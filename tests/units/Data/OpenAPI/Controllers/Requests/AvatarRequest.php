<?php

namespace Cube\Tests\Units\Data\OpenAPI\Controllers\Requests;

use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\UploadRule;

class AvatarRequest extends Request
{
    public function getRules(): array
    {
        return ['avatar' => UploadRule::fromBase64()];
    }
}
