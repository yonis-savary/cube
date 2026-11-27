<?php

namespace Cube\Tests\Units\Web\HttpClient;

use Cube\Web\Http\IpAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class IpAddressTest extends TestCase
{
    #[DataProvider('getAddresses')]
    public function testAnAddressIsPublicOnlyWhenGloballyRoutable(string $address, bool $public)
    {
        $this->assertSame($public, IpAddress::isPublic($address));
    }

    public static function getAddresses(): array
    {
        return [
            'public IPv4' => ['93.184.215.14', true],
            'public IPv6' => ['2606:2800:21f:cb07:6820:80da:af6b:8b2c', true],
            'loopback' => ['127.0.0.1', false],
            'private 10/8' => ['10.0.0.1', false],
            'private 172.16/12' => ['172.16.0.1', false],
            'private 192.168/16' => ['192.168.1.1', false],
            'cloud metadata' => ['169.254.169.254', false],
            'carrier-grade NAT' => ['100.64.0.1', false],
            'unspecified' => ['0.0.0.0', false],
            'documentation' => ['192.0.2.1', false],
            'IPv4 multicast' => ['224.0.0.1', false],
            'IPv6 loopback' => ['::1', false],
            'IPv6 link-local' => ['fe80::1', false],
            'IPv6 unique local' => ['fd00::1', false],
            'IPv6 multicast' => ['ff02::1', false],
            'IPv4-mapped loopback' => ['::ffff:127.0.0.1', false],
            'NAT64 of a loopback' => ['64:ff9b::7f00:1', false],
            'NAT64 of a public address' => ['64:ff9b::5db8:d70e', true],
        ];
    }

    #[DataProvider('getRanges')]
    public function testAnAddressIsInARangeWhenItSharesItsPrefix(string $address, string $range, bool $inRange)
    {
        $this->assertTrue(IpAddress::isRange($range));
        $this->assertSame($inRange, IpAddress::isInRange($address, $range));
    }

    public static function getRanges(): array
    {
        return [
            'same address' => ['10.0.0.1', '10.0.0.1', true],
            'other address' => ['10.0.0.2', '10.0.0.1', false],
            'inside /8' => ['10.200.3.4', '10.0.0.0/8', true],
            'outside /8' => ['11.0.0.1', '10.0.0.0/8', false],
            'inside /12, on a partial byte' => ['172.31.255.255', '172.16.0.0/12', true],
            'outside /12, on a partial byte' => ['172.32.0.1', '172.16.0.0/12', false],
            'everything in /0' => ['93.184.215.14', '0.0.0.0/0', true],
            'inside an IPv6 /8' => ['fd12:3456::1', 'fd00::/8', true],
            'outside an IPv6 /8' => ['fe80::1', 'fd00::/8', false],
            'IPv4 against an IPv6 range' => ['10.0.0.1', '::/0', false],
            'not an address' => ['unknown', '0.0.0.0/0', false],
        ];
    }

    #[DataProvider('getInvalidRanges')]
    public function testAnInvalidRangeIsRejected(string $range)
    {
        $this->assertFalse(IpAddress::isRange($range));
    }

    public static function getInvalidRanges(): array
    {
        return [
            'hostname' => ['proxy.internal'],
            'IPv4 prefix over 32' => ['10.0.0.0/33'],
            'IPv6 prefix over 128' => ['fd00::/129'],
            'negative prefix' => ['10.0.0.0/-1'],
            'empty prefix' => ['10.0.0.0/'],
        ];
    }
}
