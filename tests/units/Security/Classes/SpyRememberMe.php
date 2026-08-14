<?php

namespace Cube\Tests\Units\Security\Classes;

use Cube\Security\RememberMe;

/**
 * A cookie cannot leave a CLI process : this catches what would have been sent instead.
 */
class SpyRememberMe extends RememberMe
{
    /** @var array<int,array{value:string,expiresAt:int}> */
    public array $sentCookies = [];

    public function lastCookie(): ?array
    {
        return $this->sentCookies ? end($this->sentCookies) : null;
    }

    protected function sendCookie(string $value, int $expiresAt): void
    {
        $this->sentCookies[] = ['value' => $value, 'expiresAt' => $expiresAt];
    }
}
