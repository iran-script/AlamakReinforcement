<?php

$data = [
    "customer" => "شرکت گاز مرکزی",
    "expire"   => "2027-07-31",
    "version"  => "1.0"
];

$json = json_encode($data, JSON_UNESCAPED_UNICODE);

$privateKey = openssl_pkey_get_private(
    file_get_contents("private.pem")
);

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
    "data" => $data,
    "signature" => base64_encode($signature)
];

file_put_contents(
    "license.dat",
    json_encode($license, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo "License Created Successfully";