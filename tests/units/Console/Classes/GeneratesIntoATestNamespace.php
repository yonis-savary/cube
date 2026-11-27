<?php

namespace Cube\Tests\Units\Console\Classes;

use Cube\Tests\Units\Data\DataToObject\Generator\LintsGeneratedFiles;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Utils\Path;

/**
 * Gives a generation command a namespace of its own under `Cube\Tests\Generated`, which composer
 * autoloads, and removes what it wrote once the test is done.
 */
trait GeneratesIntoATestNamespace
{
    use HasTemporaryStorage;
    use LintsGeneratedFiles;

    protected ?string $generatedDirectory = null;

    /**
     * Its directory is not created : the command does it.
     */
    protected function generatedNamespace(): string
    {
        // Outside the directories the test Autoloader explores : another worker indexing them would list files this test then deletes
        $root = Path::relative('tests/generated');
        // Without its root, Path::namespaceToPath() cannot resolve the PSR-4 directory and answers composer's raw "../.." path
        if (!is_dir($root))
            mkdir($root, recursive: true);

        $segment = 'Generated'.bin2hex(random_bytes(6));
        $this->generatedDirectory = Path::join($root, $segment);

        return "Cube\\Tests\\Generated\\{$segment}";
    }

    protected function removeGeneratedDirectory(): void
    {
        if ($this->generatedDirectory)
            $this->removeDirectory($this->generatedDirectory);
    }

    /**
     * @return string[] the files a generation command printed, made absolute
     */
    protected function writtenFiles(string $output): array
    {
        return array_map(fn (string $file) => Path::relative($file), array_values(array_filter(explode("\n", $output))));
    }
}
