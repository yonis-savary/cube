<?php

namespace App\Controllers\Requests;

use Cube\Utils\Utils;
use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;

class WriteProductsRequest extends Request
{
    public function getRules(): array|Rule
    {
        $product = [
            'name' => Param::string()->optional(),
            'price_dollar' => Param::float(nullable: true)->optional(),
        ];

        return Utils::isList($this->all())
            ? Param::array(Param::object($product))
            : $product;
    }
}
