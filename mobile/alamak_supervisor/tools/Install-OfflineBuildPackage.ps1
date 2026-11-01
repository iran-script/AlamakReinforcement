[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $PackagePath,

    [string] $AndroidSdkRoot,

    [switch] $InstallAndroidSdk
)

$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$androidRoot = Join-Path $projectRoot 'android'
$packageRoot = (Resolve-Path -LiteralPath $PackagePath).Path
$packageChecksums = Join-Path $packageRoot 'PACKAGE-SHA256SUMS.txt'
$mavenSource = Join-Path $packageRoot 'maven-repository'
$mavenDestination = Join-Path $androidRoot 'maven-offline'
$gradleHome = $env:GRADLE_USER_HOME
if (-not $gradleHome) { $gradleHome = Join-Path $env:USERPROFILE '.gradle' }

if (-not (Test-Path -LiteralPath $packageChecksums -PathType Leaf)) {
    throw "Missing package checksum manifest: $packageChecksums"
}

function Read-HashManifest([string] $Path) {
    $result = @{}
    foreach ($line in [System.IO.File]::ReadLines($Path)) {
        if ($line -notmatch '^([A-Fa-f0-9]{64})\s+\*(.+)$') { throw "Invalid checksum entry: $line" }
        $relative = $Matches[2].Replace('/',[System.IO.Path]::DirectorySeparatorChar)
        if ([System.IO.Path]::IsPathRooted($relative) -or $relative -match '(^|[\\])\.\.([\\]|$)') {
            throw "Unsafe relative path in checksum manifest: $relative"
        }
        $result[$relative] = $Matches[1].ToUpperInvariant()
    }
    if ($result.Count -eq 0) { throw "Empty checksum manifest: $Path" }
    return $result
}

function Get-RelativeFilePath([string] $Root, [string] $Path) {
    $rootFull = [System.IO.Path]::GetFullPath($Root).TrimEnd([System.IO.Path]::DirectorySeparatorChar,[System.IO.Path]::AltDirectorySeparatorChar) + [System.IO.Path]::DirectorySeparatorChar
    $pathFull = [System.IO.Path]::GetFullPath($Path)
    if (-not $pathFull.StartsWith($rootFull,[System.StringComparison]::OrdinalIgnoreCase)) { throw "File is outside root: $Path" }
    return $pathFull.Substring($rootFull.Length)
}

function Copy-VerifiedFiles([string] $SourceRoot, [string] $DestinationRoot, [string[]] $RelativeFiles) {
    $srcRoot = [System.IO.Path]::GetFullPath($SourceRoot)
    $dstRoot = [System.IO.Path]::GetFullPath($DestinationRoot)
    foreach ($relative in $RelativeFiles) {
        $src = Join-Path $srcRoot $relative
        $dst = Join-Path $dstRoot $relative
        if (-not (Test-Path -LiteralPath $src -PathType Leaf)) { throw "Package file is missing: $relative" }
        $srcHash = (Get-FileHash -LiteralPath $src -Algorithm SHA256).Hash
        if (Test-Path -LiteralPath $dst -PathType Leaf) {
            if ((Get-FileHash -LiteralPath $dst -Algorithm SHA256).Hash -ne $srcHash) {
                throw "Refusing to overwrite a different existing file: $dst"
            }
            continue
        }
        New-Item -ItemType Directory -Path (Split-Path -Parent $dst) -Force | Out-Null
        Copy-Item -LiteralPath $src -Destination $dst
    }
}

$packageFiles = Read-HashManifest $packageChecksums
$actualPackageFiles = Get-ChildItem -LiteralPath $packageRoot -Recurse -File |
    Where-Object { $_.Name -ne 'PACKAGE-SHA256SUMS.txt' } |
    ForEach-Object { Get-RelativeFilePath $packageRoot $_.FullName }
