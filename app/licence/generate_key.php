<?php

$data = [
    "customer" => "شرکت گاز مرکزی",
    "expire"   => "2027-10-30",
    "version"  => "1.0"
];

$json = json_encode($data, JSON_UNESCAPED_UNICODE);

$privateKeyPath = getenv('LICENSE_PRIVATE_KEY_PATH');
if (!$privateKeyPath) {
    die("LICENSE_PRIVATE_KEY_PATH is required");
}

$privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));

if (!$privateKey) {
    die("Private Key Error");
}

$ok = openssl_sign(
    $json,
    $signature,
    $privateKey,
    OPENSSL_ALGO_SHA256
);

if (!$ok) {
    die("Sign Error");
}

$license = [
    "key_id" => getenv('LICENSE_ACTIVE_KEY_ID') ?: "2026-rotated",
    "data" => $data,
    "signature" => base64_encode($signature)
];

file_put_contents(
    "license.dat",
    json_encode($license, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo "License Created Successfully";
