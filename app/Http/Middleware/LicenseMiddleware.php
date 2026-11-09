<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LicenseMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($this->mayBypassForLocalDevelopment($request)) {
            return $next($request);
        }

        $licensePath = $this->resolveConfiguredPath(
            config('license.license_path'),
            storage_path('license/license.dat')
        );

        if (! is_file($licensePath)) {

            return response()->view("license-expired");

        }

        $license = json_decode(file_get_contents($licensePath), true);
        if (! is_array($license) || ! is_array($license['data'] ?? null) || ! is_string($license['signature'] ?? null)) {
            return response()->view('license-expired');
        }

        $keyId = $license['key_id'] ?? null;
        if ($keyId !== null && (! is_string($keyId) || ! preg_match('/^[A-Za-z0-9._-]+$/', $keyId))) {
            return response()->view('license-expired');
        }

        // Legacy licenses have no key_id and are verified only with the legacy key.
        // A new license with an unknown key_id must never fall back to that key.
        $legacyPublicKeyPath = $this->resolveConfiguredPath(
            config('license.legacy_public_key_path'),
            storage_path('license/public.pem')
        );
        $publicKeysPath = $this->resolveConfiguredPath(
            config('license.public_keys_path'),
            storage_path('license/keys')
        );

        $publicKeyPath = $keyId === null
            ? $legacyPublicKeyPath
            : rtrim($publicKeysPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$keyId.'.pem';

        if (! is_file($publicKeyPath)) {
            return response()->view('license-expired');
        }

        $publicKey = openssl_pkey_get_public(file_get_contents($publicKeyPath));
        if ($publicKey === false) {
            return response()->view('license-expired');
        }

        $result = openssl_verify(

            json_encode($license['data'], JSON_UNESCAPED_UNICODE),
            base64_decode($license['signature'], true),

            $publicKey,

            config('license.algorithm')

        );

        if ($result !== 1) {

            return response()->view("license-expired");

        }

        if (today()->gt($license["data"]["expire"])) {

            return response()->view("license-expired");

        }

        return $next($request);

    }

    private function mayBypassForLocalDevelopment(Request $request): bool
    {
        if (config('app.env') !== 'local' || ! config('license.dev_bypass', false)) {
            return false;
        }

        // Use the socket peer address, not Request::ip(), so forwarded headers
        // cannot make a remote client appear to be connecting from loopback.
        $remoteAddress = $request->server('REMOTE_ADDR');
        if (! is_string($remoteAddress)) {
            return false;
        }

        $packedAddress = inet_pton($remoteAddress);

        return $packedAddress === inet_pton('127.0.0.1')
            || (strlen($packedAddress ?: '') === 4 && ord($packedAddress[0]) === 127)
            || $packedAddress === inet_pton('::1');
    }

    private function resolveConfiguredPath(?string $configuredPath, string $fallback): string
    {
        if (! is_string($configuredPath) || trim($configuredPath) === '') {
            return $fallback;
        }

        $path = trim($configuredPath);

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
