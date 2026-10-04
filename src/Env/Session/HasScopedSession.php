<?php

namespace Cube\Env\Session;

use Cube\Env\Session;

trait HasScopedSession
{
    private ?Session $session = null;

    protected function getSessionConfiguration(): SessionConfiguration
    {
        return SessionConfiguration::resolve();
    }

    protected function session(): Session
    {
        return $this->session ??= new Session($this->getSessionConfiguration(), $this->getScope());
    }

    public function getScope(): string
    {
        return md5(static::class . __DIR__);
    }
}
