<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LicenseMiddleware
{
    public function handle(Request $request, Closure $next)
    {

        $licensePath = storage_path("license/license.dat");
        $publicKeyPath = storage_path("license/public.pem");

        if (!file_exists($licensePath) || !file_exists($publicKeyPath)) {

            return response()->view("license-expired");

        }

        $license = json_decode(
            file_get_contents($licensePath),
            true
        );

        $publicKey = openssl_pkey_get_public(
            file_get_contents($publicKeyPath)
        );

        $result = openssl_verify(

            json_encode(
                $license["data"],
                JSON_UNESCAPED_UNICODE
            ),

            base64_decode($license["signature"]),

            $publicKey,

            OPENSSL_ALGO_SHA256

        );

        if ($result !== 1) {

            return response()->view("license-expired");

        }

        if (today()->gt($license["data"]["expire"])) {

            return response()->view("license-expired");

        }

        return $next($request);

    }
}