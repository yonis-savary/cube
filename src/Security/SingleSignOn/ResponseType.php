<?php

namespace Cube\Security\SingleSignOn;

/** The `response_type` of an authorization URL */
enum ResponseType: string
{
    case Code = 'code';
}
