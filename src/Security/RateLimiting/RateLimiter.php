<?php

namespace Cube\Security\RateLimiting;

use Cube\Env\Cache;
use Cube\Security\RateLimiting\Exceptions\RateLimitTimeoutException;

/**
 * A rate limiting policy : at most `limit()` units per `window()` seconds for each key, counted over a sliding
 * window. Extend it once per policy, then `attempt()` before the work it limits, or `wait()` for a free slot.
 */
abstract class RateLimiter
{
    abstract protected function limit(): int;

    abstract protected function window(): int;

    public function name(): string
    {
        $shortName = (new \ReflectionClass($this))->getShortName();

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $shortName));
    }

    protected function cache(): Cache
    {
        return Cache::getInstance()->child('rate-limiter-'.static::class);
    }

    protected function now(): float
    {
        return microtime(true);
    }

    protected function sleep(float $seconds): void
    {
        usleep((int) ceil($seconds * 1_000_000));
    }

    /**
     * Count `$cost` units for the key, unless they would exceed the limit : a refused attempt counts nothing.
     */
    public function attempt(string $key, int $cost = 1): RateLimit
    {
        $this->assertFits($cost);
        $limit = $this->limit();
        $now = $this->now();
        [$currentKey, $previousKey] = $this->windowKeys($key, $now);
        $cache = $this->cache();

        $current = $cache->increment($currentKey, $cost, 2 * $this->window());
        $previous = $this->countOf($previousKey);
        $estimated = $this->estimate($current, $previous, $now);

        if ($estimated <= $limit)
            return $this->rateLimit(true, (int) floor($limit - $estimated), 0, $now);
        $current = $cache->increment($currentKey, -$cost, 2 * $this->window());

        return $this->rateLimit(false, max(0, (int) floor($limit - $this->estimate($current, $previous, $now))), $this->retryAfter($current, $previous, $now, $cost), $now);
    }

    /**
     * Block until `$cost` units fit in the limit, then count them.
     *
     * @param ?float $timeout seconds ; past it, throws instead of waiting longer
     */
    public function wait(string $key, int $cost = 1, ?float $timeout = null): RateLimit
    {
        $deadline = null === $timeout ? null : $this->now() + $timeout;

        while (!($rateLimit = $this->attempt($key, $cost))->allowed) {
            if (null !== $deadline && $this->now() + $rateLimit->retryAfter > $deadline)
                throw new RateLimitTimeoutException('Rate limiter ['.static::class."] has no room for [{$key}] before the {$timeout} seconds timeout");
            $this->sleep($rateLimit->retryAfter);
        }

        return $rateLimit;
    }

    /**
     * @return float seconds before an attempt costing `$cost` is allowed, 0 when it already is
     */
    public function availableIn(string $key, int $cost = 1): float
    {
        $this->assertFits($cost);
        $now = $this->now();
        [$currentKey, $previousKey] = $this->windowKeys($key, $now);
        $current = $this->countOf($currentKey);
        $previous = $this->countOf($previousKey);

        return $this->estimate($current, $previous, $now) + $cost <= $this->limit()
            ? 0
            : $this->retryAfter($current, $previous, $now, $cost);
    }

    public function clear(string $key): void
    {
        $cache = $this->cache();
        foreach ($this->windowKeys($key, $this->now()) as $windowKey)
            $cache->delete($windowKey);
    }

    protected function assertFits(int $cost): void
    {
        if ($cost > $limit = $this->limit())
            throw new \InvalidArgumentException("An attempt costing [{$cost}] can never fit in the limit [{$limit}] of rate limiter [".static::class.']');
    }

    /**
     * @return array{string,string} the counters of the current window and of the previous one
     */
    protected function windowKeys(string $key, float $now): array
    {
        $start = $this->windowStart($now);

        return ["{$key}-{$start}", "{$key}-".($start - $this->window())];
    }

    protected function windowStart(float $now): int
    {
        return (int) (floor($now / $this->window()) * $this->window());
    }

    protected function countOf(string $windowKey): int
    {
        return $this->cache()->increment($windowKey, 0, 2 * $this->window());
    }

    // Sliding window approximation : the previous window weighs the share of it the sliding window still covers
    protected function estimate(int $current, int $previous, float $now): float
    {
        return $previous * (1 - $this->elapsedShare($now)) + $current;
    }

    protected function elapsedShare(float $now): float
    {
        return ($now - $this->windowStart($now)) / $this->window();
    }

    protected function retryAfter(int $current, int $previous, float $now, int $cost): float
    {
        $limit = $this->limit();
        $window = $this->window();
        $elapsedShare = $this->elapsedShare($now);

        // Fits in this window once the previous one weighs little enough
        if ($current + $cost <= $limit)
            return (1 - ($limit - $current - $cost) / $previous - $elapsedShare) * $window;
        // Otherwise in the next one, where the current window becomes the previous
        return (1 - $elapsedShare) * $window + max(0, 1 - ($limit - $cost) / $current) * $window;
    }

    protected function rateLimit(bool $allowed, int $remaining, float $retryAfter, float $now): RateLimit
    {
        return new RateLimit(
            $allowed,
            $this->name(),
            $this->limit(),
            $this->window(),
            $remaining,
            $retryAfter,
            (1 - $this->elapsedShare($now)) * $this->window()
        );
    }
}
