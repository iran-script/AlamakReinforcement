# Flutter offline build handoff

The current project Maven repository retains 66 hash-manifest entries. A refreshed online Gradle resolution also identified three missing AGP plugin-classpath modules in addition to the five runtime modules in `MISSING-DEPENDENCIES.csv`. The connected-machine script resolves the entire build closure rather than relying on this partial list.

## Prepare the package on a connected Windows computer

Use the same project revision and `pubspec.lock`. Install Flutter 3.47.0, JDK 21, Android SDK Platform 36, the exact Build Tools selected by Gradle, and NDK `28.2.13676358`. The script records the exact SDK Build Tools version and can include the Platform, Build Tools, and NDK files in the package.

From the Flutter project root, run:

```powershell
.\tools\Prepare-OfflineBuildPackage.ps1 `
  -FlutterExecutable 'C:\path\to\flutter\bin\flutter.bat' `
  -JavaHome 'C:\path\to\jdk-21' `
  -BundlePath 'D:\alamak-supervisor-offline'
```

The script uses an isolated Gradle home and Pub cache, checks the lockfile, runs `flutter pub get --enforce-lockfile`, analyze, tests, and a real online APK build. It exports every module file Gradle resolved (POM, JAR, AAR, Gradle Module Metadata, and other published artifacts) into standard Maven paths, writes Gradle dependency/build-environment reports, includes the wrapper distribution ZIP and public Pub package cache, checks the exact SDK components, then verifies both direct Gradle and Flutter offline builds. It produces a folder and a `.zip` archive with a full SHA-256 manifest. It never copies user Gradle settings, `.env` files, or Pub credentials.

The script stops if the online APK build fails or the offline verification fails. It does not change dependency versions or rewrite `pubspec.lock`.

## Restore on the offline development computer

Extract the package to a new folder, then run from the same Flutter project checkout:

```powershell
.\tools\Install-OfflineBuildPackage.ps1 `
  -PackagePath 'D:\alamak-supervisor-offline' `
  -InstallAndroidSdk
```

The importer validates every package SHA-256 before writing, merges the Maven repository without overwriting different files, restores the Gradle wrapper ZIP and public Pub cache, and installs a narrowly named Gradle init script so Flutter's included/composite build can resolve from the project Maven repository. Existing SDK components and caches are preserved. `-InstallAndroidSdk` only copies a packaged SDK component when its versioned target directory is absent.

Then run:

```powershell
flutter pub get --offline
flutter analyze
flutter test
Set-Location .\android
.\gradlew.bat assembleDebug --offline
Set-Location ..
$env:ALAMAK_GRADLE_OFFLINE = '1'
flutter build apk --debug --offline
Get-FileHash .\build\app\outputs\flutter-apk\app-debug.apk -Algorithm SHA256
```

The Flutter build uses the imported init script to set Gradle offline mode across the composite build. After the APK has been verified, remove that temporary global integration if desired:

```powershell
Remove-Item -LiteralPath "$env:USERPROFILE\.gradle\init.d\alamak-supervisor-offline.init.gradle"
```

## Current system status

- Flutter analyze completed without issues and both Flutter tests passed in the latest run.
- All eight previously unresolved artifacts now resolve through the configured Alibaba Maven mirrors. Their valid XML POMs and cached binary SHA-256 values are recorded in `MISSING-DEPENDENCIES.csv`; they remain in Gradle's user cache rather than being copied into `android/maven-offline`.
- Online `flutter build apk --debug` succeeded. The APK is `build/app/outputs/flutter-apk/app-debug.apk` (193,618,741 bytes; SHA-256 `C4E865117B254203FF789D604F759BAB728A168E55B161B5B153178231EFF0AA`). The APK archive contains an Android manifest and DEX files.
- `gradlew.bat assembleDebug --offline` succeeded using the existing Gradle cache. The portable transfer package scripts were not run.
- ADB currently reports no attached device, so install and launch on Samsung remain unverified.
- No dependency was removed or substituted, TLS checks were not changed, and no Laravel database or migration was touched.
