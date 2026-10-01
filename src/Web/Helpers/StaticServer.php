<?php

namespace Cube\Web\Helpers;

use Cube\Data\Bunch;
use Cube\Env\Storage;
use Cube\Utils\Path;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;

class StaticServer extends WebAPI
{
    protected Storage $directory;
    protected bool $secure;

    protected bool $supportsIndex;
    protected ?string $indexFile;

    public function __construct(
        Storage|string $directory,
        bool $secure = true,
        bool $supportsIndex = true
    ) {
        if (is_string($directory)) {
            if (!is_dir($directory))
                $directory = Path::relative($directory);

            $directory = new Storage($directory);
        }

        $this->directory = $directory;
        $this->secure = $secure;

        $indexFile = null;
        if ($supportsIndex) {
            $supportsIndex = $directory->isFile('index.html');
            $indexFile = $supportsIndex ? $directory->path('index.html') : null;
        }

        $this->indexFile = $indexFile;
        $this->supportsIndex = $supportsIndex;
    }

    public function registerFallbackRoute(Router $router): void
    {
        if (!$this->supportsIndex) {
            return;
        }

        $router->addRoutes(
            Route::get('/{any:any}', [static::class, 'serveIndexFile'], extras: ['file' => $this->indexFile])
        );
    }

    public static function serveIndexFile(Request $request): Response
    {
        $file = $request->getRoute()->getExtras()['file'];

        return Response::file($file);
    }

    public function handle(Request $request): Response|Route|null
    {
        $path = $request->getPath();
        $directory = $this->directory;

        if ($this->secure && ($this->isPathDangerous($path) || $this->isPathPrivate($path))) {
            return null;
        }

        if ($directory->isFile($path)) {
            return Response::file($directory->path($path));
        }

        if ($this->supportsIndex && ('/' === $path)) {
            return Response::file($this->indexFile);
        }

        return null;
    }

    protected function isPathPrivate(string $path): bool
    {
        if (str_ends_with(strtolower($path), '.php'))
            return true;

        return Bunch::fromExplode('/', $path)
            ->any(fn (string $segment) => str_starts_with($segment, '.') && '.well-known' !== $segment);
    }

    protected function isPathDangerous(string $path): bool
    {
        $root = realpath($this->directory->getRoot());
        $target = realpath($this->directory->path($path));

        if (!$root || !$target)
            return true;

        return $target !== $root
            && !str_starts_with($target, $root.DIRECTORY_SEPARATOR);
    }
}
