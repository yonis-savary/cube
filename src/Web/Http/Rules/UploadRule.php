<?php

namespace Cube\Web\Http\Rules;

use Cube\Web\Http\Upload;
use Cube\Utils\File;

class UploadRule extends Rule
{
    protected bool $nullable;

    public function __construct(bool $nullable = false, bool $rejectUploadsWithErrors = true)
    {
        $this->nullable = $nullable;

        if (!$nullable) {
            $this->withCondition(fn (mixed $value) => null !== $value, '{key} upload is needed');
        }

        if ($rejectUploadsWithErrors) {
            $this->withValueCondition(
                fn (Upload $upload) => UPLOAD_ERR_OK === $upload->error,
                fn (Upload $upload) => $upload->getPHPUploadErrorMessage()
            );
        }
    }

    public function withMimeType(string $type): self
    {
        return $this->withValueCondition(
            fn (Upload $upload) => mime_content_type($upload->tempName) === $type,
            "File must be of type {$type}"
        );
    }

    public function withMaxSize(int $bytes): self
    {
        return $this->withValueCondition(
            fn (Upload $upload) => $upload->size <= $bytes,
            fn (Upload $upload) => 'Maximum file size is '.File::getPrettySize($bytes).' got '.File::getPrettySize($upload->size)
        );
    }

    public static function new(bool $nullable = false, bool $rejectUploadsWithErrors = true)
    {
        return new self($nullable, $rejectUploadsWithErrors);
    }
}
