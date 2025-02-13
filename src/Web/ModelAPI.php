<?php

namespace YonisSavary\Cube\Web;

use Exception;
use InvalidArgumentException;
use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Database\Query;
use YonisSavary\Cube\Database\Query\RawCondition;
use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Http\Response;
use YonisSavary\Cube\Http\StatusCode;
use YonisSavary\Cube\Models\Model;
use YonisSavary\Cube\Models\ModelField;
use YonisSavary\Cube\Utils\Utils;
use YonisSavary\Cube\Web\ModelAPI\ModelAPIConfiguration;
use YonisSavary\Cube\Web\Route;
use YonisSavary\Cube\Web\Router;
use YonisSavary\Cube\Web\Router\Service;

class ModelAPI extends Service
{
    const ROUTE_EXTRAS_MODEL_KEY = 'model-api-class';

    protected Model $model;
    protected ModelAPIConfiguration $configuration;

    public function __construct(
        string $modelClass,
        ?ModelAPIConfiguration $configuration=null
    )
    {
        if (!Autoloader::extends($modelClass, Model::class))
            throw new InvalidArgumentException('$modelClass must extends Model class');

        $this->model = new $modelClass;
        $this->configuration = $configuration ??= ModelAPIConfiguration::resolve();
    }

    public function routes(Router $router): void
    {
        $table = $this->model::table();

        /** @var self $self */
        $self = get_called_class();

        $extras = $this->configuration->routeExtras;
        $extras[self::ROUTE_EXTRAS_MODEL_KEY] = $this->model::class;

        $router->group(
            $table,
            $this->configuration->middlewares,
            $extras,
        function(Router $router) use ($self) {
            $router->addRoutes(
                Route::post("/", [$self, "createItems"]),
                Route::get("/", [$self, "readItems"]),
                new Route("/", [$self, "updateItem"], ['PUT', 'PATCH']),
                Route::delete("/", [$self, "deleteItem"]),
            );
        });
    }

    protected static function getModel(Request $request): Model
    {
        $modelClass = $request->getRoute()->getExtras()[self::ROUTE_EXTRAS_MODEL_KEY] ?? false;
        return new $modelClass();
    }

    public static function createItems(Request $request)
    {
        /** @var Model $model */
        $model = (get_called_class())::getModel($request);

        /** @var Model[] $instances */
        $instances = [];

        if ($request->isJSON())
        {
            $body = $request->all();

            if (!is_array($body))
                return Response::unprocessableContent("Array expected got " . gettype($body));

            $instances = Utils::isList($body) ?
                Bunch::of($body)->map(fn(array $row) => $model::fromArray($row))->toArray():
                $model::fromArray($body);
        }
        else
        {
            $instances[] = $model::fromRequest($request);
        }

        foreach ($instances as &$instance)
        {
            $instance->save();
            $instance = $instance->toArray();
        }

        return Response::json($instances, StatusCode::CREATED);
    }

    protected static function makeSearchQuery(Query &$query, string $fieldName, mixed $value)
    {
        $database = Database::getInstance();

        $conditions = Bunch::fromExplode(' ', (string) $value)
            ->map(fn($word) => "%$word%")
            ->map(fn($word) =>  $database->build("`$fieldName` LIKE {}", [$word]))
            ->join(" AND ");

        $query->conditions[] = new RawCondition($conditions);
    }

    public static function readItems(Request $request)
    {
        /** @var Model $model */
        $model = (get_called_class())::getModel($request);
        $fields = $model::fields();

        $query = $model::select();
        foreach ($request->all() as $fieldName => $value)
        {
            if (!($field = $fields[$fieldName] ?? false))
                continue;

            switch ($field->type)
            {
                case ModelField::STRING:
                    self::makeSearchQuery($query, $fieldName, $value);
                    break;
                default:
                    $query->where($fieldName, $value);
                    break;
            }
        }

        return $query->fetch();
    }

    public static function updateItem(Request $request)
    {
        /** @var Model $model */
        $model = (get_called_class())::getModel($request);

        if (! $primaryKey = $model::primaryKey())
            throw new Exception("$model model does not have a primary key");

        if (! $primaryKeyValue = $request->param($primaryKey))
            return Response::unprocessableContent("Request must have a '$primaryKey' parameter");

        if (! $instance = $model::find($primaryKeyValue))
            return Response::unprocessableContent("No $model with $primaryKey = $primaryKeyValue found");

        $query = $model::update()->where($primaryKey, $primaryKeyValue);

        foreach ($request->all() as $key => $value)
        {
            if ($instance::hasField($key))
                $query->set($key, $value);
        }

        $query->fetch();
        return $model::find($primaryKeyValue);
    }

    public static function deleteItem(Request $request)
    {
        /** @var Model $model */
        $model = (get_called_class())::getModel($request);

        if (! $primaryKey = $model::primaryKey())
            throw new Exception("$model model does not have a primary key");

        if (! $primaryKeyValue = $request->param($primaryKey))
            return Response::unprocessableContent("Request must have a '$primaryKey' parameter");

        if (! $instance = $model::find($primaryKeyValue, false))
            return Response::unprocessableContent("No $model with $primaryKey = $primaryKeyValue found");

        $model::delete()->where($primaryKey, $primaryKeyValue)->fetch();
        return Response::ok();
    }

}