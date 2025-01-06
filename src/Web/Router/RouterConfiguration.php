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

    public function __construct(
        public readonly bool $cached=false,
        public readonly bool $loadControllers=true,
        public readonly bool $loadRoutesFiles=true,
        array $services=[]
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