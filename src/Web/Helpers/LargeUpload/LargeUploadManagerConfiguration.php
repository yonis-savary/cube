<?php 

namespace Cube\Web\Helpers\LargeUpload;

use Cube\Env\Configuration\ConfigurationElement;

class LargeUploadManagerConfiguration extends ConfigurationElement
{
    public function __construct(
        public string $storageName="/large-upload-temp",
        public ?int $maxSize = null
    ){}
}