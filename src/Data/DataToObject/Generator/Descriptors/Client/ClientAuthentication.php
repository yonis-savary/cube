<?php

namespace Cube\Data\DataToObject\Generator\Descriptors\Client;

readonly class ClientAuthentication
{
    /**
     * @param 'cookie'|'header'|'query' $location
     * @param string                    $prefix   written before the token, `Bearer ` for a bearer token
     */
    public function __construct(
        public string $location,
        public string $name,
        public string $prefix = '',
    ) {}
}
