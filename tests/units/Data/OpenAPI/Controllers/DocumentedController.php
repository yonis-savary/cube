<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers;

use Cube\Data\OpenAPI\Attributes\NoContentResponse;
use Cube\Data\OpenAPI\Attributes\RawResponse;
use Cube\Tests\Units\Models\Product;

#[RawResponse(['error' => 'Forbidden'], responseCode: 403, description: 'Refused by the controller')]
#[RawResponse(['status' => 'ok'], responseCode: 200, description: 'Answered by the controller')]
#[NoContentResponse('Nothing left to answer')]
class DocumentedController
{
    public function undocumented() {}

    #[RawResponse(['error' => 'Forbidden'], responseCode: 403, description: 'Refused by the method')]
    public function refusingByItself() {}

    public function product(): Product
    {
        return new Product();
    }
}
