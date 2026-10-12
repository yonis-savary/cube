<?php 

namespace Cube\Web\Helpers\LargeUpload;

use Cube\Data\Bunch;
use Cube\Env\Storage;
use InvalidArgumentException;

class LargeUpload
{
    public readonly Storage $storage;

    public function __construct(
        public readonly string $identifier,
        protected Storage $parentStorage,
        public readonly ?int $maxSize = null
    ){
        $this->storage = $parentStorage->child($identifier);

        if (!$this->storage->isFile("info.json")) {
            $this->storage->write("info.json", json_encode([
                "identifier" => $identifier,
                "creation_date" => date("Y-m-d H:i:s")
            ]));
        }
    }

    /**
     * @return bool `false` when the chunk would take the transfer over `maxSize`, nothing is written then
     */
    public function addChunk(int $chunkNumber, string $body): bool {
        $chunk = "chunk-" . $chunkNumber;

        $nextSize = $this->sizeWithout($chunk) + strlen($body);
        if (null !== $this->maxSize && $nextSize > $this->maxSize)
            return false;

        $this->storage->write($chunk, $body);
        return true;
    }

    protected function sizeWithout(string $chunk): int {
        return Bunch::of($this->storage->files())
            ->filter(fn (string $file) => str_starts_with(basename($file), 'chunk-') && basename($file) !== $chunk)
            ->sum(fn (string $file) => filesize($file));
    }

    public function wrap(Storage $destinationStorage, bool $cleanup=true): string {
        $identifier = $this->identifier;
        if ($destinationStorage->isFile($identifier))
            throw new InvalidArgumentException("Destination storage already contains a $identifier file.");

        $outputPath = $destinationStorage->path($identifier);
        $outputStream = fopen($outputPath, "a");

        $chunkFiles = Bunch::of($this->storage->files())
            ->filter(fn($file) => str_contains($file, 'chunk-'))
            ->sort(SORT_NATURAL);

        $chunkFiles->forEach(fn($file) => fwrite($outputStream, file_get_contents($file)));
        fclose($outputStream);

        if ($cleanup)
            $this->delete();

        return $outputPath;
    }

    public function delete(): void {
        Bunch::of($this->storage->files())
            ->forEach(fn($file) => unlink($file));

        rmdir($this->storage->getRoot());
    }
}