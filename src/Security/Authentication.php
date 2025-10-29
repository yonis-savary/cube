<?php

namespace Cube\Security;

use Cube\Core\Component;
use Cube\Env\Session\HasScopedSession;
use Cube\Data\Models\Model;
use Cube\Security\Authentication\AuthenticationConfiguration;
use Cube\Security\Authentication\AuthenticationProvider;
use Cube\Security\Authentication\Events\AuthenticatedUser;
use Cube\Security\Authentication\Events\FailedAuthentication;

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
    public function __construct(AuthenticationConfiguration $configuration) {
        $this->provider = $configuration->provider;
        $this->session();
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
        $this->session->set(self::SESSION_USER_CLASS, $user::class);
        $this->session->set(self::SESSION_USER_DATA, $user->toArray());
        $this->session->set(self::SESSION_USER_ID, $user->id());
    }

    public function logout(): void
    {
        $this->session->unset(self::SESSION_USER_DATA);
        $this->session->unset(self::SESSION_USER_ID);
    }

    public function isLogged(): bool
    {
        return false != $this->session->get(self::SESSION_USER_DATA, false);
    }

    public function user(): Model
    {
        if (! $userArrayData = $this->session->get(self::SESSION_USER_DATA, false)) {
            throw new \RuntimeException('Cannot retrieve data of unauthenticated user');
        }

        $userClass = $this->session->get(self::SESSION_USER_CLASS);
        return new $userClass($userArrayData);
    }

    /**
     * @return mixed `false` on failure
     */
    public function userId(): mixed
    {
        return $this->session->get(self::SESSION_USER_ID, false);
    }
}
