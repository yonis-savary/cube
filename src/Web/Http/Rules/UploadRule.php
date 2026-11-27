<?php

namespace Cube\Web\Http\Rules;

use Cube\Utils\File;
use Cube\Web\Http\Upload;

class UploadRule extends Rule
{
    /**
     * @param ?\Closure(self):mixed $reader Adds the steps turning the received value into an `Upload`, before any check
     */
    public function __construct(bool $nullable = false, bool $rejectUploadsWithErrors = true, ?\Closure $reader = null)
    {
        $this->nullable = $nullable;

        if ($reader)
            $reader($this);

        if ($nullable)
            $this->default(null);
        else $this->withCondition(fn (mixed $value) => null !== $value, '{key} upload is needed');

        $this->withValueCondition(fn (mixed $value) => $value instanceof Upload, '{key} must be an uploaded file');

        if ($rejectUploadsWithErrors) {
            $this->withValueCondition(
                fn (Upload $upload) => UPLOAD_ERR_OK === $upload->error,
                fn (Upload $upload) => $upload->getPHPUploadErrorMessage()
            );
        }
    }

    /**
     * A file sent as a base64 string, or a data URI (`data:image/png;base64,...`) whose declared type
     * must match the content. Standard and URL-safe alphabets, with or without padding.
     */
    public static function fromBase64(bool $nullable = false): self
    {
        return new self(
            $nullable,
            reader: fn (self $rule) => $rule
                ->withValueCondition(
                    fn (mixed $value) => null !== self::decodeBase64($value),
                    '{key} must be a base64 encoded file, or a data URI'
                )
                ->withValueCondition(
                    fn (string $value) => self::holdsItsDeclaredType(...self::decodeBase64($value)),
                    fn (string $value) => self::declaredTypeError(...self::decodeBase64($value))
                )
                ->withValueTransformer(fn (string $value) => Upload::fromContent(self::decodeBase64($value)[0], 'base64'))
                ->withMetadata([self::META_TYPE => 'base64'])
        );
    }

    /**
     * The whole body of a request validating `RequestSource::RawBody` : a PDF, an image, any bytes.
     */
    public static function fromRawBody(bool $nullable = false): self
    {
        return new self(
            $nullable,
            reader: fn (self $rule) => $rule
                ->withTransformer(fn (mixed $value) => '' === $value ? null : $value)
                ->withValueCondition(fn (mixed $value) => is_string($value), '{key} must be a raw body')
                ->withValueTransformer(fn (string $value) => Upload::fromContent($value, 'body'))
        );
    }

    /**
     * @return ?array{string, ?string} The decoded bytes and the type a data URI declares, `null` when the value is no base64
     */
    private static function decodeBase64(mixed $value): ?array
    {
        if (!is_string($value))
            return null;
        $declaredType = null;
        // RFC 2397 : data:<type>[;<parameter>=<value>]*;base64,<data>
        if (preg_match('~^data:([\w.+-]+/[\w.+-]+)(?:;[\w.+-]+=[^;,]*)*;base64,~i', $value, $matches)) {
            $declaredType = strtolower($matches[1]);
            $value = substr($value, strlen($matches[0]));
        }

        $bytes = base64_decode(strtr($value, '-_', '+/'), true);

        return false === $bytes || '' === $bytes ? null : [$bytes, $declaredType];
    }

    private static function holdsItsDeclaredType(string $bytes, ?string $declaredType): bool
    {
        return null === $declaredType || self::detectedType($bytes) === $declaredType;
    }

    private static function declaredTypeError(string $bytes, ?string $declaredType): string
    {
        return "The data URI declares [{$declaredType}] but holds [".self::detectedType($bytes).']';
    }

    private static function detectedType(string $bytes): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
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
