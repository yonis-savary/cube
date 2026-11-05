<?php

namespace Cube\Data\Database\Migration;

class FailedMigrationException extends \Exception
{
    protected string $file;

    public function __construct(
        \Throwable $databaseError,
        string $file
    ) {
        parent::__construct($databaseError->getMessage());
        $this->file = $file;
    }
}
