<?php

namespace Cube\Web\Http;

class IpAddress
{
    // RFC 6052 : the well-known NAT64 prefix carries an IPv4 address in its last 32 bits
    private const NAT64_PREFIX = '64:ff9b::';

    public static function isPublic(string $address): bool
    {
        if (false === filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE))
            return false;
        if (self::isMulticast($address))
            return false;
        if (null !== $embedded = self::embeddedNat64Address($address))
            return self::isPublic($embedded);
        return true;
    }

    // FILTER_FLAG_GLOBAL_RANGE lets multicast through : 224.0.0.0/4 and ff00::/8
    private static function isMulticast(string $address): bool
    {
        $bytes = inet_pton($address);

        return 4 === strlen($bytes)
            ? (ord($bytes[0]) & 0xF0) === 0xE0
            : 0xFF === ord($bytes[0]);
    }

    private static function embeddedNat64Address(string $address): ?string
    {
        $bytes = inet_pton($address);
        if (16 !== strlen($bytes) || substr($bytes, 0, 12) !== substr(inet_pton(self::NAT64_PREFIX), 0, 12))
            return null;
        return inet_ntop(substr($bytes, 12));
    }
}
