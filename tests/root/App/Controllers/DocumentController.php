<?php

namespace App\Controllers;

use App\Controllers\Requests\StoreDocumentRequest;
use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Http\Response;
use YonisSavary\Cube\Http\StatusCode;
use YonisSavary\Cube\Http\Upload;
use YonisSavary\Cube\Web\Controller;
use YonisSavary\Cube\Web\Route;
use YonisSavary\Cube\Web\Router;

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