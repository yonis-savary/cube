<?php

namespace Cube\Tests\Units\Security\Classes;

class FakeClock
{
    /** @var float[] */
    public array $sleeps = [];

    public function __construct(
        public float $now = 1_800_000_000
    ) {}

    public function sleep(float $seconds): void
    {
        $this->sleeps[] = $seconds;
        $this->now += $seconds;
    }
}
