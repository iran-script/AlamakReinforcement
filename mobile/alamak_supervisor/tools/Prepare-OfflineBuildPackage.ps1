[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $FlutterExecutable,

    [Parameter(Mandatory = $true)]
    [string] $JavaHome,

    [string] $BundlePath = (Join-Path $PSScriptRoot '..\offline-build-package'),

    [bool] $IncludeAndroidSdk = $true
)

$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$androidRoot = Join-Path $projectRoot 'android'
$flutterCommand = (Resolve-Path -LiteralPath $FlutterExecutable).Path
$flutterRoot = [System.IO.Path]::GetFullPath((Join-Path (Split-Path -Parent (Split-Path -Parent $flutterCommand)) ''))
$javaRoot = (Resolve-Path -LiteralPath $JavaHome).Path
$bundleRoot = [System.IO.Path]::GetFullPath($BundlePath)
$gradleHome = Join-Path $bundleRoot '.work\gradle-home'
$pubCache = Join-Path $bundleRoot '.work\pub-cache'
$mavenRoot = Join-Path $bundleRoot 'maven-repository'
$sdkPackageRoot = Join-Path $bundleRoot 'android-sdk'
$wrapperPackageRoot = Join-Path $bundleRoot 'gradle-wrapper\dists'
$originalLock = Join-Path $projectRoot 'pubspec.lock'
$lockHashBefore = (Get-FileHash -LiteralPath $originalLock -Algorithm SHA256).Hash

if (Test-Path -LiteralPath $bundleRoot) {
    if ((Get-ChildItem -LiteralPath $bundleRoot -Force | Measure-Object).Count -gt 0) {
        throw "Bundle output must be a new or empty directory: $bundleRoot"
    }
} else {
    New-Item -ItemType Directory -Path $bundleRoot -Force | Out-Null
}
New-Item -ItemType Directory -Path $gradleHome,$pubCache,$mavenRoot -Force | Out-Null

$oldJavaHome = $env:JAVA_HOME
$oldPath = $env:Path
$oldGradleHome = $env:GRADLE_USER_HOME
$oldPubCache = $env:PUB_CACHE
$oldAndroidHome = $env:ANDROID_HOME
$oldAndroidSdkRoot = $env:ANDROID_SDK_ROOT
$oldAlamakOffline = $env:ALAMAK_GRADLE_OFFLINE
$env:JAVA_HOME = $javaRoot
$env:Path = "$javaRoot\bin;$env:Path"
$env:GRADLE_USER_HOME = $gradleHome
$env:PUB_CACHE = $pubCache

function Invoke-Checked {
    param([string] $Program, [string[]] $Arguments, [string] $Label)
    & $Program @Arguments
    if ($LASTEXITCODE -ne 0) { throw "$Label failed with exit code $LASTEXITCODE" }
}

function Get-RelativeFilePath([string] $Root, [string] $Path) {
    $rootFull = [System.IO.Path]::GetFullPath($Root).TrimEnd([System.IO.Path]::DirectorySeparatorChar,[System.IO.Path]::AltDirectorySeparatorChar) + [System.IO.Path]::DirectorySeparatorChar
    $pathFull = [System.IO.Path]::GetFullPath($Path)
    if (-not $pathFull.StartsWith($rootFull,[System.StringComparison]::OrdinalIgnoreCase)) { throw "File is outside root: $Path" }
    return $pathFull.Substring($rootFull.Length)
}

