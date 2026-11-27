<?php

namespace Cube\Web\Http\Exceptions;

class NonPublicAddressException extends \RuntimeException
{
    public function __construct(
        public readonly string $host,
        public readonly string $address,
    ) {
        parent::__construct("Refused to reach [{$host}] : it resolves to [{$address}], which is not a public address");
    }
}
