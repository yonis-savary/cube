<?php

namespace YonisSavary\Cube\Env\Session;

use YonisSavary\Cube\Env\Session;

trait HasScopedSession
{
    private ?Session $session = null;

    public function getSession(): Session
    {
        $this->session ??= new session($this->getScope());
        return $this->session;
    }

    public function getScope(): string
    {
        return md5(get_called_class());
    }
}