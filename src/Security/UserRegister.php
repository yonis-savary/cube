<?php

namespace Cube\Security;

use Cube\Core\Component;
use Cube\Env\Cache;
use Cube\Env\Storage;
use Cube\Http\Request;
use Cube\Http\Response;
use Cube\Models\Model;
use Cube\Security\Authentication\Events\AuthenticatedUser;
use Cube\Security\UserRegister\RememberedUser;
use Cube\Security\UserRegister\UserRegisterConfiguration;
use Cube\Web\Middleware;

class UserRegister implements Middleware
{
    use Component;

    public function __construct(
        protected Cache $cache,
        protected Authentication $authentication,
        protected UserRegisterConfiguration $configuration
    ) {}

    public static function getDefaultInstance(): static
    {
        return new self(
            Storage::getInstance()->child('Cube')->child('UserRegister')->toCache(),
            Authentication::getInstance(),
            UserRegisterConfiguration::resolve()
        );
    }

    public function handleRequest(Request $request): Request|Response
    {
        if ($this->authentication->isLogged()) {
            return $request;
        }

        $cookieName = $this->configuration->cookieName;

        if (!$token = $request->getCookies()[$cookieName] ?? false) {
            return $request;
        }

        if (!$userId = $this->cache->try($token)) {
            return $request;
        }

        $this->authentication->login($userId);
        $userData = $this->authentication->user();

        (new RememberedUser(
            $userData,
            $userId
        ))->dispatch();

        if ($this->configuration->refreshTokenOnRemember) {
            $this->register($userData);
        }

        return $request;
    }

    public function register(AuthenticatedUser|Model $user)
    {
        if ($user instanceof AuthenticatedUser) {
            $userId = $user->userId;
        } else {
            $userId = $user->id();
        }

        $token = uniqid('user', true);
        $duration = $this->configuration->cookieDuration;

        $this->cache->set($token, $userId, $duration);

        setcookie(
            $this->configuration->cookieName,
            $token,
            time() + $duration,
            secure: $this->configuration->cookieSecure,
            httponly: $this->configuration->cookieHttpOnly
        );
    }

    public static function handle(Request $request): Request|Response
    {
        return self::getInstance()->handleRequest($request);
    }
}
