<?php

namespace Cube\Env\Session;

use Cube\Env\Session;

trait HasScopedSession
{
    private ?Session $session = null;

    protected function session() {
        return $this->session ??= new Session($this->getScope());
    }

    public function getScope(): string
    {
        return md5(static::class . __DIR__);
    }
}
