<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers\Requests;

use Cube\Web\Http\Request;
use Cube\Web\Http\RequestSource;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;

class IdListRequest extends Request
{
    public function getValidatedSource(): RequestSource
    {
        return RequestSource::Body;
    }

    public function getRules(): Rule
    {
        return Param::array(Param::integer());
    }
}
