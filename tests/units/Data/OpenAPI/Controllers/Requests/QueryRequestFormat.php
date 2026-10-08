<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers\Requests;

use Cube\Tests\Units\Models\Product;
use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;

class QueryRequestFormat extends Request
{
    public function getRules(): array|Rule
    {
        return [
            'search' => Param::string(nullable: true),
            'contact' => Param::email(),
            'quantity' => Param::integer()->isBetween(0, 10),
            'status' => Param::string(nullable: true)->inArray(['draft', 'sent']),
            'token' => Param::uuid(),
            'product' => Param::model(Product::class),
        ];
    }
}
