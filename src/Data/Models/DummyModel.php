<?php

namespace Cube\Data\Models;

class DummyModel extends Model
{
    public static function table(): string
    {
        return 'cube-dummy-model';
    }

    public static function fields(): array
    {
        return [];
    }

    public static function relations(): array
    {
        return [];
    }
}
