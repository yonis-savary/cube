<?php

namespace Cube\Security\RateLimiting;

class RateLimit
{
    /**
     * @param float $retryAfter seconds before the same attempt is allowed, 0 when this one was
     * @param float $resetsIn   seconds before the current window ends
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly string $policy,
        public readonly int $limit,
        public readonly int $window,
        public readonly int $remaining,
        public readonly float $retryAfter,
        public readonly float $resetsIn,
    ) {}

    /**
     * The RateLimit-Policy and RateLimit headers (draft-ietf-httpapi-ratelimit-headers), and Retry-After when refused.
     *
     * @return array<string,string>
     */
    public function toHeaders(): array
    {
        $headers = [
            'RateLimit-Policy' => "\"{$this->policy}\";q={$this->limit};w={$this->window}",
            'RateLimit' => "\"{$this->policy}\";r={$this->remaining};t=".(int) ceil($this->allowed ? $this->resetsIn : $this->retryAfter),
        ];

        if (!$this->allowed)
            $headers['Retry-After'] = (string) (int) ceil($this->retryAfter);

        return $headers;
    }
}
