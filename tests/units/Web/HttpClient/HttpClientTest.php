<?php

namespace Cube\Tests\Units\Web\HttpClient;

use Cube\Env\Logger\NullLogger;
use Cube\Utils\Text;
use Cube\Web\Http\HttpMockServer;
use Cube\Web\Http\MockServers;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
class HttpClientTest extends TestCase
{
    protected function tearDown(): void
    {
        MockServers::removeInstance();
    }

    /**
     * A header value is separated from its name by ": ", keeping that space made every value
     * start with one — `getHeader('content-type')` answered " application/json".
     */
    public function testHeaderValuesAreTrimmed()
    {
        $client = new ExposedHttpClient();

        $headers = $client->publicParseHeaders("HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nX-Total: 42\r\n", true);

        $this->assertEquals('application/json', $headers['content-type']);
        $this->assertEquals('42', $headers['x-total']);
    }

    public function testHeaderNamesAreLeftAloneWhenNotAskedToLowercase()
    {
        $client = new ExposedHttpClient();

        $headers = $client->publicParseHeaders("Content-Type: text/html\r\n");

        $this->assertArrayHasKey('Content-Type', $headers);
    }

    public function testABaseUrlIsPrependedToTheRequestPath()
    {
        $client = new ExposedHttpClient('http://some-api.org/v1');

        $this->assertEquals('http://some-api.org/v1/products', $client->publicPath(new Request('GET', '/products')));
    }

    public function testWithoutABaseUrlThePathIsUsedAsIs()
    {
        $client = new ExposedHttpClient();

        $this->assertEquals('http://some-api.org/products', $client->publicPath(new Request('GET', 'http://some-api.org/products')));
    }

    /** The Request constructor normalizes outgoing requests the same way it does incoming ones. */
    public function testOutgoingParametersAndPathAreNormalized()
    {
        $client = new ExposedHttpClient();

        $handle = $client->toCurlHandle(new Request('GET', 'http://some-api.org/items/', ['status' => 'off', 'deleted' => 'null']));

        $this->assertEquals('http://some-api.org/items?status=0', curl_getinfo($handle, CURLINFO_EFFECTIVE_URL));
    }

    /**
     * baseUserAgent() and baseLogger() are what a subclass declares for its own requests, and
     * they were resolved after the handle was built — which is the only thing that carries them.
     */
    public function testTheDefaultsASubclassDeclaresReachTheHandle()
    {
        // Nothing listens on port 1, curl gives up at once and the handle is already built
        $client = new ExposedHttpClient('http://127.0.0.1:1/');

        try {
            $client->fetch(new Request('GET', '/ping'), timeout: 1, userAgent: null);
        } catch (\RuntimeException $_) {
        }

        $this->assertEquals('CubeTestAgent/1.0', $client->handleArguments['userAgent']);
        $this->assertNotNull($client->handleArguments['logger']);
        $this->assertEquals(1, $client->baseUserAgentCalls);
        $this->assertEquals(1, $client->baseLoggerCalls);
    }

    public function testNothingIsBuiltWhenAMockAnswers()
    {
        $client = new ExposedHttpClient('http://some-math-api/');
        $client->setMockServer(HttpMockServer::fromArray(['/ping' => Response::ok('pong')]));

        $response = $client->get('/ping');

        $this->assertEquals('pong', $response->getBody());
        $this->assertEquals([], $client->handleArguments);
    }

    public function testAMockGivenToTheClientIsUsed()
    {
        $client = new ExposedHttpClient('http://unreachable.invalid/');
        $client->setMockServer(HttpMockServer::fromArray(['/products' => Response::json([['name' => 'screen']])]));

        $this->assertEquals([['name' => 'screen']], $client->get('/products')->getJSON());
    }

    public function testAMockRegisteredForTheClassIsUsed()
    {
        MockServers::getInstance()->set(
            ExposedHttpClient::class,
            HttpMockServer::fromArray(['/products' => Response::ok('from the register')])
        );

        $this->assertEquals('from the register', (new ExposedHttpClient('http://unreachable.invalid/'))->get('/products')->getBody());
    }

    public function testAsyncFetchIsShortCircuitedByAMock()
    {
        $client = new ExposedHttpClient('http://unreachable.invalid/');
        $client->setMockServer(HttpMockServer::fromArray(['/events' => Response::ok()]));

        $this->assertTrue($client->postJsonAsync('/events', ['name' => 'clicked']));
    }

    public function testLastDurationIsKnownBeforeAnyFetch()
    {
        $this->assertEquals(0.0, (new ExposedHttpClient())->lastDuration());
    }

    public function testAMockServerAnswersEveryVerb()
    {
        $client = new ExposedHttpClient('http://some-api/');
        $client->setMockServer(HttpMockServer::fromRoutes(
            new \Cube\Web\Router\Route('/products', fn (Request $request) => Response::ok($request->getMethod()))
        ));

        foreach (['get', 'post', 'put', 'patch', 'delete'] as $verb) {
            $this->assertEquals(strtoupper($verb), $client->{$verb}('/products')->getBody());
        }
    }

    /** fetchAsync() used to send the request headers only, leaving baseHeaders() behind. */
    public function testAsyncFetchSendsTheBaseHeaders()
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        $client = new class("http://127.0.0.1:{$port}") extends ExposedHttpClient {
            public function baseHeaders(): array
            {
                return ['X-Api-Token' => 'abc'];
            }
        };

        $this->assertTrue($client->postJsonAsync('/events', ['name' => 'created']));

        $connection = stream_socket_accept($server, 1);
        $this->assertStringContainsString('X-Api-Token: abc', stream_get_contents($connection));

