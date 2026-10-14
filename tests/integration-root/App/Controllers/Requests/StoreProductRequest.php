<?php

namespace App\Controllers\Requests;

use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\Param;

class StoreProductRequest extends Request
{
    public function getRules(): array
    {
        return [
            'name' => Param::string(),
            'price_dollar' => Param::float(nullable: true),
            'managers' => Param::array(Param::object()),
        ];
    }
}
