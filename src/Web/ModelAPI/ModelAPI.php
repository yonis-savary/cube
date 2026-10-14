<?php

namespace Cube\Web\ModelAPI;

use Cube\Data\Bunch;
use Cube\Data\Database\Query;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\Rules\AnyParam;
use Cube\Web\Http\StatusCode;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use Cube\Data\Models\ModelResource;
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

    /**
     * @return ?ModelResource<TModel> What every method answers with, `null` for the whole model
     */
    protected function getModelResource(): ?ModelResource
    {
        return null;
    }

    /**
     * @param TModel $item
     * @return array<string,mixed>
     */
    protected function answer(Model $item): array
    {
        return $this->getModelResource()?->toArray($item) ?? $item->toArray();
    }

    public function createItems(Request $request): Response
    {
        $this->assertDeclaresItsFields($request, __FUNCTION__);

        $model = $this->getModelClass();
        $validated = $request->validated();

        if (!is_array($validated))
            return Response::text('Array expected got '.gettype($validated), StatusCode::UNPROCESSABLE_CONTENT);

        $instances = Bunch::wrap($validated)
            ->map(fn (array $row) => $model::fromArray($row)->save())
            ->map(fn (Model $newRow) => $this->answer($newRow))
            ->toArray();

        return Response::json($instances, StatusCode::CREATED);
    }

    /**
     * A plain `Request` declares no rule : it filters on nothing, since any column (a password hash included) would answer
     *
     * @return array<int,array<string,mixed>>
     */
    public function readItems(Request $request): array
    {
        $model = $this->getModelClass();
        $fields = $model::fields();
        $filters = $this->declaresItsFields($request) ? $request->validated() : [];

        $query = $model::select();
        foreach ($filters as $fieldName => $value) {
            if (!$field = $fields[$fieldName] ?? false)
                continue;

            match ($field->type) {
                ModelField::STRING => self::makeSearchQuery($query, $fieldName, $value, $this->getMaxSearchWords()),
                default => $query->where($fieldName, $value),
            };
        }

        return Bunch::wrap($query->fetch())
            ->map(fn (Model $item) => $this->answer($item))
            ->get();
    }

    /**
     * @param TModel $item
     * @return array<string,mixed>
     */
    public function updateItem(Model $item, Request $request): array
    {
        $this->assertHandles($item);
        $this->assertDeclaresItsFields($request, __FUNCTION__);

        $data = $request->validated();
        unset($data[$item::primaryKey()]);

        $item->merge($data)->save();

        return $this->answer($item::find($item->id()));
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

    protected function declaresItsFields(Request $request): bool
    {
        return !$request->getObjectParam() instanceof AnyParam;
    }

    // Writing what a plain Request carries would let a client set any column : a role, an owner, a password hash
    protected function assertDeclaresItsFields(Request $request, string $method): void
    {
        if (!$this->declaresItsFields($request))
            throw new InvalidArgumentException(static::class."::{$method}() needs a Request subclass whose rules list the writable fields, got a plain ".$request::class);
    }

    protected function assertHandles(Model $item): void
    {
        $modelClass = $this->getModelClass();

        if (!$item instanceof $modelClass)
            throw new InvalidArgumentException("This ModelAPI handles {$modelClass} items, got ".$item::class);

        if (!$item::primaryKey())
            throw new InvalidArgumentException("{$modelClass} model does not have a primary key");
    }

    protected function getMaxSearchWords(): int
    {
        return 8;
    }

    protected static function makeSearchQuery(Query $query, string $fieldName, mixed $value, int $maxWords, string $comparisonKeyword="LIKE")
    {
        $query->whereGroup(fn (Query $query) =>
            Bunch::fromExplode(' ', (string) $value)
                ->filter(fn (string $word) => '' !== $word)
                ->slice(0, $maxWords)
                ->map(Query::escapeLike(...))
                ->forEach(fn (string $word) => $query->where($fieldName, "%{$word}%", $comparisonKeyword))
        );
    }
}
