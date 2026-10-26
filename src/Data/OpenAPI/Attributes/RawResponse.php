<?php

namespace Cube\Data\OpenAPI\Attributes;

use Attribute;
use Cube\Utils\Path;
use Cube\Web\Http\StatusCode;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class RawResponse
{
    /**
     * **Either data or file must be given** for a JSON response, without them any other mime type is a binary body
     */
    public function __construct(
        public mixed $data=null,
        public mixed $file=null,
        public int $responseCode=StatusCode::OK,
        public ?string $description = null,
        public string $mimeType = "application/json"
    )
    {
        if (is_null($this->data) && is_null($this->file) && $this->isJson())
            throw new InvalidArgumentException("Either data or file argument must be given for a [{$this->mimeType}] response");

        if ($file) {
            $path = Path::relative($file);
            if (!is_file($path))
                throw new InvalidArgumentException("Given file ($file) is invalid, no file at [$path]");

            $this->data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        }

    }

    public function isJson(): bool
    {
        return (bool) preg_match('~^application/(.+\+)?json$~', $this->mimeType);
    }

    public function isBinary(): bool
    {
        return is_null($this->data) && !$this->isJson();
    }
}