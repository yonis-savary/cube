<?php

namespace App\Controllers;

use App\Controllers\Requests\StoreDocumentRequest;
use Cube\Env\Storage;
use Cube\Http\Response;
use Cube\Http\StatusCode;
use Cube\Http\Upload;
use Cube\Web\Controller;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;

class DocumentController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::post("/documents", [self::class, "uploadDocument"]),
            Route::get ("/documents/{name}", [self::class, "readDocument"])
        );
    }

    public static function uploadDocument(StoreDocumentRequest $request)
    {
        $validated = $request->validated();

        /** @var Upload $upload */
        $upload = $validated["to-upload"];

        $randomName = uniqid("file-") . ".json";

        $upload->move(
            Storage::getInstance(),
            $randomName
        );

        return Response::json($randomName, StatusCode::CREATED);
    }

    public static function readDocument($_, string $name)
    {
        $store = Storage::getInstance();

        if (!$store->isFile($name))
            return Response::notFound();

        return Response::file($store->path($name));
    }
}