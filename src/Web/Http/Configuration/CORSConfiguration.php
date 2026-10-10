<?php

namespace Cube\Web\Http\Configuration;

use Cube\Data\Bunch;
use Cube\Env\Configuration\ConfigurationElement;

use function Cube\env;

class CORSConfiguration extends ConfigurationElement
{
    /**
     * @param string[] $allowedOrigins Full origins (`https://app.example.org`), or `['*']` for any origin, which browsers never pair with credentials. Can also be configured through `.env` (`CORS_ALLOWED_ORIGINS`, comma separated)
     * @param ?string $allowHeaders Can also be configured through `.env` (`CORS_ALLOWED_HEADERS`)
     * @param ?bool $allowCredentials Let the browser send cookies along, ignored with `*`. Can also be configured through `.env` (`CORS_ALLOWED_CREDENTIALS`)
     */
    public function __construct(
        public array $allowedOrigins = [],
        public ?string $allowHeaders = null,
        public ?bool $allowCredentials = null,
        public int $maxAge = 86400
    )
    {
        if (!count($this->allowedOrigins))
            $this->allowedOrigins = Bunch::fromExplode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
                ->map(trim(...))
                ->filter(keepValues: true)
                ->get();

        $this->allowHeaders ??= env('CORS_ALLOWED_HEADERS', 'Content-Type, x-requested-with');
        $this->allowCredentials ??= (bool) env('CORS_ALLOWED_CREDENTIALS', false);
    }

    public function allowsAnyOrigin(): bool
    {
        return in_array('*', $this->allowedOrigins, true);
    }

    /**
     * @return ?string The `Access-Control-Allow-Origin` value to answer to `$origin`, `null` when it is not allowed
     */
    public function getAllowedOrigin(?string $origin): ?string
    {
        return match(true) {
            $this->allowsAnyOrigin() => '*',
            in_array($origin, $this->allowedOrigins, true) => $origin,
            default =>  null,
        };
    }
}
