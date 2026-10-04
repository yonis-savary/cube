<?php

namespace Cube\Env\Session;

use Cube\Env\Configuration\ConfigurationElement;
use Cube\Env\Session\Drivers\PHPGlobalSession;
use Cube\Env\Session\Drivers\SessionDriverInterface;

class SessionConfiguration extends ConfigurationElement
{
    public function __construct(
        public readonly string $namespace = 'cube',
        public SessionDriverInterface $driver = new PHPGlobalSession(),
    ) {}
}
