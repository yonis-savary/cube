<?php

namespace Cube\Security;

use Cube\Core\Component;
use Cube\Env\Session\HasScopedSession;
use Cube\Data\Models\Model;
use Cube\Security\Authentication\AuthenticationConfiguration;
use Cube\Security\Authentication\AuthenticationProvider;
use Cube\Security\Authentication\Events\AuthenticatedUser;
use Cube\Security\Authentication\Events\FailedAuthentication;
use Cube\Utils\Path;

class Authentication
{
    use Component;
    use HasScopedSession;

    public const SESSION_USER_ID = 'session-user-id';
    public const SESSION_USER_DATA = 'session-user-data';
    public const SESSION_USER_CLASS = 'session-user-class';

    private AuthenticationProvider $provider;

    /**
     * @param class-string<Model> $model
     */
    public function __construct(?AuthenticationConfiguration $configuration=null) {
        $configuration ??= AuthenticationConfiguration::resolve(null);
        $this->provider = $configuration->provider;
    }

    public function getSessionKey(string $key): string
    {
        return md5(Path::relative($key));
    }

    public function attempt(string $login, ?string $userPassword=null): bool
    {
        $this->logout();

        if (!$user = $this->provider->attempt($login, $userPassword)){
            (new FailedAuthentication())->dispatch();
            return false;
        }

        $this->login($user);
        (new AuthenticatedUser($user, $user->id()))->dispatch();
        return true;
    }

    public function login(Model $user): void
    {
        $session = $this->getSession();

        $session->set($this->getSessionKey(self::SESSION_USER_CLASS), $user::class);
        $session->set($this->getSessionKey(self::SESSION_USER_DATA), $user->toArray());
        $session->set($this->getSessionKey(self::SESSION_USER_ID), $user->id());
    }

    public function logout(): void
    {
        $session = $this->getSession();

        $session->unset($this->getSessionKey(self::SESSION_USER_DATA));
        $session->unset($this->getSessionKey(self::SESSION_USER_ID));
    }

    public function isLogged(): bool
    {
        $session = $this->getSession();
        $userArrayData = $session->get($this->getSessionKey(self::SESSION_USER_DATA), false);

        return false != $userArrayData;
    }

    public function user(): Model
    {
        $session = $this->getSession();
        $userArrayData = $session->get($this->getSessionKey(self::SESSION_USER_DATA));
        $userClass = $session->get($this->getSessionKey(self::SESSION_USER_CLASS));

        if (!$this->isLogged()) {
            throw new \RuntimeException('Cannot retrieve data of unauthenticated user');
        }

        return new $userClass($userArrayData);
    }

    public function userId(): mixed
    {
        return $this->getSession()->get($this->getSessionKey(self::SESSION_USER_ID), false);
    }
}
