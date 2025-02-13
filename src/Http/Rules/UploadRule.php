<?php

namespace YonisSavary\Cube\Http\Rules;

use YonisSavary\Cube\Http\Upload;
use YonisSavary\Cube\Utils\File;

class UploadRule extends AbstractRule
{
    protected bool $nullable;

    public function withMimeType(string $type): self
    {
        return $this->withValueCondition(fn(Upload $upload) =>
            mime_content_type($upload->tempName) === $type,
            "File must be of type $type"
        );
    }

    public function withMaxSize(int $bytes): self
    {
        return $this->withValueCondition(
            fn(Upload $upload) => $upload->size <= $bytes,
            fn(Upload $upload) => "Maximum file size is " . File::getPrettySize($bytes) . " got " . File::getPrettySize($upload->size)
        );
    }

    public function __construct(bool $nullable=false, bool $rejectUploadsWithErrors=true)
    {
        $this->nullable = $nullable;

        if (!$nullable)
            $this->withCondition(fn(mixed $value) => $value !== null, "{key} upload is needed");

        if ($rejectUploadsWithErrors)
            $this->withValueCondition(
                fn(Upload $upload) => $upload->error === UPLOAD_ERR_OK,
                fn(Upload $upload) => $upload->getPHPUploadErrorMessage()
            );
    }

    public static function new(bool $nullable=false, bool $rejectUploadsWithErrors=true)
    {
        return new self($nullable, $rejectUploadsWithErrors);
    }
}