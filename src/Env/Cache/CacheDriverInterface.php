<?php

namespace Cube\Env\Cache;

interface CacheDriverInterface
{
    public const PERMANENT = 0;
    public const SECOND = 1;
    public const MINUTE = self::SECOND * 60;
    public const HOUR = self::MINUTE * 60;
    public const DAY = self::HOUR * 24;
    public const WEEK = self::DAY * 7;
    public const MONTH = self::DAY * 31;

    public function initialize();
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $timeToLive = self::MONTH, ?int $creationDate = null);
    public function has(string $key): bool;
    public function delete(string $key): void;
    public function clear(): void;

    public function &getReference(string $key): mixed;
}