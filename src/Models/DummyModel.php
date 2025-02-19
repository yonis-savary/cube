<?php

namespace Cube\Models;

class DummyModel extends Model
{
    public static function table(): string
    {
        return "sharp-dummy-model";
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