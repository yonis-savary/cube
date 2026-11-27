<?php

namespace Cube\Utils;

use function Cube\appKey;

class Identifier
{
    public const MINIMUM_RANDOM_BYTES = 16;

    // RFC 9562 §5.1 : 100-nanosecond intervals between 1582-10-15 (Gregorian epoch) and 1970-01-01
    private const GREGORIAN_TO_UNIX_TICKS = 0x01B21DD213814000;

    private static ?int $uuid6Process = null;
    private static int $lastUuid6Timestamp = 0;
    private static int $uuid6ClockSequence = 0;
    private static int $uuid6Node = 0;

    public static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public static function uuid6(): string
    {
        // A forked child inherits the parent state : both would generate the same identifiers within one tick
        if (self::$uuid6Process !== getmypid())
            self::resetUuid6State();

        $timestamp = max(self::gregorianTicks(), self::$lastUuid6Timestamp + 1);
        self::$lastUuid6Timestamp = $timestamp;

        return sprintf(
            '%08x-%04x-%04x-%04x-%012x',
            ($timestamp >> 28) & 0xFFFFFFFF,
            ($timestamp >> 12) & 0xFFFF,
            0x6000 | ($timestamp & 0x0FFF),
            0x8000 | self::$uuid6ClockSequence,
            self::$uuid6Node
        );
    }

    public static function prefixed(string $prefix, int $bytes = 32): string
    {
        if ($bytes < self::MINIMUM_RANDOM_BYTES)
            throw new \InvalidArgumentException('A prefixed identifier needs at least '.self::MINIMUM_RANDOM_BYTES." random bytes to stay unguessable, got [{$bytes}]");

        return $prefix.rtrim(strtr($bytes |> random_bytes(...) |> base64_encode(...), '+/', '-_'), '=');
    }

    public static function hash(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] ?string $key = null): string
    {
        return hash_hmac('sha256', $secret, hash_hkdf('sha256', appKey($key), 32, 'cube-identifier-hash'));
    }

    public static function matches(#[\SensitiveParameter] string $secret, string $hash, #[\SensitiveParameter] ?string $key = null): bool
    {
        return hash_equals(self::hash($secret, $key), $hash);
    }

    /**
     * Less secure than `hash()` : a stolen hash can be checked against guesses without the application key.
     */
    public static function hash256(#[\SensitiveParameter] string $secret): string
    {
        return hash('sha256', $secret);
    }

    public static function matches256(#[\SensitiveParameter] string $secret, string $hash): bool
    {
        return hash_equals(self::hash256($secret), $hash);
    }

    private static function resetUuid6State(): void
    {
        self::$uuid6Process = getmypid();
        self::$lastUuid6Timestamp = 0;
        self::$uuid6ClockSequence = random_int(0, 0x3FFF);
        // RFC 9562 §6.10 : a random node sets the multicast bit, so it never collides with a hardware address
        self::$uuid6Node = random_int(0, 0xFFFFFFFFFFFF) | 0x010000000000;
    }

    private static function gregorianTicks(): int
    {
        // microtime(true) is a float : at 100 ns precision it loses the last digits
        [$fraction, $seconds] = explode(' ', microtime());

        return (int) $seconds * 10_000_000 + (int) substr($fraction, 2, 7) + self::GREGORIAN_TO_UNIX_TICKS;
    }
}
