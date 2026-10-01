<?php

namespace Cube\Tests\Units\Web\HttpClient;

use Cube\Web\Helpers\CubeServer;
use Cube\Web\Http\HttpClient;
use PHPUnit\Framework\TestCase;

class HttpClientRedirectionTest extends TestCase
{
    protected static ?CubeServer $server = null;
    protected static int $port;

    public static function setUpBeforeClass(): void
    {
        self::$port = random_int(10001, 20000);
        self::$server = new CubeServer(self::$port, __DIR__.'/RedirectingServer');
    }

    public static function tearDownAfterClass(): void
    {
        self::$server = null;
    }

    protected function client(): HttpClient
    {
        return new class('http://localhost:'.self::$port) extends ExposedHttpClient {
            public function baseHeaders(): array
            {
                return ['X-Api-Token' => 'secret'];
            }
        };
    }

    /** Any response carrying a Location was followed, a 201 Created included. */
    public function testOnlyARedirectionStatusIsFollowed()
    {
        $response = $this->client()->get('/created');

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('answered /created', $response->getBody());
    }

    /** A redirection loop recursed until the stack ran out. */
    public function testARedirectionLoopIsStopped()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Stopped after 10 redirections/');

        $this->client()->get('/loop');
    }

    /** Any scheme was followed, file:// included. */
    public function testOnlyHttpRedirectionsAreFollowed()
    {
        $response = $this->client()->get('/file');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('answered /file', $response->getBody());
    }

    public function testBaseHeadersFollowARedirectionToTheSameHost()
    {
        $headers = json_decode($this->client()->get('/to-same-host')->getBody(), true);

        $this->assertEquals('secret', $headers['x-api-token'] ?? null);
    }

    /** The base headers, often credentials, were sent to whatever host the Location named. */
    public function testBaseHeadersStayBehindOnARedirectionToAnotherHost()
    {
        $headers = json_decode($this->client()->get('/to-other-host')->getBody(), true);

        $this->assertIsArray($headers);
        $this->assertArrayNotHasKey('x-api-token', $headers);
    }

    /** With a base URL, the Location was joined to it, and its query string dropped. */
    public function testARelativeLocationKeepsItsQueryString()
    {
        $this->assertEquals('page 2', $this->client()->get('/with-query')->getBody());
    }
}
