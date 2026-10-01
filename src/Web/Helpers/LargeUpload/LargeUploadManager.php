<?php 

namespace Cube\Web\Helpers\LargeUpload;

use Cube\Core\Component;
use Cube\Env\Storage;

class LargeUploadManager
{
    use Component;

    protected LargeUploadManagerConfiguration $configuration;

    public function __construct(LargeUploadManagerConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    private function getStorage(): Storage
    {
        return Storage::getInstance()->child($this->configuration->storageName);
    }

    public function start(): LargeUpload
    {
        $identifier = 'largeupload-'.bin2hex(random_bytes(16));
        return new LargeUpload($identifier, $this->getStorage());
    }

    public function find(string $identifier): ?LargeUpload
    {
        $storage = $this->getStorage();

        if (!$this->isIdentifier($identifier) || !$storage->isDirectory($identifier))
            return null;

        return new LargeUpload($identifier, $storage);
    }

    public function delete(string $identifier): bool 
    {
        $storage = $this->getStorage();

        if (!$this->isIdentifier($identifier) || !$storage->isDirectory($identifier))
            return false;

        $upload = new LargeUpload($identifier, $storage);
        $upload->delete();
        return true;
    }

    protected function isIdentifier(string $identifier): bool
    {
        return 1 === preg_match('/^[\w-]+(\.[\w-]+)*$/', $identifier);
    }
}