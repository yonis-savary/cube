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

    /**
     * Whether `$range` is an IP address, or a network written in CIDR notation as `10.0.0.0/8` or `fd00::/8`.
     */
    public static function isRange(string $range): bool
    {
        [$network, $prefixLength] = explode('/', $range, 2) + [1 => null];
        if (false === filter_var($network, FILTER_VALIDATE_IP))
            return false;
        if (null === $prefixLength)
            return true;
        return preg_match('/^\d{1,3}$/', $prefixLength) && (int) $prefixLength <= 8 * ($network |> inet_pton(...) |> strlen(...));
    }

    /**
     * @param string $range a valid `isRange()`
     */
    public static function isInRange(string $address, string $range): bool
    {
        if (false === filter_var($address, FILTER_VALIDATE_IP))
            return false;
        [$network, $prefixLength] = explode('/', $range, 2) + [1 => null];
        $addressBytes = inet_pton($address);
        $networkBytes = inet_pton($network);
        if (strlen($addressBytes) !== strlen($networkBytes))
            return false;
        $prefixLength = null === $prefixLength ? 8 * strlen($networkBytes) : (int) $prefixLength;
        $wholeBytes = intdiv($prefixLength, 8);
        if (substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes))
            return false;
        if (0 === $remainingBits = $prefixLength % 8)
            return true;
        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
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
        return substr($bytes, 12) |> inet_ntop(...);
    }
}
