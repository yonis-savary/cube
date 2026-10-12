<?php

namespace App\Controllers\Requests;

use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;

class ReadProductsRequest extends Request
{
    public function getRules(): array|Rule
    {
        return [
            'id' => Param::integer()->optional(),
            'name' => Param::string()->optional(),
            'price_dollar' => Param::float()->optional(),
        ];
    }
}