foreach ($relative in $actualPackageFiles) {
    if (-not $packageFiles.ContainsKey($relative)) { throw "Unhashed file found in package: $relative" }
}
if ($actualPackageFiles.Count -ne $packageFiles.Count) { throw 'Package contains missing or duplicate checksum entries.' }
foreach ($relative in $packageFiles.Keys) {
    $file = Join-Path $packageRoot $relative
    if (-not (Test-Path -LiteralPath $file -PathType Leaf)) { throw "Package manifest file is absent: $relative" }
    if ((Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash -ne $packageFiles[$relative]) {
        throw "Package SHA-256 mismatch: $relative"
    }
    if ($relative -match '(?i)(^|[\\/])\.env($|\.)|credentials\.json|(^|[\\/])[^\\/]*(token|password)[^\\/]*$') {
        throw "Refusing a package with a sensitive-looking file: $relative"
    }
}

if (-not (Test-Path -LiteralPath $mavenSource -PathType Container)) { throw 'Package has no Maven repository.' }
$mavenSum = Join-Path $mavenSource 'SHA256SUMS.txt'
$mavenFiles = Read-HashManifest $mavenSum
foreach ($relative in $mavenFiles.Keys) {
    $packageRelative = Join-Path 'maven-repository' $relative
    if (-not $packageFiles.ContainsKey($packageRelative)) { throw "Maven artifact is not covered by package checksums: $relative" }
    $file = Join-Path $mavenSource $relative
    if ((Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash -ne $mavenFiles[$relative]) {
        throw "Maven SHA-256 mismatch: $relative"
    }
}
Copy-VerifiedFiles $mavenSource $mavenDestination @($mavenFiles.Keys)

$sumPath = Join-Path $mavenDestination 'SHA256SUMS.txt'
$merged = @{}
if (Test-Path -LiteralPath $sumPath -PathType Leaf) {
    $merged = Read-HashManifest $sumPath
}
foreach ($relative in $mavenFiles.Keys) {
    if ($merged.ContainsKey($relative) -and $merged[$relative] -ne $mavenFiles[$relative]) {
        throw "Existing Maven checksum conflicts: $relative"
    }
    $merged[$relative] = $mavenFiles[$relative]
}
$sumLines = foreach ($relative in ($merged.Keys | Sort-Object)) {
    "$($merged[$relative]) *$($relative.Replace([System.IO.Path]::DirectorySeparatorChar,'/'))"
}
[System.IO.File]::WriteAllLines($sumPath,$sumLines,[System.Text.UTF8Encoding]::new($false))

# The global init script makes the project repository visible to Flutter's composite Gradle builds.
$initDirectory = Join-Path $gradleHome 'init.d'
$initPath = Join-Path $initDirectory 'alamak-supervisor-offline.init.gradle'
$repoUriPath = $mavenDestination.Replace('\','/').Replace("'","\'")
$initBody = @"
def alamakOfflineRepo = new File('$repoUriPath').toURI()
gradle.beforeSettings { settings ->
    settings.pluginManagement.repositories.maven { name = 'AlamakOfflineMaven'; url = alamakOfflineRepo }
    settings.pluginManagement.repositories.google()
    settings.pluginManagement.repositories.mavenCentral()
    settings.pluginManagement.repositories.gradlePluginPortal()
    settings.dependencyResolutionManagement.repositories.maven { name = 'AlamakOfflineMaven'; url = alamakOfflineRepo }
    settings.dependencyResolutionManagement.repositories.google()
    settings.dependencyResolutionManagement.repositories.mavenCentral()
}
gradle.beforeProject { project ->
    project.buildscript.repositories.maven { name = 'AlamakOfflineBuildscript'; url = alamakOfflineRepo }
    project.repositories.maven { name = 'AlamakOfflineProject'; url = alamakOfflineRepo }
}
if (System.getenv('ALAMAK_GRADLE_OFFLINE') == '1') { gradle.startParameter.offline = true }
"@
if (Test-Path -LiteralPath $initPath) {
    if ((Get-Content -LiteralPath $initPath -Raw) -notlike "*$repoUriPath*") {
        throw "Refusing to overwrite another Gradle init script: $initPath"
    }
} else {
    New-Item -ItemType Directory -Path $initDirectory -Force | Out-Null
    [System.IO.File]::WriteAllText($initPath,$initBody,[System.Text.UTF8Encoding]::new($false))
}

$pubSource = Join-Path $packageRoot 'pub-cache'
if (Test-Path -LiteralPath $pubSource -PathType Container) {
    $targetPubCache = $env:PUB_CACHE
    if (-not $targetPubCache) { $targetPubCache = Join-Path $env:LOCALAPPDATA 'Pub\Cache' }
    $pubFiles = Get-ChildItem -LiteralPath $pubSource -Recurse -File | ForEach-Object {
        Get-RelativeFilePath $pubSource $_.FullName
    }
    Copy-VerifiedFiles $pubSource $targetPubCache $pubFiles
}

$wrapperSource = Join-Path $packageRoot 'gradle-wrapper\dists'
if (Test-Path -LiteralPath $wrapperSource -PathType Container) {
    $wrapperFiles = Get-ChildItem -LiteralPath $wrapperSource -Recurse -File -Filter '*.zip' | ForEach-Object {
        Get-RelativeFilePath $wrapperSource $_.FullName
    }
    Copy-VerifiedFiles $wrapperSource (Join-Path $gradleHome 'wrapper\dists') $wrapperFiles
}

if ($InstallAndroidSdk) {
    $sdkSource = Join-Path $packageRoot 'android-sdk'
    if (-not (Test-Path -LiteralPath $sdkSource -PathType Container)) { throw 'Package has no Android SDK component archive.' }
    if (-not $AndroidSdkRoot) {
        $properties = Get-Content -LiteralPath (Join-Path $androidRoot 'local.properties') -Raw
        if ($properties -match '(?m)^sdk\.dir=(.+)$') { $AndroidSdkRoot = $Matches[1].Replace('\\','\') }
        if (-not $AndroidSdkRoot) { $AndroidSdkRoot = $env:ANDROID_SDK_ROOT }
        if (-not $AndroidSdkRoot) { $AndroidSdkRoot = $env:ANDROID_HOME }
    }
    if (-not $AndroidSdkRoot) { throw 'Supply -AndroidSdkRoot or set ANDROID_SDK_ROOT.' }
    foreach ($category in Get-ChildItem -LiteralPath $sdkSource -Directory) {
        foreach ($component in Get-ChildItem -LiteralPath $category.FullName -Directory) {
            $targetComponent = Join-Path (Join-Path $AndroidSdkRoot $category.Name) $component.Name
            if (Test-Path -LiteralPath $targetComponent -PathType Container) {
                Write-Output "Android SDK component already exists; preserving it: $targetComponent"
                continue
            }
            $sdkFiles = Get-ChildItem -LiteralPath $component.FullName -Recurse -File | ForEach-Object {
                Join-Path $component.Name (Get-RelativeFilePath $component.FullName $_.FullName)
            }
            Copy-VerifiedFiles $category.FullName (Join-Path $AndroidSdkRoot $category.Name) $sdkFiles
        }
    }
}

$apkRelative = 'artifacts\app-debug.apk'
if ($packageFiles.ContainsKey($apkRelative)) {
    $deliveryRoot = Join-Path $projectRoot 'offline-build-artifacts'
    $apkSource = Join-Path $packageRoot $apkRelative
    $apkDestination = Join-Path $deliveryRoot 'app-debug.apk'
    if (Test-Path -LiteralPath $apkDestination) {
        if ((Get-FileHash -LiteralPath $apkDestination -Algorithm SHA256).Hash -ne (Get-FileHash -LiteralPath $apkSource -Algorithm SHA256).Hash) {
            throw "Refusing to overwrite a different delivered APK: $apkDestination"
        }
    } else {
        New-Item -ItemType Directory -Path $deliveryRoot -Force | Out-Null
        Copy-Item -LiteralPath $apkSource -Destination $apkDestination
    }
}

Write-Output "Verified package files: $($packageFiles.Count)"
Write-Output "Imported Maven files: $($mavenFiles.Count)"
Write-Output "Project Maven repository: $mavenDestination"
Write-Output "Composite-build init script: $initPath"
Write-Output 'Then run: flutter pub get --offline'
Write-Output 'Then run: .\gradlew.bat assembleDebug --offline (from android\)'
Write-Output 'Then run: $env:ALAMAK_GRADLE_OFFLINE="1"; flutter build apk --debug --offline'
