<?php

namespace Cube\Security;

use Cube\Core\Component;
use Cube\Data\Models\Model;
use Cube\Env\Session\HasScopedSession;
use Cube\Security\Authentication\AuthenticationConfiguration;
use Cube\Security\Authentication\AuthenticationProvider;
use Cube\Security\Authentication\Events\AuthenticatedUser;
use Cube\Security\Authentication\Events\FailedAuthentication;
use Cube\Security\Authentication\Events\LoggedInUser;
use Cube\Security\Authentication\Events\LoggedOutUser;
use Cube\Security\Authentication\VerifiesBearerTokens;
use Cube\Security\Authentication\VerifiesCredentials;

class Authentication
{
    use Component;
    use HasScopedSession;

    public const SESSION_USER_ID = 'session-user-id';
    public const SESSION_USER_DATA = 'session-user-data';
    public const SESSION_USER_CLASS = 'session-user-class';

    private AuthenticationProvider $provider;
    private ?Model $bearerUser = null;

    public function __construct(AuthenticationConfiguration $configuration)
    {
        $this->provider = $configuration->provider;
    }

    public function attempt(string $login, #[\SensitiveParameter] ?string $userPassword = null): bool
    {
        if (!$this->provider instanceof VerifiesCredentials)
            throw new \RuntimeException(sprintf('%s cannot verify credentials : attempt() needs a provider implementing %s', $this->provider::class, VerifiesCredentials::class));

        $this->resetSession();

        if (!$user = $this->provider->attempt($login, $userPassword)) {
            (new FailedAuthentication($login))->dispatch();
            return false;
        }

        $this->login($user);
        return true;
    }

    public function attemptBearerToken(#[\SensitiveParameter] string $token): bool
    {
        if (!$this->provider instanceof VerifiesBearerTokens)
            throw new \RuntimeException(sprintf('%s cannot verify bearer tokens : attemptBearerToken() needs a provider implementing %s', $this->provider::class, VerifiesBearerTokens::class));

        $user = $this->provider->userByBearerToken($token);
        $this->bearerUser = $user
            ? (new ($user::class)($this->toSessionData($user)))->markAsPersisted()
            : null;

        return null !== $this->bearerUser;
    }

    public function login(Model $user): void
    {
        if ('cli' !== PHP_SAPI && headers_sent($file, $line))
            throw new \RuntimeException("Cannot log a user in once output started at {$file}:{$line} : the session id could not be regenerated");

        $this->session()->regenerateId();
        $this->session()->set(self::SESSION_USER_CLASS, $user::class);
        $this->session()->set(self::SESSION_USER_DATA, $this->toSessionData($user));
        $this->session()->set(self::SESSION_USER_ID, $user->id());

        (new AuthenticatedUser($user, $user->id()))->dispatch();
        (new LoggedInUser($user, $user->id()))->dispatch();
    }

    protected function toSessionData(Model $user): array
    {
        return array_diff_key($user->toArray(), array_flip($this->provider->getCredentialFields()));
    }

    public function loginById(mixed $id): bool
    {
        if (!$user = $this->provider->userById($id))
            return false;
        $this->login($user);
        return true;
    }

    protected function resetSession()
    {
        $this->session()->unset(self::SESSION_USER_CLASS);
        $this->session()->unset(self::SESSION_USER_DATA);
        $this->session()->unset(self::SESSION_USER_ID);
    }

    public function logout(): void
    {
        $this->bearerUser = null;

        if (!$this->isLogged())
            return;
        $user = $this->user();
        $userId = $this->userId();

        $this->resetSession();
        $this->session()->regenerateId();

        (new LoggedOutUser($user, $userId))->dispatch();
    }

    public function isLogged(): bool
    {
        return null !== $this->bearerUser
            || false !== $this->session()->get(self::SESSION_USER_DATA, false);
    }

    public function user(): Model
    {
        if (null !== $this->bearerUser)
            return $this->bearerUser;
        if (!$userArrayData = $this->session()->get(self::SESSION_USER_DATA, false)) {
            throw new \RuntimeException('Cannot retrieve data of unauthenticated user');
        }

        $userClass = $this->session()->get(self::SESSION_USER_CLASS);
        return (new $userClass($userArrayData))->markAsPersisted();
    }

    /**
     * @return mixed `false` on failure
     */
    public function userId(): mixed
    {
        if (null !== $this->bearerUser)
            return $this->bearerUser->id();
        return $this->session()->get(self::SESSION_USER_ID, false);
    }
}
