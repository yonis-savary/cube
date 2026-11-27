<?php

namespace Cube\Security\SingleSignOn\Providers;

use Cube\Env\Cache;
use Cube\Security\SingleSignOn\AuthorizationCodeProvider;
use Cube\Security\SingleSignOn\ExternalIdentity;
use Cube\Security\SingleSignOn\IdentityProviderClient;
use Cube\Utils\Text;

/**
 * Any OpenID Connect provider, found from its issuer URL : Google, Microsoft Entra ID, GitLab,
 * Keycloak, Auth0, Okta... Its endpoints are read from the discovery document, kept a day in
 * the `Cache`.
 */
class OpenIDConnectProvider extends AuthorizationCodeProvider
{
    public const DISCOVERY_PATH = '/.well-known/openid-configuration';

    /**
     * @param string[] $scopes
     */
    public function __construct(
        string $name,
        public readonly string $issuer,
        string $clientId,
        #[\SensitiveParameter]
        string $clientSecret,
        string $redirectUri,
        array $scopes = ['openid', 'email', 'profile'],
    ) {
        parent::__construct($name, $clientId, $clientSecret, $redirectUri, $scopes);
    }

    public static function google(
        string $clientId,
        #[\SensitiveParameter]
        string $clientSecret,
        string $redirectUri,
        string $name = 'google',
    ): static {
        return new static($name, 'https://accounts.google.com', $clientId, $clientSecret, $redirectUri);
    }

    protected function authorizationEndpoint(): string
    {
        return $this->endpoint('authorization_endpoint');
    }

    protected function tokenEndpoint(): string
    {
        return $this->endpoint('token_endpoint');
    }

    protected function identityOf(string $accessToken, IdentityProviderClient $client): ExternalIdentity
    {
        $response = $client->get($this->endpoint('userinfo_endpoint'), headers: $this->bearer($accessToken));
        $claims = $response->isOk() ? $this->jsonOf($response, 'userinfo endpoint') : [];

        if (!is_string($claims['sub'] ?? null))
            throw new \RuntimeException(Text::interpolate('{provider} userinfo endpoint answered [{status}] without a subject', [
                'provider' => $this->name,
                'status' => $response->getStatusCode(),
            ]));

        return new ExternalIdentity(
            $this->name,
            $claims['sub'],
            $claims['email'] ?? null,
            in_array($claims['email_verified'] ?? false, [true, 'true'], true),
            $claims['name'] ?? null,
            $claims
        );
    }

    protected function endpoint(string $key, ?Cache $cache = null): string
    {
        $endpoint = $this->discovery($cache)[$key] ?? null;
        if (!is_string($endpoint))
            throw new \RuntimeException("{$this->name} discovery document has no [{$key}]");

        return $endpoint;
    }

    /**
     * @return array<string,mixed>
     */
    protected function discovery(?Cache $cache = null): array
    {
        $cache ??= Cache::getInstance();
        $cache = $cache->child('openid-connect-discovery');
        $key = md5($this->issuer);

        if (is_array($document = $cache->get($key)))
            return $document;
        $url = rtrim($this->issuer, '/').self::DISCOVERY_PATH;
        $response = $this->client()->get($url);

        if (!$response->isOk())
            throw new \RuntimeException(Text::interpolate('{provider} discovery document [{url}] answered [{status}]', [
                'provider' => $this->name,
                'url' => $url,
                'status' => $response->getStatusCode(),
            ]));

        $document = $this->jsonOf($response, 'discovery document');

        // OpenID Connect Discovery 1.0 §4.3 : a document announcing another issuer must not be used
        if (($document['issuer'] ?? null) !== $this->issuer)
            throw new \RuntimeException(Text::interpolate('{provider} discovery document announces the issuer [{announced}], expected [{issuer}]', [
                'provider' => $this->name,
                'announced' => $document['issuer'] ?? 'none',
                'issuer' => $this->issuer,
            ]));

        $cache->set($key, $document, Cache::DAY);

        return $document;
    }
}
