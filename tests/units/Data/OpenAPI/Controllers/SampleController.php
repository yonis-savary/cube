<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers;

use Cube\Data\OpenAPI\Attributes\Endpoint;
use Cube\Data\OpenAPI\Attributes\ModelResponse;
use Cube\Data\OpenAPI\Attributes\RawResponse;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\CustomRequestFormat;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Models\User;
use Cube\Web\Controller;
use Cube\Web\Http\Request;

class SampleController extends Controller
{
    public function simpleRoute() {

    }

    #[Endpoint("Call simple endpoint", "Some Simple Description !")]
    public function simpleEndpoint() {

    }

    public function simpleSlugEndpoint(Request $request, float $firstSlug) {

    }

    /** Test type union, should uses 'User' */
    public function modelSlugEndpoint(Request $request, User|string $user) {

    }

    public function postEndpointWithCustomRequest(CustomRequestFormat $request) {

    }

    #[ModelResponse(Product::class)]
    public function endpointReturningAProduct(Request $request, int $product) {

    }

    #[ModelResponse(Product::class, true)]
    public function endpointReturningAListOfProducts() {

    }


    #[RawResponse([
        'first-key' => [1,2,3],
        'second-key' => ['some' => ['object', '/', 'array']],
        'third-key' => ['a-flag' => true]
    ])]
    public function endpointReturningARawDataType() {

    }


    #[RawResponse(file: __DIR__ . '/../Fixtures/RawResponseSource.json')]
    public function endpointReturningAFileDataType() {

    }

}