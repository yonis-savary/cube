<?php

namespace Cube\Test;

use Cube\Security\SingleSignOn\ExternalIdentity;
use Cube\Security\SingleSignOn\IdentityProvider;
use Cube\Security\SingleSignOn\PendingAuthorization;

class FakeIdentityProvider implements IdentityProvider
{
    public const AUTHORIZATION_URL = 'https://fake-identity-provider.test/authorize';
    public const CODE = 'fake-authorization-code';

    protected ?ExternalIdentity $identity = null;
    protected ?PendingAuthorization $lastAuthorization = null;

    public function __construct(
        public readonly string $name = 'fake',
    ) {}

    /** @param array<string,mixed> $raw */
    public function withIdentity(string $id, ?string $email = null, bool $emailVerified = true, ?string $name = null, array $raw = []): static
    {
        $this->identity = new ExternalIdentity($this->name, $id, $email, $emailVerified, $name, $raw);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function authorize(): PendingAuthorization
    {
        $state = bin2hex(random_bytes(16));

        return $this->lastAuthorization = new PendingAuthorization(self::AUTHORIZATION_URL.'?state='.$state, $state);
    }

    public function identify(string $code, PendingAuthorization $pending): ?ExternalIdentity
    {
        return $this->identity;
    }

    /** @return array{code:string,state:string} What the provider sends back to your callback route after the last `authorize()` */
    public function callbackParameters(): array
    {
        if (!$this->lastAuthorization)
            throw new \RuntimeException('Nothing was authorized yet : call your redirect route before your callback route');

        return ['code' => self::CODE, 'state' => $this->lastAuthorization->state];
    }
}
