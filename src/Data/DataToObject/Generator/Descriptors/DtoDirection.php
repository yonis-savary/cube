<?php

namespace Cube\Data\DataToObject\Generator\Descriptors;

enum DtoDirection
{
    /** Built from received data, a `DataToObject` */
    case Output;

    /** Built by you then sent, an `AutoDataToObject` */
    case Input;
}
