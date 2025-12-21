<?php

namespace Cube\Web\Http\Configuration;

use Cube\Env\Configuration\ConfigurationElement;

use function Cube\env;

class CORSConfiguration extends ConfigurationElement
{
    /**
     * Allow the configuration of CORS settings through global configuration
     * @param ?string $allowOrigin Can also be configured through `.env` (`CORS_ALLOWED_ORIGINS`)
     * @param ?string $allowHeaders Can also be configured through `.env` (`CORS_ALLOWED_HEADERS`)
     * @param ?string $allowCredentials Can also be configured through `.env` (`CORS_ALLOWED_CREDENTIALS`)
     */
    public function __construct(
            public ?string $allowOrigin = null,
            public ?string $allowHeaders = null,
            public ?string $allowCredentials = null,
            public int $maxAge = 86400
    )
    {
        $this->allowOrigin ??= env('CORS_ALLOWED_ORIGINS', '*');
        $this->allowHeaders ??= env('CORS_ALLOWED_HEADERS', 'Content-Type, x-requested-with');
        $this->allowCredentials ??= env('CORS_ALLOWED_CREDENTIALS', 'true');
    }
}