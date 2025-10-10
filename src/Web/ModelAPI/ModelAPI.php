<?php

namespace Cube\Web\ModelAPI;

use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Database\Query;
use Cube\Data\Database\Query\RawCondition;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use Cube\Utils\Utils;
use Cube\Web\Controller;
use Cube\Web\Router\Route;
use Cube\Web\Router\RouteGroup;
use Cube\Web\Router\Router;

abstract class ModelAPI extends Controller
{
    public const ROUTE_EXTRAS_MODEL_KEY = 'model-api-class';

    public const CREATE = ModelAPIModes::CREATE;
    public const READ = ModelAPIModes::READ;
    public const UPDATE = ModelAPIModes::UPDATE;
    public const DELETE = ModelAPIModes::DELETE;

    protected Model $model;
    protected RouteGroup $group;

    /**
     * @var array<ModelAPIModes>
     */
    protected array $modes;

    public function __construct()
    {
        $modelClass = $this->getModelClass();
        if (!Autoloader::extends($modelClass, Model::class)) {
            throw new \InvalidArgumentException('$modelClass must extends Model class');
        }

        $this->model = new $modelClass();

        $this->group = $this->getRouteGroup();
        $this->modes = $this->getModes();
    }

    /**
     * @return class-string<Model>
     */
    abstract public function getModelClass(): string;

    public function getRouteGroup(): RouteGroup
    {
        return new RouteGroup();
    }

    /**
     * @return array<ModelAPIModes>
     */
    public function getModes(): array
    {
        return [
            self::CREATE,
            self::READ,
            self::UPDATE,
            self::DELETE,
        ];
    }

    public function routes(Router $router): void
    {
        $table = $this->model::table();

        $modes = $this->modes;

        $modelGroup = $this->group->mergeWith(new RouteGroup(
            $table,
            extras: [self::ROUTE_EXTRAS_MODEL_KEY => $this->model::class]
        ));

        $router->group(
            $modelGroup->prefix,
            $modelGroup->middlewares,
            $modelGroup->extras,
            function: function (Router $router) use ($modes) {
                $router->addRoutes(
                    in_array(self::CREATE, $modes) ? Route::post('/', [static::class, 'createItems']) : null,
                    in_array(self::READ, $modes) ? Route::get('/', [static::class, 'readItems']) : null,
                    in_array(self::UPDATE, $modes) ? new Route('/', [static::class, 'updateItem'], ['PUT', 'PATCH']) : null,
                    in_array(self::DELETE, $modes) ? Route::delete('/', [static::class, 'deleteItem']) : null,
                );
            }
        );
    }

    public static function createItems(Request $request)
    {
        $model = static::getModel($request);

        /** @var Model[] $instances */
        $instances = [];

        if ($request->isJSON()) {
            $body = $request->all();

            if (!is_array($body)) {
                return Response::unprocessableContent('Array expected got '.gettype($body));
            }

            $instances = Utils::isList($body)
                ? Bunch::of($body)->map(fn (array $row) => $model::fromArray($row))->toArray()
                : [$model::fromArray($body)];
        } else {
            $instances[] = $model::fromRequest($request);
        }

        foreach ($instances as &$instance) {
            $instance->save();
            $instance = $instance->toArray();
        }

        return Response::json($instances, StatusCode::CREATED);
    }

    public static function readItems(Request $request)
    {
        $model = static::getModel($request);
        $fields = $model::fields();

        $query = $model::select();
        foreach ($request->all() as $fieldName => $value) {
            if (!($field = $fields[$fieldName] ?? false)) {
                continue;
            }

            switch ($field->type) {
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
        $model = static::getModel($request);

        if (!$primaryKey = $model::primaryKey()) {
            throw new \Exception("{$model} model does not have a primary key");
        }

        if (!$primaryKeyValue = $request->param($primaryKey)) {
            return Response::unprocessableContent("Request must have a '{$primaryKey}' parameter");
        }

        if (!$instance = $model::find($primaryKeyValue)) {
            return Response::unprocessableContent("No {$model} with {$primaryKey} = {$primaryKeyValue} found");
        }

        $query = $model::update()->where($primaryKey, $primaryKeyValue);

        foreach ($request->all() as $key => $value) {
            if ($instance::hasField($key)) {
                $query->set($key, $value);
            }
        }

        $query->fetch();

        return $model::find($primaryKeyValue);
    }

    public static function deleteItem(Request $request)
    {
        $model = static::getModel($request);

        if (!$primaryKey = $model::primaryKey()) {
            throw new \Exception("{$model} model does not have a primary key");
        }

        if (!$primaryKeyValue = $request->param($primaryKey)) {
            return Response::unprocessableContent("Request must have a '{$primaryKey}' parameter");
        }

        if (!$instance = $model::find($primaryKeyValue, false)) {
            return Response::unprocessableContent("No {$model} with {$primaryKey} = {$primaryKeyValue} found");
        }

        $model::delete()->where($primaryKey, $primaryKeyValue)->fetch();

        return Response::ok();
    }

    protected static function getModel(Request $request): Model
    {
        $modelClass = $request->getRoute()->getExtras()[self::ROUTE_EXTRAS_MODEL_KEY] ?? false;

        return new $modelClass();
    }

    protected static function makeSearchQuery(Query &$query, string $fieldName, mixed $value, string $comparisonKeywork="LIKE")
    {
        $database = Database::getInstance();

        $conditions = Bunch::fromExplode(' ', (string) $value)
            ->map(fn ($word) => "%{$word}%")
            ->map(fn ($word) => $database->build("`{$fieldName}` $comparisonKeywork {}", [$word]))
            ->join(' AND ')
        ;

        $query->conditions[] = new RawCondition($conditions);
    }
}
