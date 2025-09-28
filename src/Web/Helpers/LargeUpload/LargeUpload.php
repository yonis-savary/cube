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
        protected Storage $parentStorage
    ){
        $this->storage = $parentStorage->child($identifier);

        if (!$this->storage->isFile("info.json")) {
            $this->storage->write("info.json", json_encode([
                "identifier" => $identifier,
                "creation_date" => date("Y-m-d H:i:s")
            ]));
        }
    }

    public function addChunk(int $chunkNumber, string $body) {
        $this->storage->write("chunk-" . $chunkNumber, $body);
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