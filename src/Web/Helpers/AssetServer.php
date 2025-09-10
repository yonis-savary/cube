<?php

namespace Cube\Web\Helpers;

use Cube\Core\Autoloader;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;

class AssetServer extends WebAPI
{
    public function __construct(
        public readonly string $route = '/assets/{file}'
    ) {}

    public function routes(Router $router): void
    {
        $router->addRoutes(
            new Route($this->route, [self::class, 'serveAsset'], ['GET'])
        );
    }

    public static function serveAsset(Request $request)
    {
        $target = $request->getSlugValues()[0] ?? null;

        if (!$file = self::findAssetFile($target)) {
            return new Response(StatusCode::NOT_FOUND, "[{$target}] file not found");
        }

        return Response::file($file);
    }

    protected static function findAssetFile(string $target): ?string
    {
        $assets = Autoloader::getAssetsFiles();

        foreach ($assets as $file) {
            if (str_ends_with($file, $target)) {
                return $file;
            }
        }

        return null;
    }
}
