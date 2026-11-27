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
}
