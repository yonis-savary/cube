<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers\Requests;

use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;

class EnumRequestFormat extends Request
{
    public function getRules(): array|Rule
    {
        return [
            'page-size' => Param::integer()->inArray([10, 25, 50]),
        ];
    }
}
