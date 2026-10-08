<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers;

use Cube\Data\OpenAPI\Attributes\Endpoint;
use Cube\Data\OpenAPI\Attributes\ModelResponse;
use Cube\Data\OpenAPI\Attributes\RawResponse;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\CustomRequestFormat;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\QueryRequestFormat;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Models\User;
use Cube\Web\Controller;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;

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

    public function getEndpointWithQueryRequest(QueryRequestFormat $request) {

    }

    #[ModelResponse(Product::class)]
    public function endpointReturningAProduct(Request $request, int $product) {

    }

    #[ModelResponse(Product::class)]
    public function endpointReadingAProductSlug(Request $request, Product $product) {

    }

    public function endpointTypedAsAProduct(): Product {
        return new Product();
    }

    public function endpointTypedAsAnOptionalProduct(): ?Product {
        return null;
    }

    public function endpointTypedAsVoid(): void {

    }

    public function endpointTypedAsAResponse(): Response {
        return Response::ok();
    }

    #[ModelResponse(Product::class, description: 'A product from its attribute')]
    public function endpointTypedAndDescribedAsAProduct(): Product {
        return new Product();
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

    #[RawResponse(['items' => []])]
    public function endpointReturningAnEmptyList() {

    }

}