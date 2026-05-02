<?php

namespace Cube\Data\OpenAPI\Attributes;

use Attribute;
use Cube\Utils\Path;
use Cube\Web\Http\StatusCode;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_METHOD)]
class RawResponse
{
    /**
     * **Either data or file must be given**
     */
    public function __construct(
        public mixed $data=null,
        public mixed $file=null,
        public int $responseCode=StatusCode::OK,
        public ?string $description = null,
        public string $mimeType = "application/json"
    )
    {
        if (is_null($this->data) && is_null($this->file))
            throw new InvalidArgumentException("Either data or file argument must be given");

        if ($file) {
            if (!is_file($file))
                throw new InvalidArgumentException("Given file ($file) is invalid");

            $this->data = json_decode(file_get_contents(Path::relative($file)), true, flags: JSON_THROW_ON_ERROR);
        }

    }
}