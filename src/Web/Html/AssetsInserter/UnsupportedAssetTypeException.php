<?php 

namespace Cube\Web\Html\AssetsInserter;

use Exception;

class UnsupportedAssetTypeException extends Exception
{
    public function __construct(string $path)
    {
        parent::__construct("File " . $path . " has an unsupported file type");
    }
}