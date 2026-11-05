<?php

namespace Cube\Tests\Units\Data\DataToObject\Generator;

use Symfony\Component\Process\Process;

trait LintsGeneratedFiles
{
    /**
     * Lints every file before anything requires it : an invalid one fails the test instead of the process.
     *
     * @param string[] $files
     */
    protected function assertGeneratedFilesAreValid(array $files): void
    {
        $this->assertContainsOnlyString($files);

        foreach ($files as $file) {
            $lint = new Process([PHP_BINARY, '-l', $file]);
            $lint->run();

            $this->assertTrue($lint->isSuccessful(), $lint->getOutput().$lint->getErrorOutput().file_get_contents($file));
        }
    }
}
