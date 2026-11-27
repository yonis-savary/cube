<?php

namespace Cube\Web\Router;

use Cube\Core\Autoloader;
use Cube\Env\Configuration\ConfigurationElement;
use Cube\Web\Helpers\WebAPI;
use Cube\Web\Http\IpAddress;

class RouterConfiguration extends ConfigurationElement
{
    /**
     * @var array<WebAPI>
     */
    public readonly array $apis;

    /**
     * @param bool                   $cached          Do the router should cache routes
     * @param bool                   $loadControllers Load routes from Controller classes ?
     * @param bool                   $loadRoutesFiles Load PHP Files inside your app(s) Routes directory ?
     * @param class-string<WebAPI>[] $apis            Additionnal services to load (Either classes name or instances)
     * @param array<string,string>   $securityHeaders Headers added to every response that does not set them already (X-Frame-Options, Content-Security-Policy...)
     * @param string[]               $trustedProxies  Reverse proxies (addresses or CIDR networks) whose X-Forwarded-For header gives the client address
     */
    public function __construct(
        public readonly bool $cached = false,
        public readonly bool $loadControllers = true,
        public readonly bool $loadRoutesFiles = true,
        array $apis = [],
        public readonly array $commonMiddlewares = [],
        public readonly string $commonPrefix = '/',
        public readonly array $securityHeaders = [],
        public readonly array $trustedProxies = [],
    ) {
        foreach ($trustedProxies as $proxy) {
            if (!IpAddress::isRange($proxy))
                throw new \InvalidArgumentException("Trusted proxy [{$proxy}] is neither an IP address nor a CIDR network");
        }

        foreach ($apis as &$api) {
            if (!Autoloader::extends($api, WebAPI::class)) {
                throw new \InvalidArgumentException('Given api must extend WebAPI class');
            }

            if (is_string($api)) {
                $api = new $api();
            }
        }

        $this->apis = $apis;
    }
}
