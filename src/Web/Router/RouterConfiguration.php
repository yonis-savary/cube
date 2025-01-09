<?php

namespace YonisSavary\Cube\Web\Router;

use InvalidArgumentException;
use YonisSavary\Cube\Configuration\ConfigurationElement;
use YonisSavary\Cube\Core\Autoloader;

class RouterConfiguration extends ConfigurationElement
{
    /**
     * @var array<Service>
     */
    public readonly array $services;

    /**
     * @param bool $cached Do the router should cache routes
     * @param bool $loadControllers Load routes from Controller classes ?
     * @param bool $loadRoutesFiles Load PHP Files inside your app(s) Routes directory ?
     * @param array<String|Service> Additionnal services to load (Either classes name or instances)
     */
    public function __construct(
        public readonly bool $cached=false,
        public readonly bool $loadControllers=true,
        public readonly bool $loadRoutesFiles=true,
        array $services=[],
        public readonly array $commonMiddlewares=[],
        public readonly ?string $commonPrefix=null,
    )
    {
        foreach ($services as &$service)
        {
            if (!Autoloader::extends($service, Service::class))
                throw new InvalidArgumentException("Given service must extend Service class");

            if (is_string($service))
                $service = new $service();
        }

        $this->services = $services;
    }
}