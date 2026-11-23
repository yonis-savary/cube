<?php

namespace Cube\Security\SingleSignOn;

interface IdentityProvider
{
    /** Identifies the provider in `SingleSignOnConfiguration` and in `ExternalIdentity::$provider` */
    public function getName(): string;

    public function authorize(): PendingAuthorization;

    /** @return ?ExternalIdentity `null` when the provider refuses the code : expired, already used or forged */
    public function identify(string $code, PendingAuthorization $pending): ?ExternalIdentity;
}
