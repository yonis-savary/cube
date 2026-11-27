<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers;

use Cube\Data\OpenAPI\Attributes\ResourceResponse;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\AvatarRequest;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\IdListRequest;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\RawDocumentRequest;
use Cube\Tests\Units\Web\Classes\AlbumDatesResource;

class BodyController
{
    public function storeAvatar(AvatarRequest $request) {}

    public function deleteMany(IdListRequest $request) {}

    public function searchByIds(IdListRequest $request) {}

    public function storeDocument(RawDocumentRequest $request) {}

    #[ResourceResponse(AlbumDatesResource::class)]
    public function albumDates() {}
}
