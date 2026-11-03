<?php

namespace Cube\Data\DataToObject\Generator\Sources;

use Cube\Data\DataToObject\Generator\Descriptors\DtoClass;

interface DtoSource
{
    /**
     * @return DtoClass[]
     */
    public function toClasses(): array;
}
