<?php

namespace Cube\Security\SingleSignOn;

final class ExternalIdentity
{
    /** @param array<string,mixed> $raw The profile as the provider returned it */
    public function __construct(
        public readonly string $provider,
        public readonly string $id,
        public readonly ?string $email = null,
        public readonly bool $emailVerified = false,
        public readonly ?string $name = null,
        public readonly array $raw = [],
    ) {}
}
