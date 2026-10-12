<?php

namespace Cube\Tests\Units\Web\Examples;

use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;
use Override;

class ProductRequest extends Request
{
    #[Override]
    public function getRules(): array|Rule
    {
        return [
            'name' => Param::string()->optional(),
            'price_dollar' => Param::float(true)->optional(),
        ];
    }
}
