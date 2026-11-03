<?php

namespace Cube\Data\DataToObject\Generator\Descriptors\Types;

enum Scalar: string
{
    case Bool = 'bool';
    case Int = 'int';
    case Float = 'float';
    case String = 'string';
}
