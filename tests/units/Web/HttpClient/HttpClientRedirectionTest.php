<?php

namespace Cube\Tests\Units\Web\HttpClient;

use Cube\Env\Logger\NullLogger;
use Cube\Utils\Text;
use Cube\Web\Helpers\CubeServer;
use Cube\Web\Http\HttpClient;
use Cube\Web\Http\Request;
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

    /** The cURL mutator, which may set CURLOPT_USERPWD or auth headers, was replayed on the foreign host. */
    public function testTheCurlMutatorStaysBehindOnARedirectionToAnotherHost()
    {
        $mutator = fn (\CurlHandle $handle) => curl_setopt($handle, CURLOPT_HTTPHEADER, ['X-Mutated: yes']);

        $sameHost = json_decode($this->client()->fetch(new Request('GET', '/to-same-host'), curlMutator: $mutator)->getBody(), true);
        $otherHost = json_decode($this->client()->fetch(new Request('GET', '/to-other-host'), curlMutator: $mutator)->getBody(), true);

        $this->assertEquals('yes', $sameHost['x-mutated'] ?? null);
        $this->assertArrayNotHasKey('x-mutated', $otherHost);
    }

    /** Every fetch logged its full URL, query string included, whatever the log flags asked for. */
    public function testTheUrlParametersAreOnlyLoggedWhenAsked()
    {
        $logger = new class extends NullLogger {
            public string $written = '';

            public function log($level, null|string|\Stringable $message, array $context = []): void
            {
                $this->written .= Text::interpolate($message, $context)."\n";
            }
        };

        $this->client()->fetch(new Request('GET', '/query', ['api_key' => 'q-123']), $logger);

        $this->assertStringNotContainsString('q-123', $logger->written);
    }

    /** With a base URL, the Location was joined to it, and its query string dropped. */
    public function testARelativeLocationKeepsItsQueryString()
    {
        $this->assertEquals('page 2', $this->client()->get('/with-query')->getBody());
    }
}
