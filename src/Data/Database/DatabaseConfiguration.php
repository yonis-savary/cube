<?php

namespace Cube\Data\Database;

use Cube\Env\Configuration\ConfigurationElement;

class DatabaseConfiguration extends ConfigurationElement
{
    /**
     * @param int $keepAliveInterval Seconds between two connection checks in `websocket:serve` and `cube:queue`, `0` to never check
     */
    public function __construct(
        public readonly DatabaseDriver|string $driver = DatabaseDriver::SQLite,
        public readonly string $database = 'db.sqlite',
        public readonly ?string $host = null,
        public readonly ?int $port = null,
        public readonly ?string $user = null,
        public readonly ?string $password = null,
        public readonly int $keepAliveInterval = 300,
    ) {}
}
