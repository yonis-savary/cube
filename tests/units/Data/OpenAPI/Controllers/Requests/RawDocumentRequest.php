<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers\Requests;

use Cube\Web\Http\Request;
use Cube\Web\Http\RequestSource;
use Cube\Web\Http\Rules\Rule;
use Cube\Web\Http\Rules\UploadRule;

class RawDocumentRequest extends Request
{
    public function getValidatedSource(): RequestSource
    {
        return RequestSource::RawBody;
    }

    public function getRules(): Rule
    {
        return UploadRule::fromRawBody()->withMimeType('application/pdf');
    }
}
