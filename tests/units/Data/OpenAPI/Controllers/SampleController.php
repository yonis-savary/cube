<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers;

use Cube\Data\OpenAPI\Attributes\Endpoint;
use Cube\Data\OpenAPI\Attributes\ModelResponse;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\CustomRequestFormat;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Models\User;
use Cube\Web\Controller;
use Cube\Web\Http\Request;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;

class SampleController extends Controller
{
    public static function simpleRoute() {

    }

    #[Endpoint("Call simple endpoint", "Some Simple Description !")]
    public static function simpleEndpoint() {

    }

    public static function simpleSlugEndpoint(Request $request, float $firstSlug) {

    }

    /** Test type union, should uses 'User' */
    public static function modelSlugEndpoint(Request $request, User|string $user) {

    }

    public static function postEndpointWithCustomRequest(CustomRequestFormat $request) {

    }

    #[ModelResponse(Product::class)]
    public static function endpointReturningAProduct(Request $request, int $product) {

    }

    #[ModelResponse(Product::class, true)]
    public static function endpointReturningAListOfProducts() {

    }
}