<?php

namespace Cube\Tests\Units\Security\RateLimiting;

use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Env\Configuration;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Tests\Units\Security\Classes\TwoPerMinuteThrottle;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use Cube\Web\Router\RouterConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ThrottleTest extends TestCase
{
    use HasTemporaryStorage;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('throttle-test-');
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    protected function handle(Request $request): Response
    {
        return (new Cache(new CacheConfiguration(new LocalDiskCache($this->storage))))->asGlobalInstance(
            fn () => TwoPerMinuteThrottle::handle($request, fn () => Response::ok('Hello'))
        );
    }

    public function testARequestPastTheLimitIsAnswered429()
    {
        $request = new Request(ip: '93.184.215.14');

        $first = $this->handle($request);
        $this->assertSame(StatusCode::OK, $first->getStatusCode());
        $this->assertStringContainsString('r=1', $first->getHeader('RateLimit'));
        $this->assertStringContainsString('q=2;w=60', $first->getHeader('RateLimit-Policy'));

        $this->handle($request);
        $refused = $this->handle($request);
        $this->assertSame(StatusCode::TOO_MANY_REQUESTS, $refused->getStatusCode());
        $this->assertNotNull($refused->getHeader('Retry-After'));

        $this->assertSame(StatusCode::OK, $this->handle(new Request(ip: '2606:2800:21f:cb07:6820:80da:af6b:8b2c'))->getStatusCode());
    }

    public function testClientsBehindATrustedProxyAreCountedApart()
    {
        (new Configuration(new RouterConfiguration(trustedProxies: ['10.0.0.0/8'])))->asGlobalInstance(function () {
            $this->handle(new Request(headers: ['X-Forwarded-For' => '93.184.215.14'], ip: '10.0.0.5'));
            $this->handle(new Request(headers: ['X-Forwarded-For' => '93.184.215.14'], ip: '10.0.0.5'));

            $other = $this->handle(new Request(headers: ['X-Forwarded-For' => '93.184.215.15'], ip: '10.0.0.5'));
            $this->assertSame(StatusCode::OK, $other->getStatusCode());
        });
    }
}
