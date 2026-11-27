<?php

namespace Cube\Tests\Units\Web\HttpClient;

use Cube\Web\Helpers\CubeServer;
use Cube\Web\Http\Exceptions\NonPublicAddressException;
use Cube\Web\Http\HttpClient;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class HttpClientPublicOnlyTest extends TestCase
{
    protected static ?CubeServer $server = null;
    protected static int $port;

    public static function setUpBeforeClass(): void
    {
        self::$port = random_int(20001, 30000);
        self::$server = new CubeServer(self::$port, __DIR__.'/RedirectingServer');
    }

    public static function tearDownAfterClass(): void
    {
        self::$server = null;
    }

    public function testAClientIsNotPublicOnlyByDefault()
    {
        $this->assertFalse((new HttpClient())->isPublicOnly());
        $this->assertTrue((new HttpClient())->withPublicOnly()->isPublicOnly());
    }

    public function testAPrivateAddressIsRefusedBeforeAnyConnection()
    {
        $this->expectException(NonPublicAddressException::class);
        $this->expectExceptionMessage('[127.0.0.1]');

        (new HttpClient())->withPublicOnly()->get('http://127.0.0.1:'.self::$port.'/headers');
    }

    public function testAnIpv6LoopbackIsRefused()
    {
        $this->expectException(NonPublicAddressException::class);

        (new HttpClient())->withPublicOnly()->get('http://[::1]:'.self::$port.'/headers');
    }

    public function testAHostResolvingToAPrivateAddressIsRefused()
    {
        try {
            (new HttpClient())->withPublicOnly()->get('http://localhost:'.self::$port.'/headers');
            $this->fail('localhost was reached');
        } catch (NonPublicAddressException $exception) {
            $this->assertEquals('localhost', $exception->host);
            $this->assertFalse(filter_var($exception->address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE));
        }
    }

    /** Checking an address then letting curl resolve the host again lets a second DNS answer point elsewhere. */
    public function testCurlConnectsToTheCheckedAddress()
    {
        $response = $this->pinnedClient()->get('/created');

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testARedirectionToTheSameOriginIsCheckedAgain()
    {
        $headers = json_decode($this->pinnedClient()->get('/to-same-host')->getBody(), true);

        $this->assertIsArray($headers);
    }

    /** A redirection to another origin goes through a fresh client, which dropped the option. */
    public function testARedirectionToAnotherOriginStaysPublicOnly()
    {
        $this->expectException(NonPublicAddressException::class);
        $this->expectExceptionMessage('[127.0.0.1]');

        $this->pinnedClient()->get('/to-other-host');
    }

    public function testAnUnixSocketClientCannotBePublicOnly()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unix socket');

        (new ExposedHttpClient('http://localhost', '/tmp/cube.sock'))->withPublicOnly()->get('/');
    }

    public function testAnAsyncFetchCannotBePublicOnly()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fetchAsync()');

        (new HttpClient())->withPublicOnly()->getAsync('https://example.com/');
    }

    /**
     * `pinned.test` exists in no DNS : the request only reaches the local server through the pinned address.
     */
    protected function pinnedClient(): HttpClient
    {
        return (new class('http://pinned.test:'.self::$port) extends ExposedHttpClient {
            protected function resolveHost(string $host): array
            {
                return 'pinned.test' === $host ? ['127.0.0.1'] : parent::resolveHost($host);
            }

            protected function isPublicAddress(string $address): bool
            {
                return true;
            }
        })->withPublicOnly();
    }
}