        fclose($connection);
        fclose($server);
    }

    /** A client built for one API sent its credentials to any absolute URL it was given. */
    public function testBaseValuesStayOnTheBaseOrigin()
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        $client = new class('http://127.0.0.1:1') extends ExposedHttpClient {
            public function baseHeaders(): array
            {
                return ['X-Api-Token' => 'abc'];
            }

            public function baseURLParameters(): array
            {
                return ['api_key' => 'abc'];
            }
        };

        $this->assertTrue($client->postJsonAsync("http://127.0.0.1:{$port}/events", ['name' => 'created']));

        $connection = stream_socket_accept($server, 1);
        $received = stream_get_contents($connection);
        $this->assertStringNotContainsString('abc', $received);

        fclose($connection);
        fclose($server);
    }

    /** Only the host was compared : a redirection to another port of it kept the credentials. */
    public function testAnOriginIsMadeOfSchemeHostAndPort()
    {
        $this->assertSame(ExposedHttpClient::publicOriginOf('https://api.example.org/a'), ExposedHttpClient::publicOriginOf('https://API.example.org:443/b'));
        $this->assertNotSame(ExposedHttpClient::publicOriginOf('http://api.example.org/a'), ExposedHttpClient::publicOriginOf('http://api.example.org:8080/a'));
        $this->assertNotSame(ExposedHttpClient::publicOriginOf('https://api.example.org/a'), ExposedHttpClient::publicOriginOf('http://api.example.org/a'));
    }

    /** cURL speaks file://, gopher:// or dict:// as well : a URL taken from a user could read files or talk to Redis. */
    public function testOnlyHttpIsFetched()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Curl error 1:/');

        (new ExposedHttpClient())->fetch(new Request('GET', 'file:///etc/hostname'), userAgent: null);
    }

    /** Every fetch logged its headers, so Authorization and API keys piled up in Storage/Logs. */
    public function testCredentialsAreRedactedFromTheLogs()
    {
        $logger = new class extends NullLogger {
            public string $written = '';

            public function log($level, null|string|\Stringable $message, array $context = []): void
            {
                $this->written .= Text::interpolate($message, $context)."\n";
            }
        };

        $client = new class('http://localhost') extends ExposedHttpClient {
            public function baseHeaders(): array
            {
                return ['X-Api-Key' => 'k-123', 'X-Broadcast-Secret' => 's-456'];
            }
        };

        $client->toCurlHandle(new Request('GET', '/', headers: ['Authorization' => 'Bearer t-789', 'Accept' => 'text/plain']), logger: $logger);

        $this->assertStringContainsString('text/plain', $logger->written);
        foreach (['k-123', 's-456', 't-789'] as $secret)
            $this->assertStringNotContainsString($secret, $logger->written);
    }

    /** fetchAsync() writes on a raw socket : a line break in a value used to forge another header. */
    public function testAsyncFetchStripsLineBreaksFromHeaders()
    {
        [$server, $port] = $this->listen();

        (new ExposedHttpClient("http://127.0.0.1:{$port}"))->postJsonAsync('/events', [], ['X-Trace' => "abc\r\nX-Injected: 1"]);

        $connection = stream_socket_accept($server, 1);
        $raw = stream_get_contents($connection);

        $this->assertStringContainsString('x-trace: abcX-Injected: 1', $raw);
        $this->assertStringNotContainsString("\r\nX-Injected", $raw);

        fclose($connection);
        fclose($server);
    }

    /** TLS was picked from the port : https on any port but 443 went out in clear text. */
    public function testAsyncFetchUsesTlsForHttpsOnAnyPort()
    {
        [$server, $port] = $this->listen();

        // Nothing answers the handshake : it times out, and fsockopen() warns about it
        $timeout = ini_set('default_socket_timeout', '1');
        set_error_handler(fn () => true);
        try {
            (new ExposedHttpClient("https://127.0.0.1:{$port}"))->postJsonAsync('/events', ['name' => 'created']);
        } finally {
            restore_error_handler();
            ini_set('default_socket_timeout', $timeout);
        }

        $connection = stream_socket_accept($server, 1);
        $firstByte = fread($connection, 1);

        // 0x16 opens a TLS handshake record, a clear request would start with "POST"
        $this->assertEquals("\x16", $firstByte);

        fclose($connection);
        fclose($server);
    }

    /** @return array{resource,int} */
    protected function listen(): array
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        return [$server, $port];
    }

    public function testAUnixSocketClientNeedsNoHost()
    {
        $client = new ExposedHttpClient(socket: '/run/some-app.sock');

        $this->assertEquals('http://localhost/products', $client->publicPath(new Request('GET', '/products')));
    }

    public function testFetchGoesThroughTheUnixSocket()
    {
        $socket = sys_get_temp_dir().'/cube-http-client-'.uniqid().'.sock';
        $server = new Process(['php', __DIR__.'/UnixSocketServer/answer-once.php', $socket]);
        $server->start();

        $deadline = microtime(true) + 5;
        while (!file_exists($socket) && microtime(true) < $deadline)
            usleep(10_000);

        $response = (new ExposedHttpClient(socket: $socket))->get('/internal/ping', ['verbose' => 1]);
        $server->wait();

        $this->assertEquals('GET /internal/ping?verbose=1 HTTP/1.1', $response->getBody());
    }

    public function testAsyncFetchGoesThroughTheUnixSocket()
    {
        $socket = sys_get_temp_dir().'/cube-http-client-'.uniqid().'.sock';
        $server = stream_socket_server("unix://{$socket}");

        $this->assertTrue((new ExposedHttpClient(socket: $socket))->postJsonAsync('/events', ['name' => 'created']));

        $connection = stream_socket_accept($server, 1);
        $this->assertStringStartsWith('POST /events HTTP/1.1', stream_get_contents($connection));

        fclose($connection);
        fclose($server);
        unlink($socket);
    }
}
