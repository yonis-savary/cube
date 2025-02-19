<?php

namespace App\Controllers\Requests;

use Cube\Http\Request;
use Cube\Http\Rules\Param;

class StoreProductRequest extends Request
{
    public function getRules(): array
    {
        return [
            "name" => Param::string(true, false),
            "price_dollar" => Param::float(true),
            'managers' => Param::array(Param::object())
        ];
    }
}