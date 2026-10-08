<?php

namespace Cube\Web\ModelAPI;

use Cube\Data\Bunch;
use Cube\Data\Database\Query;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use Cube\Utils\Utils;
use InvalidArgumentException;

/**
 * @template TModel of Model
 */
abstract class ModelAPI
{
    /**
     * @return class-string<TModel>
     */
    abstract public function getModelClass(): string;

    /**
     * @template TForModel of Model
     * @param class-string<TForModel> $modelClass
     * @return ModelAPI<TForModel>
     */
    public static function forModel(string $modelClass): ModelAPI
    {
        return new class($modelClass) extends ModelAPI {
            public function __construct(private string $modelClass) {}

            public function getModelClass(): string
            {
                return $this->modelClass;
            }
        };
    }

    public function createItems(Request $request): Response
    {
        $model = $this->getModelClass();

        /** @var Model[] $instances */
        $instances = [];

        if ($request->isJSON()) {
            $body = $request->all();

            if (!is_array($body)) {
                return Response::text('Array expected got '.gettype($body), StatusCode::UNPROCESSABLE_CONTENT);
            }

            $instances = Utils::isList($body)
                ? Bunch::of($body)
                    ->map(fn (array $row) => $model::fromArray($row))
                    ->toArray()
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

    /**
     * @return TModel[]
     */
    public function readItems(Request $request): array
    {
        $model = $this->getModelClass();
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

    /**
     * @param TModel $item
     * @return TModel
     */
    public function updateItem(Model $item, Request $request): Model
    {
        $this->assertHandles($item);

        $data = $request->all();
        if ($primaryKey = $item::primaryKey())
            unset($data[$primaryKey]);

        $item->patch($data);

        return $item::find($item->id());
    }

    /**
     * @param TModel $item
     */
    public function deleteItem(Model $item): Response
    {
        $this->assertHandles($item);

        $item->destroy();

        return Response::ok();
    }

    protected function assertHandles(Model $item): void
    {
        $modelClass = $this->getModelClass();

        if (!$item instanceof $modelClass)
            throw new InvalidArgumentException("This ModelAPI handles {$modelClass} items, got ".$item::class);

        if (!$item::primaryKey())
            throw new InvalidArgumentException("{$modelClass} model does not have a primary key");
    }

    protected static function makeSearchQuery(Query $query, string $fieldName, mixed $value, string $comparisonKeyword="LIKE")
    {
        $query->whereGroup(fn (Query $query) =>
            Bunch::fromExplode(' ', (string) $value)
                ->forEach(fn ($word) => $query->where($fieldName, "%{$word}%", $comparisonKeyword))
        );
    }
}
