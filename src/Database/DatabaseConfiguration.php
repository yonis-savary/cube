<?php

namespace YonisSavary\Cube\Database;

use YonisSavary\Cube\Configuration\ConfigurationElement;

class DatabaseConfiguration extends ConfigurationElement
{
    public function __construct(
        public readonly string $driver="sqlite",
        public readonly string $database="db.sqlite",
        public readonly ?string $host=null,
        public readonly ?int $port=null,
        public readonly ?string $user=null,
        public readonly ?string $password=null
    )
    {}
}