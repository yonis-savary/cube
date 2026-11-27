<?php

namespace Cube\Tests\Units\Security\RateLimiting;

use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Env\Cache\RedisCache\RedisCache;
use Cube\Security\RateLimiting\Exceptions\RateLimitTimeoutException;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Tests\Units\Security\Classes\FakeClock;
use Cube\Tests\Units\Security\Classes\TenPerMinuteRateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class RateLimiterTest extends TestCase
{
    use HasTemporaryStorage;

    protected FakeClock $clock;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('rate-limiter-test-');
        // The start of a window : a test moves the clock from there
        $this->clock = new FakeClock(1_800_000_000);
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    protected function limiter(string $driver = 'disk'): TenPerMinuteRateLimiter
    {
        $cacheDriver = 'redis' === $driver
            ? new RedisCache(uniqid('cube-test-'), '127.0.0.1', 6379)
            : new LocalDiskCache($this->storage);

        return new TenPerMinuteRateLimiter(new Cache(new CacheConfiguration($cacheDriver)), $this->clock);
    }

    #[DataProvider('getDrivers')]
    public function testAttemptsAreRefusedPastTheLimit(string $driver)
    {
        $limiter = $this->limiter($driver);

        for ($i = 0; $i < 10; ++$i)
            $this->assertTrue($limiter->attempt('client')->allowed);

        $refused = $limiter->attempt('client');
        $this->assertFalse($refused->allowed);
        $this->assertSame(0, $refused->remaining);
        $this->assertTrue($limiter->attempt('another-client')->allowed);
    }

    public static function getDrivers(): array
    {
        return ['local disk cache' => ['disk'], 'redis cache' => ['redis']];
    }

    public function testRemainingCountsDown()
    {
        $limiter = $this->limiter();

        $this->assertSame(9, $limiter->attempt('client')->remaining);
        $this->assertSame(6, $limiter->attempt('client', 3)->remaining);
    }

    public function testARefusedAttemptCountsNothing()
    {
        $limiter = $this->limiter();
        $limiter->attempt('client', 8);

        $this->assertFalse($limiter->attempt('client', 5)->allowed);
        $this->assertTrue($limiter->attempt('client', 2)->allowed);
    }

    /** A fixed window lets 2 × the limit through around its boundary : the sliding one still counts the previous window. */
    public function testThePreviousWindowStillWeighsAtTheStartOfTheNext()
    {
        $limiter = $this->limiter();

        $this->clock->now += 50;
        $limiter->attempt('client', 10);

        // 10 % into the next window, the previous one still weighs 90 % of its 10 attempts
        $this->clock->now += 16;
        $this->assertTrue($limiter->attempt('client')->allowed);
        $this->assertFalse($limiter->attempt('client')->allowed);
    }

    public function testRetryAfterIsTheMomentTheAttemptFits()
    {
        $limiter = $this->limiter();
        $this->clock->now += 30;
        $limiter->attempt('client', 10);

        $retryAfter = $limiter->attempt('client')->retryAfter;
        // Full until the end of this window, then the previous one has to weigh 9 at most : 10 % into the next
        $this->assertEqualsWithDelta(36, $retryAfter, 0.001);

        $this->clock->now += $retryAfter - 0.5;
        $this->assertFalse($limiter->attempt('client')->allowed);
        $this->clock->now += 0.5;
        $this->assertTrue($limiter->attempt('client')->allowed);
    }

    public function testAvailableInDoesNotCount()
    {
        $limiter = $this->limiter();
        $limiter->attempt('client', 9);

        $this->assertEquals(0, $limiter->availableIn('client'));
        $this->assertEquals(0, $limiter->availableIn('client'));
        $this->assertGreaterThan(0, $limiter->availableIn('client', 2));
        $this->assertTrue($limiter->attempt('client')->allowed);
    }

    public function testClearForgetsTheKey()
    {
        $limiter = $this->limiter();
        $limiter->attempt('client', 10);

        $limiter->clear('client');

        $this->assertTrue($limiter->attempt('client', 10)->allowed);
    }

    public function testAnAttemptCostingMoreThanTheLimitIsAMistake()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->limiter()->attempt('client', 11);
    }

    public function testWaitSleepsUntilTheAttemptFits()
    {
        $limiter = $this->limiter();
        $limiter->attempt('client', 10);

        $this->assertTrue($limiter->wait('client')->allowed);
        $this->assertCount(1, $this->clock->sleeps);
        $this->assertEqualsWithDelta(66, $this->clock->sleeps[0], 0.001);
    }

    public function testWaitGivesUpWhenTheTimeoutComesFirst()
    {
        $limiter = $this->limiter();
        $limiter->attempt('client', 10);

        try {
            $limiter->wait('client', timeout: 30);
            $this->fail('wait() should have given up');
        } catch (RateLimitTimeoutException) {
            $this->assertSame([], $this->clock->sleeps);
        }
    }

    public function testTheHeadersDescribeThePolicy()
    {
        $limiter = $this->limiter();
        $this->clock->now += 15;

        $this->assertEquals([
            'RateLimit-Policy' => '"ten-per-minute-rate-limiter";q=10;w=60',
            'RateLimit' => '"ten-per-minute-rate-limiter";r=9;t=45',
        ], $limiter->attempt('client')->toHeaders());

        $limiter->attempt('client', 9);
        $this->assertEquals('51', $limiter->attempt('client')->toHeaders()['Retry-After'] ?? null);
    }
}
