<?php

namespace Cube\Web;

use Cube\Core\Autoloader;
use Cube\Http\Request;
use Cube\Http\Response;
use Cube\Http\StatusCode;
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
