<?php

namespace Cube\Web\Router;

use Cube\Configuration\ConfigurationElement;
use Cube\Core\Autoloader;
use Cube\Web\WebAPI;

class RouterConfiguration extends ConfigurationElement
{
    /**
     * @var array<WebAPI>
     */
    public readonly array $apis;

    /**
     * @param bool                        $cached          Do the router should cache routes
     * @param bool                        $loadControllers Load routes from Controller classes ?
     * @param bool                        $loadRoutesFiles Load PHP Files inside your app(s) Routes directory ?
     * @param class-string<WebAPI>|WebAPI $apis            Additionnal services to load (Either classes name or instances)
     */
    public function __construct(
        public readonly bool $cached = false,
        public readonly bool $loadControllers = true,
        public readonly bool $loadRoutesFiles = true,
        array $apis = [],
        public readonly array $commonMiddlewares = [],
        public readonly string $commonPrefix = '/',
    ) {
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
