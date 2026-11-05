<?php

namespace App\Controllers\Requests;

use Cube\Utils\File;
use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\UploadRule;

class StoreDocumentRequest extends Request
{
    public function getRules(): array
    {
        return [
            'to-upload' => UploadRule::new()->withMimeType('application/json')->withMaxSize(File::KILOBYTES * 5),
        ];
    }
}
