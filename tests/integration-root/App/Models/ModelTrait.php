<?php

namespace App\Models;

trait ModelTrait
{
    public function tellClass(): string {
        return static::class;
    }
}