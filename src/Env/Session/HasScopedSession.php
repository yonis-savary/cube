<?php

namespace Cube\Env\Session;

use Cube\Env\Session;

trait HasScopedSession
{
    private ?Session $session = null;

    public function getSession(): Session
    {
        $this->session ??= new Session($this->getScope());

        return $this->session;
    }

    public function getScope(): string
    {
        return md5(static::class);
    }
}
