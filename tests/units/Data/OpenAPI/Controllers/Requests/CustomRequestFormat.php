<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers\Requests;

use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;

class CustomRequestFormat extends Request
{
    public function getRules(): array|Rule
    {
        return [
            'some-array' => Param::array(
                Param::object([
                    'id' => Param::integer(),
                    'name' => Param::string(),
                    'age' => Param::integer()->isBetween(0, 120),
                    'last_login' => Param::datetime()
                ])
            ),
            'some-boolean' => Param::boolean(),
            'some-float' => Param::float(),
            'some-date' => Param::date()->isBetween('2000-01-01', '2099-12-31')
        ];
    }
}