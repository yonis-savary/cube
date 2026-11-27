<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers;

use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\AvatarRequest;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\IdListRequest;
use Cube\Tests\Units\Data\OpenAPI\Controllers\Requests\RawDocumentRequest;

class BodyController
{
    public function storeAvatar(AvatarRequest $request) {}

    public function deleteMany(IdListRequest $request) {}

    public function searchByIds(IdListRequest $request) {}

    public function storeDocument(RawDocumentRequest $request) {}
}
