<?php

namespace Cube\Security\SingleSignOn;

use Cube\Web\Http\HttpClient;

/**
 * The client every `AuthorizationCodeProvider` talks to its provider with. Register an
 * `HttpMockServer` for this class in `MockServers` to fake a provider in your tests.
 */
class IdentityProviderClient extends HttpClient
{
    /**
     * @return array<string,string>
     */
    public function baseHeaders(): array
    {
        return ['accept' => 'application/json'];
    }

    public function baseUserAgent(): string
    {
        return 'Cube';
    }
}
