<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers;

use Cube\Data\OpenAPI\Attributes\Endpoint;
use Cube\Data\OpenAPI\Attributes\Hidden;
use Cube\Data\OpenAPI\Attributes\ModelResponse;
use Cube\Data\OpenAPI\Attributes\NoContentResponse;
use Cube\Data\OpenAPI\Attributes\ObjectResponse;
use Cube\Data\OpenAPI\Attributes\RawBody;
use Cube\Data\OpenAPI\Attributes\RawResponse;
use Cube\Data\OpenAPI\Attributes\ResourceResponse;
use Cube\Data\OpenAPI\Attributes\ResourceSchema;
use Cube\Tests\Units\Web\Classes\UserResource;
use Cube\Tests\Units\Web\Classes\UserWithLoadedTypeResource;
use Cube\Tests\Units\Web\Classes\UserWithTypeResource;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\AnyValueRequestFormat;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\CustomRequestFormat;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\EnumRequestFormat;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\ListRequestFormat;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\QueryRequestFormat;
use Cube\Tests\Units\Data\OpenAPI\Models\Album;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Models\User;
use Cube\Tests\Units\Models\UserType;
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

    #[Hidden]
    public function internalEndpoint() {

    }

    #[ResourceResponse(UserResource::class, isArray: true)]
    public function usersAsResource() {

    }

    #[ResourceResponse(UserWithTypeResource::class)]
    public function userWithItsType() {

    }

    #[ResourceResponse(UserWithLoadedTypeResource::class, with: ['_type'])]
    public function userWithItsLoadedType() {

    }

    #[ResourceResponse(UserWithLoadedTypeResource::class)]
    public function userWithoutItsType() {

    }

    #[ModelResponse(User::class, with: ['_type', 'modules._module'])]
    public function userWithItsRelations() {

    }

    public function simpleSlugEndpoint(Request $request, float $firstSlug) {

    }

    /** Test type union, should uses 'User' */
    public function modelSlugEndpoint(Request $request, User|string $user) {

    }

    public function postEndpointWithCustomRequest(CustomRequestFormat $request) {

    }

    public function patchEndpointWithCustomRequest(CustomRequestFormat $request) {

    }

    public function putEndpointWithCustomRequest(CustomRequestFormat $request) {

    }

    public function typedSlugsEndpoint() {

    }

    public function slugNamedDifferentlyEndpoint(Request $request, int $identifier) {

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

    public function postEndpointWithListRequest(ListRequestFormat $request) {

    }

    public function getEndpointWithEnumRequest(EnumRequestFormat $request) {

    }

    #[ModelResponse(Album::class)]
    public function endpointReturningAnAlbum() {

    }

    public function postEndpointWithAnyValueRequest(AnyValueRequestFormat $request) {

    }

    #[RawResponse(['nested' => ['value' => null], 'list' => [null]])]
    public function endpointReturningUntypedRawData() {

    }

    #[ObjectResponse(['items' => [Album::class], 'total' => 0])]
    public function endpointReturningAPageOfAlbums() {

    }

    #[RawResponse(['model' => Album::class])]
    public function endpointReturningAModelClassName() {

    }

    #[RawBody(description: 'A chunk of the uploaded file')]
    public function endpointReceivingAChunk(Request $request) {

    }

    #[RawResponse(mimeType: 'audio/mpeg')]
    public function endpointStreamingATrack() {

    }

    #[ModelResponse(Album::class)]
    #[NoContentResponse('The album has no cover')]
    #[RawResponse(['error' => 'Album is locked'], responseCode: 409)]
    #[RawResponse(['error' => 'Album is archived'], responseCode: 410)]
    public function endpointWithSeveralResponses() {

    }

    #[ObjectResponse([
        'owner' => UserResource::class,
        'members' => [UserResource::class],
        'admins' => [new ResourceSchema(UserWithLoadedTypeResource::class, with: ['_type'])],
        'type' => UserType::class,
        'total' => 0,
    ])]
    public function endpointReturningAUserDirectory() {

    }
}
