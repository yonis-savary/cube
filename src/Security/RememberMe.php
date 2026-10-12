<?php

namespace Cube\Security;

use Closure;
use Cube\Core\Component;
use Cube\Env\Cache;
use Cube\Env\Storage;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Data\Models\Model;
use Cube\Security\Authentication;
use Cube\Security\Authentication\Events\AuthenticatedUser;
use Cube\Security\RememberMe\RememberedUser;
use Cube\Security\RememberMe\UserRegisterConfiguration;
use Cube\Web\Middleware;

class RememberMe implements Middleware
{
    use Component;

    protected ?string $issuedToken = null;

    public function __construct(
        protected Cache $cache,
        protected Authentication $authentication,
        protected UserRegisterConfiguration $configuration
    ) {}

    public static function getDefaultInstance(): static
    {
        return new static(
            Cache::getInstance(),
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

        $userId = $this->rememberedUserOf($token);
        if (!$this->isToken($token) || null === $userId) {
            return $request;
        }

        $this->issuedToken = null;

        if (!$this->authentication->loginById($userId)) {
            $this->dropToken($token);
            return $request;
        }

        $userData = $this->authentication->user();

        (new RememberedUser(
            $userData,
            $userId
        ))->dispatch();

        if ($this->issuedToken || $this->configuration->refreshTokenOnRemember) {
            $this->dropToken($token);
        }

        if ($this->configuration->refreshTokenOnRemember && !$this->issuedToken) {
            $this->register($userData);
        }

        return $request;
    }

    public function register(AuthenticatedUser|Model $user): void
    {
        $userId = $user instanceof AuthenticatedUser
            ? $user->userId
            : $user->id();

        $token = bin2hex(random_bytes(32));
        $duration = $this->configuration->cookieDuration;
        $generation = $this->generationOf($userId) ?? self::newGeneration();

        $this->userGenerations()->set((string) $userId, $generation, $duration);
        $this->tokens()->set(self::keyOf($token), ['user' => $userId, 'generation' => $generation], $duration);

        $this->issuedToken = $token;
        $this->sendCookie($token, time() + $duration);
    }

    public static function handle(Request $request, Closure $next): Request|Response
    {
        $handled = self::getInstance()->handleRequest($request);

        return $handled instanceof Response
            ? $handled
            : $next($handled)
        ;
    }

    public function forget(Request|string $requestOrToken): void
    {
        $token = $requestOrToken;

        if ($requestOrToken instanceof Request) {
            $cookieName = $this->configuration->cookieName;
            $token = $requestOrToken->getCookies()[$cookieName] ?? false;
        }

        if ($this->isToken($token)) {
            $this->dropToken($token);
        }

        // Without this the browser keeps sending a token nothing answers to
        $this->sendCookie('', time() - 3600);
    }

    // Every token carries the generation of its user when issued : a new one invalidates them all, with no token list to keep in sync
    public function forgetUser(Model|int|string $user): void
    {
        $userId = $user instanceof Model ? $user->id() : $user;

        $this->userGenerations()->set((string) $userId, self::newGeneration(), $this->configuration->cookieDuration);
    }

    protected function rememberedUserOf(string $token): mixed
    {
        $entry = $this->tokens()->get(self::keyOf($token));
        if (!is_array($entry))
            return null;

        return $this->generationOf($entry['user']) === $entry['generation']
            ? $entry['user']
            : null;
    }

    protected function dropToken(string $token): void
    {
        $this->tokens()->delete(self::keyOf($token));
    }

    protected function generationOf(mixed $userId): ?string
    {
        return $this->userGenerations()->get((string) $userId);
    }

    protected static function newGeneration(): string
    {
        return bin2hex(random_bytes(16));
    }

    // Only a hash is stored : reading the cache, or its file names, must not hand out working cookies
    protected static function keyOf(string $token): string
    {
        return hash('sha256', $token);
    }

    protected function tokens(): Cache
    {
        return $this->cache->child('remember-me-tokens');
    }

    protected function userGenerations(): Cache
    {
        return $this->cache->child('remember-me-users');
    }

    protected function sendCookie(string $value, int $expiresAt): void
    {
        setcookie($this->configuration->cookieName, $value, [
            'expires' => $expiresAt,
            'path' => $this->configuration->cookiePath,
            'secure' => $this->configuration->cookieSecure,
            'httponly' => $this->configuration->cookieHttpOnly,
            'samesite' => $this->configuration->cookieSameSite,
        ]);
    }

    protected function isToken(mixed $token): bool
    {
        return is_string($token) && 1 === preg_match('/^[0-9a-f]{64}$/', $token);
    }
}
