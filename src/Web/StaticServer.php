<?php

namespace YonisSavary\Cube\Web;

use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Http\Response;
use YonisSavary\Cube\Web\Router\Service;

class StaticServer extends Service
{
    protected Storage $directory;
    protected bool $secure;

    protected bool $supportsIndex;
    protected string $indexFile;

    public function __construct(
        string|Storage $directory,
        bool $secure = true,
        bool $supportsIndex = true
    )
    {
        if (is_string($directory))
            $directory = new Storage($directory);

        $this->directory = $directory;
        $this->secure = $secure;

        $indexFile = null;
        if ($supportsIndex)
        {
            foreach (["index.php", "index.html"] as $possibleFile)
            {
                if (!$directory->isFile($possibleFile))
                    continue;

                $indexFile = $directory->path($possibleFile);
            }
            $supportsIndex &= is_string($indexFile);
        }

        $this->indexFile = $indexFile;
        $this->supportsIndex = $supportsIndex;
    }

    protected function isPathDangerous(string $path): bool
    {
        return str_contains($path, "..");
    }


    public function registerFallbackRoute(Router $router): void
    {
        if (!$this->supportsIndex)
            return;

        $directory = $this->directory;
        $indexPath = $directory->path($this->indexFile);

        $router->addRoutes(
            Route::get("/{any:any}", [(get_called_class())::class, 'serveIndexFile'], extras:['file' => $indexPath])
        );
    }

    public static function serveIndexFile(Request $request)
    {
        $file = $request->getRoute()->getExtras()['file'];
        return Response::file($file);
    }

    public function handle(Request $request): mixed
    {
        $path = $request->getPath();
        $directory = $this->directory;

        if ($this->secure && $this->isPathDangerous($path))
            return null;

        if ($directory->isFile($path))
            return Response::file($directory->path($path));

        if ($this->supportsIndex && $path === "/")
            return Response::file($this->indexFile);

        return null;
    }
}