<?php 

namespace Cube\Web\Html;

use Cube\Core\Autoloader;
use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Web\Html\AssetsInserter\UnsupportedAssetTypeException;
use InvalidArgumentException;

use function Cube\debug;

class AssetsInserter
{
    use Component;

    protected Bunch $assetsFiles;

    public static function getDefaultInstance(): static
    {
        return new self(Autoloader::getAssetsFiles());
    }

    public function __construct(array $assetsFiles)
    {
        $this->assetsFiles = Bunch::of($assetsFiles);
    }

    protected function findAsset(string $assetName): ?string {
        return $this->assetsFiles->first(fn($file) => str_ends_with($file, $assetName));
    }

    public function insert(string $assetName) {
        $path = $this->findAsset($assetName);
        if (!$path)
            throw new InvalidArgumentException("Asset not found [$assetName]");

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $fileContent = file_get_contents($path);

        return match($extension) {
            'css' => "<style>$fileContent</style>",
            'js' => "<script>$fileContent</script>",
            default => throw new UnsupportedAssetTypeException($path)
        };
    }

}