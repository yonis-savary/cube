<?php

namespace App\Controllers\Requests;

use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Http\Rules\UploadRule;
use YonisSavary\Cube\Utils\File;

class StoreDocumentRequest extends Request
{
    public function getRules(): array
    {
        return [
            "to-upload" => UploadRule::new()->withMimeType("application/json")->withMaxSize(File::KILOBYTES * 5)
        ];
    }
}