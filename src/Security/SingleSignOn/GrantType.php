<?php

namespace Cube\Security\SingleSignOn;

/** The `grant_type` of a token request */
enum GrantType: string
{
    case AuthorizationCode = 'authorization_code';
}
