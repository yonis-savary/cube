<?php 

namespace Cube;

use Exception;
use RuntimeException;

function encrypt(string $content, ?string $key=null): string {
    $key ??= env('APP_KEY', false);
    if (!$key)
        throw new RuntimeException("Cannot use decrypt() without an app key or provided app key");

    $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));

    $ciphertext = openssl_encrypt($content, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

    if ($ciphertext === false)
        throw new Exception("Encryption failed.");


    return base64_encode($iv . $ciphertext);
}

function decrypt(string $b64Encoded, ?string $key=null): string
{
    $key ??= env('APP_KEY', false);
    if (!$key)
        throw new RuntimeException("Cannot use decrypt() without an app key or provided app key");

    if (! $data = base64_decode($b64Encoded, true))
        throw new Exception("Invalid base64 input.");

    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $ivLength);
    $ciphertext = substr($data, $ivLength);

    if (! $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv))
        throw new Exception("Decryption failed.");

    return $plaintext;
}