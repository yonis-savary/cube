<?php

namespace Cube\Security;

use Cube\Core\Component;
use Cube\Core\Exceptions\ResponseException;
use Cube\Data\Bunch;
use Cube\Env\Session\HasScopedSession;
use Cube\Security\SingleSignOn\ExternalIdentity;
use Cube\Security\SingleSignOn\IdentityProvider;
use Cube\Security\SingleSignOn\PendingAuthorization;
use Cube\Security\SingleSignOn\SingleSignOnConfiguration;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;

class SingleSignOn
{
    use Component;
    use HasScopedSession;

    public function __construct(
        protected SingleSignOnConfiguration $configuration
    ) {}

    public function provider(string $name): ?IdentityProvider
    {
        return Bunch::wrap($this->configuration->providers)
            ->first(fn($p) => $p->getName() === $name);
    }

    /**
     * @throws ResponseException 404 when no provider has that name, so a route slug can be given as is
     */
    public function redirect(IdentityProvider|string $provider): Response
    {
        $provider = $this->resolve($provider);
        $pending = $provider->authorize();

        $this->session()->set(self::pendingKey($provider), $pending);

        return Response::found($pending->url);
    }

    /**
     * @return ?ExternalIdentity `null` when the user refused, came back without going through
     *                           `redirect()`, came back twice, or the provider refused the code
     *
     * @throws ResponseException 404 when no provider has that name
     */
    public function callback(Request $request, IdentityProvider|string $provider): ?ExternalIdentity
    {
        $provider = $this->resolve($provider);
        $key = self::pendingKey($provider);

        $pending = $this->session()->get($key);
        $this->session()->unset($key);

        if (!$pending instanceof PendingAuthorization)
            return null;

        $state = $request->get()['state'] ?? null;
        if (!is_string($state) || !hash_equals($pending->state, $state))
            return null;

        $code = $request->get()['code'] ?? null;
        if (!is_string($code) || '' === $code)
            return null;

        return $provider->identify($code, $pending);
    }

    protected function resolve(IdentityProvider|string $provider): IdentityProvider
    {
        return $provider instanceof IdentityProvider
            ? $provider
            : $this->provider($provider) ?? throw new ResponseException("No identity provider named [{$provider}]", Response::notFound());
    }

    protected static function pendingKey(IdentityProvider $provider): string
    {
        return 'pending-'.$provider->getName();
    }
}
