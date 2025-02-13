<?php

namespace YonisSavary\Cube\Web;

use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Http\Response;
use YonisSavary\Cube\Http\StatusCode;
use YonisSavary\Cube\Web\Router\Service;

class AssetServer extends Service
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            new Route($this->path, [self::class, "serveAsset"], ["GET"])
        );
    }

    public function __construct(
        public readonly string $path="/assets/{file}"
    ){}

    protected static function findAssetFile(string $target): ?string
    {
        $assets = Autoloader::getAssetsFiles();

        foreach ($assets as $file)
        {
            if (str_ends_with($file, $target))
                return $file;
        }

        return null;
    }

    public static function serveAsset(Request $request)
    {
        $target = $request->getSlugValues()[0] ?? null;

        if (!$file = self::findAssetFile($target))
            return new Response(StatusCode::NOT_FOUND, "[$target] file not found");

        return Response::file($file);
    }
}