function Add-VerifiedFile {
    param([string] $Source, [string] $RelativePath)
    $relative = $RelativePath.Replace('\','/')
    if ($relative.StartsWith('/') -or $relative -match '(^|/)\.\.(/|$)') { throw "Unsafe package path: $relative" }
    $destination = Join-Path $mavenRoot ($relative.Replace('/',[System.IO.Path]::DirectorySeparatorChar))
    $destinationDir = Split-Path -Parent $destination
    New-Item -ItemType Directory -Path $destinationDir -Force | Out-Null
    $hash = (Get-FileHash -LiteralPath $Source -Algorithm SHA256).Hash
    if (Test-Path -LiteralPath $destination -PathType Leaf) {
        if ((Get-FileHash -LiteralPath $destination -Algorithm SHA256).Hash -ne $hash) {
            throw "Different artifact already exists at $relative; refusing to overwrite."
        }
    } else {
        Copy-Item -LiteralPath $Source -Destination $destination
    }
    $script:artifactHashes[$relative] = $hash
}

try {
    $javaVersion = (& (Join-Path $javaRoot 'bin\java.exe') -version 2>&1 | Out-String)
    if ($javaVersion -notmatch 'version "(17|21|25)\.') { throw "JDK 17, 21, or 25 is required. Detected: $javaVersion" }
    $flutterMachine = (& $flutterCommand --version --machine | ConvertFrom-Json)
    if ($LASTEXITCODE -ne 0) { throw 'Unable to read Flutter version.' }
    if ($flutterMachine.frameworkVersion -ne '3.44.2') {
        throw "Flutter 3.44.2 is required for this package; detected $($flutterMachine.frameworkVersion)."
    }

    $sdkRoot = $env:ANDROID_SDK_ROOT
    if (-not $sdkRoot) { $sdkRoot = $env:ANDROID_HOME }
    if (-not $sdkRoot) {
        $localProperties = Get-Content -LiteralPath (Join-Path $androidRoot 'local.properties') -Raw
        if ($localProperties -match '(?m)^sdk\.dir=(.+)$') { $sdkRoot = $Matches[1].Replace('\\','\') }
    }
    if (-not $sdkRoot -or -not (Test-Path -LiteralPath $sdkRoot -PathType Container)) {
        throw "Android SDK was not found. Set ANDROID_SDK_ROOT on the connected computer."
    }
    $sdkRoot = (Resolve-Path -LiteralPath $sdkRoot).Path
    $env:ANDROID_HOME = $sdkRoot
    $env:ANDROID_SDK_ROOT = $sdkRoot

    $flutterExtension = Join-Path $flutterRoot 'packages\flutter_tools\gradle\src\main\kotlin\FlutterExtension.kt'
    $flutterExtensionText = Get-Content -LiteralPath $flutterExtension -Raw
    foreach ($entry in @(
        @{ Name='compileSdkVersion'; Key='compileSdk'; Directory='platforms\android-' },
        @{ Name='ndkVersion'; Key='ndk'; Directory='ndk\' }
    )) {
        $entryPattern = 'val ' + [regex]::Escape($entry.Name) + ':\s+(?:Int|String)\s*=\s*"?([^"\r\n]+)"?'
        if ($flutterExtensionText -notmatch $entryPattern) {
            throw "Could not read $($entry.Name) from Flutter SDK."
        }
        $value = $Matches[1]
        if ($entry.Key -eq 'compileSdk') { $compileSdk = $value }
        if ($entry.Key -eq 'ndk') { $ndkVersion = $value }
    }
    $compileSdkDir = Join-Path $sdkRoot "platforms\android-$compileSdk"
    $ndkDir = Join-Path $sdkRoot "ndk\$ndkVersion"
    if (-not (Test-Path -LiteralPath (Join-Path $compileSdkDir 'android.jar'))) { throw "Required Android SDK Platform android-$compileSdk is missing." }
    if (-not (Test-Path -LiteralPath $ndkDir)) { throw "Required Flutter NDK $ndkVersion is missing." }

    $lockBackup = Join-Path $bundleRoot 'pubspec.lock'
    Copy-Item -LiteralPath $originalLock -Destination $lockBackup
    Push-Location $projectRoot
    try {
        Invoke-Checked $flutterCommand @('pub','get','--enforce-lockfile') 'flutter pub get --enforce-lockfile'
        Invoke-Checked $flutterCommand @('analyze') 'flutter analyze'
        Invoke-Checked $flutterCommand @('test') 'flutter test'
        Invoke-Checked $flutterCommand @('build','apk','--debug') 'flutter build apk --debug'
    } finally { Pop-Location }

    if ((Get-FileHash -LiteralPath $originalLock -Algorithm SHA256).Hash -ne $lockHashBefore) {
        throw 'pubspec.lock changed during package preparation; restore it and rerun with the exact lockfile.'
    }

    $gradleWrapper = Join-Path $androidRoot 'gradlew.bat'
    $script:artifactHashes = @{}
    $wrapperPropertiesPath = Join-Path $androidRoot 'gradle\wrapper\gradle-wrapper.properties'
    $wrapperDistribution = (Select-String -LiteralPath $wrapperPropertiesPath -Pattern '^distributionUrl=').Line
    if ($wrapperDistribution -notmatch 'gradle-9\.3\.1-all\.zip') { throw "Expected Gradle 9.3.1 wrapper; found: $wrapperDistribution" }
    $agpLine = (Select-String -LiteralPath (Join-Path $androidRoot 'settings.gradle.kts') -Pattern 'id\("com.android.application"\) version').Line.Trim()
    $kotlinLine = (Select-String -LiteralPath (Join-Path $androidRoot 'settings.gradle.kts') -Pattern 'id\("org.jetbrains.kotlin.android"\) version').Line.Trim()
    if ($agpLine -notmatch 'version "9\.1\.0"' -or $kotlinLine -notmatch 'version "2\.4\.0"') {
        throw "Unexpected Android plugin versions: $agpLine; $kotlinLine"
    }
    Push-Location $androidRoot
    try {
        $gradleVersionOutput=& $gradleWrapper @('--version') 2>&1
        if($LASTEXITCODE -ne 0 -or ($gradleVersionOutput -join "`n") -notmatch '(?m)^Gradle 9\.3\.1$') { throw 'Gradle Wrapper did not start as version 9.3.1.' }
        foreach($report in @(
            @{Name='gradle-runtime-dependencies.txt'; Args=@(':app:dependencies','--configuration','debugRuntimeClasspath')},
            @{Name='gradle-root-build-environment.txt'; Args=@('buildEnvironment')},
            @{Name='gradle-app-build-environment.txt'; Args=@(':app:buildEnvironment')}
        )){
            $reportOutput=& $gradleWrapper @($report.Args) 2>&1
            if($LASTEXITCODE -ne 0){ throw "Gradle dependency report failed: $($report.Name)" }
            [System.IO.File]::WriteAllLines((Join-Path $bundleRoot $report.Name),[string[]]@($reportOutput),[System.Text.UTF8Encoding]::new($false))
        }
    } finally { Pop-Location }

    $apkSource = Join-Path $projectRoot 'build\app\outputs\flutter-apk\app-debug.apk'
    if (-not (Test-Path -LiteralPath $apkSource -PathType Leaf)) { throw 'Flutter reported success but the APK file is missing.' }
    $apkDir = Join-Path $bundleRoot 'artifacts'
    New-Item -ItemType Directory -Path $apkDir -Force | Out-Null
    Copy-Item -LiteralPath $apkSource -Destination (Join-Path $apkDir 'app-debug.apk')

    # Include the already verified project Maven artifacts without adding documentation files.
    $projectMaven = Join-Path $androidRoot 'maven-offline'
    $projectSum = Join-Path $projectMaven 'SHA256SUMS.txt'
    foreach ($line in [System.IO.File]::ReadLines($projectSum)) {
        if ($line -notmatch '^([A-Fa-f0-9]{64})\s+\*(.+)$') { throw "Malformed project Maven checksum: $line" }
        $relative = $Matches[2]
        $source = Join-Path $projectMaven ($relative.Replace('/',[System.IO.Path]::DirectorySeparatorChar))
        if (-not (Test-Path -LiteralPath $source -PathType Leaf)) { throw "Project Maven artifact missing: $relative" }
        if ((Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash -ne $Matches[1].ToUpperInvariant()) { throw "Project Maven SHA-256 mismatch: $relative" }
        Add-VerifiedFile $source $relative
    }

    # Mirror every Gradle-resolved module file from this isolated, project-only Gradle home.
    $filesRoot = Join-Path $gradleHome 'caches\modules-2\files-2.1'
    if (-not (Test-Path -LiteralPath $filesRoot)) { throw 'Gradle resolved no module artifacts into the isolated cache.' }
    foreach ($file in Get-ChildItem -LiteralPath $filesRoot -Recurse -File) {
        $rel=Get-RelativeFilePath $filesRoot $file.FullName
        $parts=$rel.Split([System.IO.Path]::DirectorySeparatorChar)
        if ($parts.Count -lt 5) { throw "Unexpected Gradle module-cache path: $rel" }
        $group=$parts[0]; $artifact=$parts[1]; $version=$parts[2]; $fileName=$parts[$parts.Count-1]
        $mavenPath=($group.Replace('.','/')+'/'+$artifact+'/'+$version+'/'+$fileName)
        Add-VerifiedFile $file.FullName $mavenPath
    }
    $mavenLines=foreach($path in ($script:artifactHashes.Keys | Sort-Object)){ "$($script:artifactHashes[$path]) *$path" }
    [System.IO.File]::WriteAllLines((Join-Path $mavenRoot 'SHA256SUMS.txt'),$mavenLines,[System.Text.UTF8Encoding]::new($false))

    # Validate with a clean Gradle home: a successful offline build must use the
    # exported Maven repository, not artifacts left in the online Gradle cache.
    $verifyGradleHome=Join-Path $bundleRoot '.work\verify-gradle-home'
    $wrapperSource=Join-Path $gradleHome 'wrapper\dists'
    $wrapperZips=Get-ChildItem -LiteralPath $wrapperSource -Recurse -File -Filter '*.zip' -ErrorAction Stop
    if(@($wrapperZips).Count -eq 0){ throw 'The Gradle Wrapper distribution archive was not cached for transfer.' }
    foreach($zip in $wrapperZips){
        $rel=Get-RelativeFilePath $wrapperSource $zip.FullName
        $dest=Join-Path (Join-Path $verifyGradleHome 'wrapper\dists') $rel
        New-Item -ItemType Directory -Path (Split-Path -Parent $dest) -Force | Out-Null
        Copy-Item -LiteralPath $zip.FullName -Destination $dest
    }
    $initDirectory=Join-Path $verifyGradleHome 'init.d'
    New-Item -ItemType Directory -Path $initDirectory -Force | Out-Null
    $repoPathGroovy=$mavenRoot.Replace('\','/').Replace("'","\'")
    $offlineInit=@"
def alamakOfflineRepo = new File('$repoPathGroovy').toURI()
gradle.beforeSettings { settings ->
    settings.pluginManagement.repositories.maven { name = 'AlamakPackageMaven'; url = alamakOfflineRepo }
    settings.pluginManagement.repositories.google()
    settings.pluginManagement.repositories.mavenCentral()
    settings.pluginManagement.repositories.gradlePluginPortal()
    settings.dependencyResolutionManagement.repositories.maven { name = 'AlamakPackageMaven'; url = alamakOfflineRepo }
    settings.dependencyResolutionManagement.repositories.google()
    settings.dependencyResolutionManagement.repositories.mavenCentral()
}
gradle.beforeProject { project ->
    project.buildscript.repositories.maven { name = 'AlamakPackageBuildscript'; url = alamakOfflineRepo }
    project.repositories.maven { name = 'AlamakPackageProject'; url = alamakOfflineRepo }
}
if (System.getenv('ALAMAK_GRADLE_OFFLINE') == '1') { gradle.startParameter.offline = true }
"@
    [System.IO.File]::WriteAllText((Join-Path $initDirectory 'alamak-package-offline.init.gradle'),$offlineInit,[System.Text.UTF8Encoding]::new($false))
    $env:GRADLE_USER_HOME=$verifyGradleHome
    $env:ALAMAK_GRADLE_OFFLINE='1'
    Push-Location $androidRoot
    try { Invoke-Checked (Join-Path $androidRoot 'gradlew.bat') @('--offline',':app:assembleDebug') 'Gradle offline assembleDebug' }
    finally { Pop-Location }
    Push-Location $projectRoot
    try { Invoke-Checked $flutterCommand @('build','apk','--debug','--offline') 'Flutter offline APK build' }
    finally { Pop-Location }

    # Gradle's distribution archive is needed by the wrapper when the target has no network.
    foreach($zip in $wrapperZips){
        $rel=Get-RelativeFilePath $wrapperSource $zip.FullName
        $dest=Join-Path $wrapperPackageRoot $rel
        New-Item -ItemType Directory -Path (Split-Path -Parent $dest) -Force | Out-Null
        Copy-Item -LiteralPath $zip.FullName -Destination $dest
    }

    # Copy only public dependency cache trees. Never include Pub credentials or user settings.
    foreach($cachePart in @('hosted','hosted-hashes','git','git-cache')){
        $source=Join-Path $pubCache $cachePart
        if(Test-Path -LiteralPath $source){
            $pubDestination=Join-Path $bundleRoot "pub-cache\$cachePart"
            New-Item -ItemType Directory -Path (Split-Path -Parent $pubDestination) -Force | Out-Null
            Copy-Item -LiteralPath $source -Destination $pubDestination -Recurse -Force
        }
    }
    Get-ChildItem -LiteralPath (Join-Path $bundleRoot 'pub-cache') -Recurse -File -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match '(?i)credential|token|password|\.env' } |
        ForEach-Object { throw "Refusing to package sensitive-looking Pub cache file: $($_.FullName)" }

    $probePath=Join-Path $bundleRoot '.work\sdk-components.init.gradle'
    $probe=@'
gradle.projectsEvaluated {
    def app = gradle.rootProject.findProject(':app')
    if (app != null) {
        def android = app.extensions.findByName('android')
        println("ALAMAK_COMPILE_SDK=" + android.compileSdkVersion)
        println("ALAMAK_BUILD_TOOLS=" + android.sdkComponents.buildToolsVersion.get())
    }
}
'@
    [System.IO.File]::WriteAllText($probePath,$probe,[System.Text.UTF8Encoding]::new($false))
    Push-Location $androidRoot
    try {
        $probeOutput=& (Join-Path $androidRoot 'gradlew.bat') @('--init-script',$probePath,':app:properties') 2>&1
        if($LASTEXITCODE -ne 0){ throw 'Could not query the Android Gradle SDK component versions.' }
    } finally { Pop-Location }
    $compileSdkMatch=[regex]::Match(($probeOutput -join "`n"),'ALAMAK_COMPILE_SDK=(?:android-)?(\d+)')
    $buildToolsMatch=[regex]::Match(($probeOutput -join "`n"),'ALAMAK_BUILD_TOOLS=([0-9.]+)')
    if(-not $compileSdkMatch.Success -or -not $buildToolsMatch.Success){ throw 'Gradle did not report its selected compile SDK and Build Tools versions.' }
    $compileSdk=$compileSdkMatch.Groups[1].Value
    $buildToolsVersion=$buildToolsMatch.Groups[1].Value
    $buildTools=Get-Item -LiteralPath (Join-Path $sdkRoot "build-tools\$buildToolsVersion") -ErrorAction SilentlyContinue
    if(-not $buildTools){ throw "Required Android Build Tools $buildToolsVersion is missing." }
    if ($IncludeAndroidSdk) {
        foreach($component in @(
            @{Source=$compileSdkDir; Relative="platforms\android-$compileSdk"},
            @{Source=$buildTools.FullName; Relative="build-tools\$buildToolsVersion"},
            @{Source=$ndkDir; Relative="ndk\$ndkVersion"}
        )){
            $dest=Join-Path $sdkPackageRoot $component.Relative
            New-Item -ItemType Directory -Path (Split-Path -Parent $dest) -Force | Out-Null
            Copy-Item -LiteralPath $component.Source -Destination $dest -Recurse
        }
    }

    $pubDeps = & $flutterCommand pub deps --json | ConvertFrom-Json
    if ($LASTEXITCODE -ne 0) { throw 'Could not export the locked Dart/Flutter dependency graph.' }
    foreach($lockedPackage in @(
        @{Name='maplibre_gl';Version='0.27.1'},
        @{Name='flutter_secure_storage';Version='9.2.4'},
        @{Name='image_picker';Version='1.1.2'}
    )){
        $resolvedPackage=$pubDeps.packages | Where-Object { $_.name -eq $lockedPackage.Name } | Select-Object -First 1
        if(-not $resolvedPackage -or $resolvedPackage.version -ne $lockedPackage.Version){
            throw "Locked package version mismatch for $($lockedPackage.Name); expected $($lockedPackage.Version)."
        }
    }
    $manifest = [ordered]@{
        generatedAtUtc = [DateTime]::UtcNow.ToString('o')
        flutter = $flutterMachine
        flutterRoot = $flutterRoot
        javaVersion = $javaVersion.Trim()
        gradleWrapper = $wrapperDistribution
        androidGradlePlugin = $agpLine
        kotlinPlugin = $kotlinLine
        dartDependencyGraph = $pubDeps
        androidSdk = [ordered]@{ root=$sdkRoot; compileSdk=$compileSdk; buildTools=$buildToolsVersion; ndk=$ndkVersion; packaged=[bool]$IncludeAndroidSdk }
        gradleMavenFileCount = $script:artifactHashes.Count
        apk = [ordered]@{ path='artifacts/app-debug.apk'; bytes=(Get-Item -LiteralPath (Join-Path $apkDir 'app-debug.apk')).Length; sha256=(Get-FileHash -LiteralPath (Join-Path $apkDir 'app-debug.apk') -Algorithm SHA256).Hash }
        packageContents = 'Maven repository closure, Gradle wrapper ZIP, locked Dart package cache, SDK components (when requested), and debug APK; no project .env or credentials.'
    }
    $manifestPath=Join-Path $bundleRoot 'offline-build-manifest.json'
    $manifest | ConvertTo-Json -Depth 80 | Set-Content -LiteralPath $manifestPath -Encoding utf8

    $hashLines=foreach($file in Get-ChildItem -LiteralPath $bundleRoot -Recurse -File | Where-Object { $_.Name -ne 'PACKAGE-SHA256SUMS.txt' -and $_.FullName -notlike "$bundleRoot\.work\*" }){
        $rel=(Get-RelativeFilePath $bundleRoot $file.FullName).Replace('\','/')
        if($rel -match '(?i)(^|/)\.env($|\.)|credentials\.json|(^|/)[^/]*(token|password)[^/]*$'){
            throw "Refusing to package a sensitive-looking file: $rel"
        }
        "$(Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256 | Select-Object -ExpandProperty Hash) *$rel"
    }
    [System.IO.File]::WriteAllLines((Join-Path $bundleRoot 'PACKAGE-SHA256SUMS.txt'),$hashLines,[System.Text.UTF8Encoding]::new($false))

    $lockHashAfter=(Get-FileHash -LiteralPath $originalLock -Algorithm SHA256).Hash
    if($lockHashAfter -ne $lockHashBefore){ throw 'pubspec.lock changed after the final package check.' }

    $workFull=[System.IO.Path]::GetFullPath((Join-Path $bundleRoot '.work'))
    if(-not $workFull.StartsWith($bundleRoot + [System.IO.Path]::DirectorySeparatorChar,[System.StringComparison]::OrdinalIgnoreCase)){
        throw 'Refusing to remove a work directory outside the package output.'
    }
    Remove-Item -LiteralPath $workFull -Recurse -Force

    $zipPath="$bundleRoot.zip"
    if(Test-Path -LiteralPath $zipPath){ throw "Refusing to overwrite package archive: $zipPath" }
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory($bundleRoot,$zipPath,[System.IO.Compression.CompressionLevel]::Optimal,$false)
    Write-Output "Offline build package created: $bundleRoot"
    Write-Output "Archive: $zipPath"
    Write-Output "APK SHA-256: $($manifest.apk.sha256)"
} finally {
    $env:JAVA_HOME=$oldJavaHome; $env:Path=$oldPath; $env:GRADLE_USER_HOME=$oldGradleHome; $env:PUB_CACHE=$oldPubCache
    $env:ANDROID_HOME=$oldAndroidHome; $env:ANDROID_SDK_ROOT=$oldAndroidSdkRoot; $env:ALAMAK_GRADLE_OFFLINE=$oldAlamakOffline
}
