<?php

namespace Cube\Security\Encryption;

use Exception;
use RuntimeException;

use function Cube\env;

/**
 * Reads values encrypted before `Encryption` : AES-256-CBC, no authentication, raw key.
 * @deprecated Nothing is encrypted this way anymore, re-encrypt what you read with `Encryption`.
 */
class LegacyEncryption
{
    private const CIPHER = 'aes-256-cbc';

    public static function decrypt(string $encrypted, ?string $key = null): string
    {
        $key ??= env('APP_KEY', false);
        if (!$key)
            throw new RuntimeException('Cannot decrypt without an app key or provided app key');

        if (false === $data = base64_decode($encrypted, true))
            throw new Exception('Invalid base64 input.');

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $plaintext = openssl_decrypt(substr($data, $ivLength), self::CIPHER, $key, OPENSSL_RAW_DATA, substr($data, 0, $ivLength));

        if (false === $plaintext)
            throw new Exception('Decryption failed.');

        return $plaintext;
    }
}
