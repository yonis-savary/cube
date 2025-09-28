<?php 

namespace Cube\Web\Helpers\LargeUpload;

use Cube\Core\Component;
use Cube\Env\Storage;

class LargeUploadManager
{
    use Component;

    protected LargeUploadManagerConfiguration $configuration;

    public function __construct(?LargeUploadManagerConfiguration $configuration=null)
    {
        $configuration ??= LargeUploadManagerConfiguration::resolve();
        $this->configuration = $configuration;
    }

    private function getStorage(): Storage
    {
        return Storage::getInstance()->child($this->configuration->storageName);
    }

    public function start(): LargeUpload
    {
        $identifier = uniqid("largeupload-", true);
        return new LargeUpload($identifier, $this->getStorage());
    }

    public function find(string $identifier): ?LargeUpload
    {
        $storage = $this->getStorage();

        if (!$storage->isDirectory($identifier))
            return null;

        return new LargeUpload($identifier, $storage);
    }

    public function delete(string $identifier): bool 
    {
        $storage = $this->getStorage();

        if (!$storage->isDirectory($identifier))
            return false;

        $upload = new LargeUpload($identifier, $storage);
        $upload->delete();
        return true;
    }
}