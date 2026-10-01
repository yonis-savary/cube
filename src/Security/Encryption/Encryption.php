<?php

namespace Cube\Security\Encryption;

use Exception;
use RuntimeException;

use function Cube\env;

/**
 * Authenticated encryption (AES-256-GCM) with a key derived from the given one, or from `APP_KEY`
 */
class Encryption
{
    public const PREFIX = 'v2:';

    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;

    public static function encrypt(string $content, ?string $key = null): string
    {
        $key = self::deriveKey($key);
        $nonce = random_bytes(openssl_cipher_iv_length(self::CIPHER));

        $ciphertext = openssl_encrypt($content, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG_LENGTH);
        if (false === $ciphertext)
            throw new Exception('Encryption failed.');

        return self::PREFIX.base64_encode($nonce.$tag.$ciphertext);
    }

    public static function supports(string $encrypted): bool
    {
        return str_starts_with($encrypted, self::PREFIX);
    }

    public static function decrypt(string $encrypted, ?string $key = null): string
    {
        $key = self::deriveKey($key);

        if (!self::supports($encrypted) || false === $data = base64_decode(substr($encrypted, strlen(self::PREFIX)), true))
            throw new Exception('Invalid encrypted input.');

        $nonceLength = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($data) < $nonceLength + self::TAG_LENGTH)
            throw new Exception('Decryption failed.');

        $nonce = substr($data, 0, $nonceLength);
        $tag = substr($data, $nonceLength, self::TAG_LENGTH);
        $ciphertext = substr($data, $nonceLength + self::TAG_LENGTH);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if (false === $plaintext)
            throw new Exception('Decryption failed.');

        return $plaintext;
    }

    private static function deriveKey(?string $key): string
    {
        $key ??= env('APP_KEY', false);
        if (!$key)
            throw new RuntimeException('Cannot encrypt or decrypt without an app key or provided app key');

        // The cipher wants exactly 32 bytes : derive them rather than cut or pad the given key
        return hash_hkdf('sha256', $key, 32, 'cube-encryption');
    }
}
