<?php

namespace Cube\Utils;

use function Cube\appKey;

/**
 * Prepends an HMAC (keyed on `APP_KEY`) to a payload stored outside the application, so that it can
 * be checked before being trusted : `unserialize()` must never read something an attacker could write.
 */
class SignedPayload
{
    private const MAC_ALGORITHM = 'sha256';
    private const MAC_LENGTH = 64;

    // Distinct derivations : a payload signed without expiration can never be read as one with an expiration, nor the opposite
    private const PERMANENT_CONTEXT = 'cube-signed-payload';
    private const EXPIRING_CONTEXT = 'cube-signed-payload-expiring';

    public static function sign(string $payload, #[\SensitiveParameter] ?string $key = null, \DateTimeInterface|int|null $expiresAt = null): string
    {
        if (null === $expiresAt)
            return self::computeMac($payload, $key, self::PERMANENT_CONTEXT).$payload;
        $expiring = self::toTimestamp($expiresAt).'.'.$payload;

        return self::computeMac($expiring, $key, self::EXPIRING_CONTEXT).$expiring;
    }

    /**
     * @return ?string The original payload, `null` when the signature does not match or the payload expired
     */
    public static function verify(string $signed, #[\SensitiveParameter] ?string $key = null): ?string
    {
        $mac = substr($signed, 0, self::MAC_LENGTH);
        $content = substr($signed, self::MAC_LENGTH);

        if (hash_equals(self::computeMac($content, $key, self::PERMANENT_CONTEXT), $mac))
            return $content;
        if (!hash_equals(self::computeMac($content, $key, self::EXPIRING_CONTEXT), $mac))
            return null;
        [$expiresAt, $payload] = explode('.', $content, 2);

        return time() < (int) $expiresAt
            ? $payload
            : null;
    }

    private static function toTimestamp(\DateTimeInterface|int $moment): int
    {
        return $moment instanceof \DateTimeInterface
            ? $moment->getTimestamp()
            : $moment;
    }

    private static function computeMac(string $payload, #[\SensitiveParameter] ?string $key, string $context): string
    {
        $key = appKey($key);

        return hash_hmac(self::MAC_ALGORITHM, $payload, hash_hkdf('sha256', $key, 32, $context));
    }
}
