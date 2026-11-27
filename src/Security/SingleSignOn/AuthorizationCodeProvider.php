<?php

namespace Cube\Security\SingleSignOn;

use Cube\Utils\Text;
use Cube\Web\Http\Response;

abstract class AuthorizationCodeProvider implements IdentityProvider
{
    /** Errors a token endpoint answers when the code is refused, not the client : the user can simply start again */
    public const REFUSED_CODE_ERRORS = ['invalid_grant'];

    /** @param string[] $scopes */
    public function __construct(
        public readonly string $name,
        public readonly string $clientId,
        #[\SensitiveParameter]
        protected readonly string $clientSecret,
        public readonly string $redirectUri,
        public readonly array $scopes = [],
    ) {}

    abstract protected function authorizationEndpoint(): string;

    abstract protected function tokenEndpoint(): string;

    abstract protected function identityOf(string $accessToken, IdentityProviderClient $client): ExternalIdentity;

    /** @return array<string,string> */
    protected function authorizationParameters(): array
    {
        return [];
    }

    protected function client(): IdentityProviderClient
    {
        return new IdentityProviderClient();
    }

    final public function getName(): string
    {
        return $this->name;
    }

    final public function authorize(): PendingAuthorization
    {
        $state = random_bytes(16) |> bin2hex(...);
        $codeVerifier = self::base64url(random_bytes(32));

        $parameters = [
            'response_type' => ResponseType::Code->value,
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $this->scopes),
            'state' => $state,
            'code_challenge' => self::base64url(hash('sha256', $codeVerifier, true)),
            'code_challenge_method' => 'S256',
        ] + $this->authorizationParameters();

        $endpoint = $this->authorizationEndpoint();
        $separator = str_contains($endpoint, '?') ? '&' : '?';

        return new PendingAuthorization(
            $endpoint.$separator.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986),
            $state,
            $codeVerifier
        );
    }

    final public function identify(string $code, PendingAuthorization $pending): ?ExternalIdentity
    {
        $client = $this->client();

        $parameters = array_filter([
            'grant_type' => GrantType::AuthorizationCode->value,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code_verifier' => $pending->codeVerifier,
        ], fn ($value) => null !== $value);

        $response = $client->post($this->tokenEndpoint(), $parameters, headers: ['content-type' => 'application/x-www-form-urlencoded']);
        $token = $this->jsonOf($response, 'token endpoint');

        if (is_string($accessToken = $token['access_token'] ?? null))
            return $this->identityOf($accessToken, $client);
        if (in_array($token['error'] ?? null, static::REFUSED_CODE_ERRORS, true))
            return null;
        throw new \RuntimeException(Text::interpolate('{provider} token endpoint answered [{status}] without an access token : {error} {description}', [
            'provider' => $this->name,
            'status' => $response->getStatusCode(),
            'error' => $token['error'] ?? 'no error code',
            'description' => $token['error_description'] ?? '',
        ]));
    }

    /**
     * @return array<string,string>
     */
    protected function bearer(string $accessToken): array
    {
        return ['authorization' => "Bearer {$accessToken}"];
    }

    /**
     * @return array<mixed>
     */
    protected function jsonOf(Response $response, string $endpoint): array
    {
        try {
            $json = $response->getJSON();
        } catch (\JsonException) {
            $json = null;
        }

        if (!is_array($json))
            throw new \RuntimeException(Text::interpolate('{provider} {endpoint} answered [{status}] with a body that is not a JSON object', [
                'provider' => $this->name,
                'endpoint' => $endpoint,
                'status' => $response->getStatusCode(),
            ]));

        return $json;
    }

    protected static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
