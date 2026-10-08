<?php

namespace Cube\Core\Exceptions;

use Cube\Data\Bunch;

class ImplementationNotFoundException extends \RuntimeException
{
    /**
     * @param class-string $base
     * @param Bunch<int,class-string> $candidates
     */
    public function __construct(
        public readonly string $base,
        public readonly Bunch $candidates,
        public readonly ?string $subject = null
    ) {
        $subjectPart = $subject === null
            ? 'the given case'
            : "[{$subject}]";

        $tried = $candidates->join(',') ?: 'none';

        parent::__construct("No implementation of [{$base}] supports {$subjectPart}, tried [{$tried}]");
    }
}
