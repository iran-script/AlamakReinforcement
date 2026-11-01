<?php

return [
    'dev_bypass' => env('LICENSE_DEV_BYPASS', false),
    'legacy_public_key_path' => env('LICENSE_LEGACY_PUBLIC_KEY_PATH', storage_path('license/public.pem')),
    'public_keys_path' => env('LICENSE_PUBLIC_KEYS_PATH', storage_path('license/keys')),
    'private_key_path' => env('LICENSE_PRIVATE_KEY_PATH', storage_path('license/private.pem')),
    'active_key_id' => env('LICENSE_ACTIVE_KEY_ID', '2026-rotated'),
    'license_path' => env('LICENSE_PATH', storage_path('license/license.dat')),
    'algorithm' => OPENSSL_ALGO_SHA256,
];
