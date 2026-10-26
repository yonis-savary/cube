<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers\Requests;

use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;

class AnyValueRequestFormat extends Request
{
    public function getRules(): array|Rule
    {
        return [
            'payload' => new Param(),
            'tags' => Param::array(new Param()),
            'options' => Param::object([]),
        ];
    }
}
