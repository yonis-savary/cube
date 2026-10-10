<?php

namespace Cube\Security;

use Closure;
use Cube\Core\Component;
use Cube\Data\Bunch;
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

        if (!$this->isToken($token) || !$userId = $this->cache->try($token)) {
            return $request;
        }

        $this->issuedToken = null;

        if (!$this->authentication->loginById($userId)) {
            $this->dropToken($token, $userId);
            return $request;
        }

        $userData = $this->authentication->user();

        (new RememberedUser(
            $userData,
            $userId
        ))->dispatch();

        if ($this->issuedToken || $this->configuration->refreshTokenOnRemember) {
            $this->dropToken($token, $userId);
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

        $this->cache->set($token, $userId, $duration);
        $this->userTokens()->set((string) $userId, [...$this->liveTokensOf($userId), $token], $duration);

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

        if ($this->isToken($token) && $userId = $this->cache->try($token)) {
            $this->dropToken($token, $userId);
        }

        // Without this the browser keeps sending a token nothing answers to
        $this->sendCookie('', time() - 3600);
    }

    public function forgetUser(Model|int|string $user): void
    {
        $userId = $user instanceof Model ? $user->id() : $user;

        foreach ($this->liveTokensOf($userId) as $token) {
            $this->cache->delete($token);
        }

        $this->userTokens()->delete((string) $userId);
    }

    protected function dropToken(string $token, mixed $userId): void
    {
        $this->cache->delete($token);

        $this->userTokens()->set(
            (string) $userId,
            array_values(array_diff($this->liveTokensOf($userId), [$token])),
            $this->configuration->cookieDuration
        );
    }

    /**
     * @return string[] Tokens of the user that still point at them
     */
    protected function liveTokensOf(mixed $userId): array
    {
        return Bunch::of($this->userTokens()->get((string) $userId, []))
            ->filter(fn (string $token) => $this->cache->get($token) == $userId, true)
            ->get();
    }

    protected function userTokens(): Cache
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
