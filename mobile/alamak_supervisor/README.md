# Alamak Supervisor (Flutter)

Android source for the field supervisor client. The Laravel backend remains in the parent directory.

## Build

Requires Flutter 3.47 / Dart 3.13, Android SDK 37 and JDK 21. Dependencies are pinned in `pubspec.lock`.

```powershell
flutter pub get
flutter analyze
flutter test
flutter build apk --debug
```

On the first build, Gradle needs the pinned Android Gradle Plugin and AndroidX artifacts from Google Maven and Maven Central. For an air-gapped build, transfer the Flutter SDK/cache and Gradle dependency cache from a connected build machine, then use `flutter pub get --offline` and Gradle offline mode.

## Connect to Laravel

On first launch, enter the base URL of the internal Laravel server (for example `https://alamak.intra`). The app checks `/api/v1/mobile/health` before storing the address. Release builds reject HTTP and map tiles outside the Laravel host. Debug builds accept loopback and RFC1918 HTTP addresses for local development. After changing servers, existing credentials and local drafts for the old account are removed.

Login uses the existing `/api/login` endpoint. The backend issues a Sanctum token with the `mobile:supervisor` ability and rejects Boss and Superadmin accounts. Versioned app routes live under `/api/v1/supervisor`; logout revokes the current token.

## Maps and offline work

The app reads the active XYZ Raster profile from `/api/v1/supervisor/map/config`; the base map is separate from riser data. OSM is used only as a debug fallback when no profile can be fetched. Release builds require an HTTPS tile URL on the Laravel host (or a relative same-origin URL).

Risers are fetched from the supervisor-scoped, paginated API (at most 200 per request); the first page is rendered locally. Draft case/operation forms and selected photos are saved in the app documents directory and SQLite. Pending records are retried manually after reconnecting with a fresh server identity check. A queued draft is never displayed as server-approved.

Current offline storage is app-sandboxed, but SQLite and copied photos are not encrypted at rest. Background retry, conflict resolution, resumable photo chunks, GPS, and encrypted offline map/feature caches are not implemented yet. Do not use this build for sensitive offline capture until device encryption policy and these controls are completed.
