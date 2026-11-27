<?php

namespace Cube\Security\SingleSignOn;

/**
 * What `SingleSignOn` keeps in the session between the redirection to the provider and the
 * callback : the URL the user is sent to, and the values the callback has to prove.
 */
final class PendingAuthorization
{
    public function __construct(
        public readonly string $url,
        public readonly string $state,
        #[\SensitiveParameter]
        public readonly ?string $codeVerifier = null,
    ) {}
}
