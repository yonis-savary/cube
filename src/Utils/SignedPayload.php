<?php

namespace Cube\Utils;

use RuntimeException;

use function Cube\env;

/**
 * Prepends an HMAC (keyed on `APP_KEY`) to a payload stored outside the application, so that it can
 * be checked before being trusted : `unserialize()` must never read something an attacker could write
 */
class SignedPayload
{
    private const MAC_ALGORITHM = 'sha256';
    private const MAC_LENGTH = 64;

    public static function sign(string $payload, ?string $key = null): string
    {
        return self::computeMac($payload, $key).$payload;
    }

    /**
     * @return ?string The original payload, `null` when the signature does not match
     */
    public static function verify(string $signed, ?string $key = null): ?string
    {
        $mac = substr($signed, 0, self::MAC_LENGTH);
        $payload = substr($signed, self::MAC_LENGTH);

        return hash_equals(self::computeMac($payload, $key), $mac)
            ? $payload
            : null;
    }

    private static function computeMac(string $payload, ?string $key): string
    {
        $key ??= env('APP_KEY', false);
        if (!$key)
            throw new RuntimeException('Cannot sign or verify a payload without an app key (APP_KEY) or a provided key');

        return hash_hmac(self::MAC_ALGORITHM, $payload, hash_hkdf('sha256', $key, 32, 'cube-signed-payload'));
    }
}
