<?php

namespace App\Controllers\Requests;

use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Http\Rules\Rule;

class StoreProductRequest extends Request
{
    public function getRules(): array
    {
        return [
            "name" => Rule::string(true, false),
            "price_dollar" => Rule::float(true),
            'managers' => Rule::array(Rule::object())
        ];
    }
}