<?php

namespace Cube\Web\ModelAPI;

use Cube\Core\Exceptions\ResponseException;
use Cube\Data\Bunch;
use Cube\Data\Database\Query;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\Rules\AnyParam;
use Cube\Web\Http\Rules\ObjectParam;
use Cube\Web\Http\Rules\Param;
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
        $validated = $this->validateWrite($request, $this->getImplicitWriteRule());

        if (!is_array($validated))
            return Response::text('Array expected got '.gettype($validated), StatusCode::UNPROCESSABLE_CONTENT);

        $instances = Bunch::of($validated)
            ->map(fn (array $row) => $this->withoutHiddenKeys($row))
            ->map(fn (array $row) => $model::fromArray($row)->save())
            ->map(fn (Model $newRow) => $this->withoutHiddenFields($newRow)->toArray())
            ->toArray();

        return Response::json($instances, StatusCode::CREATED);
    }

    /**
     * @return string[] Columns never sent back, written, nor accepted as a filter (password hashes, tokens...)
     */
    protected function getHiddenFields(): array
    {
        return [];
    }

    /**
     * @return TModel[]
     */
    public function readItems(Request $request): array
    {
        $model = $this->getModelClass();
        $fields = $model::fields();
        $hiddenFields = $this->getHiddenFields();

        $query = $model::select();
        foreach ($request->validated() as $fieldName => $value) {
            if (in_array($fieldName, $hiddenFields, true) || !($field = $fields[$fieldName] ?? false)) {
                continue;
            }

            switch ($field->type) {
                case ModelField::STRING:
                    self::makeSearchQuery($query, $fieldName, $value, $this->getMaxSearchWords());
                    break;

                default:
                    $query->where($fieldName, $value);
                    break;
            }
        }

        return Bunch::of($query->fetch())
            ->map(fn (Model $item) => $this->withoutHiddenFields($item))
            ->get();
    }

    /**
     * @param TModel $item
     * @return TModel
     */
    public function updateItem(Model $item, Request $request): Model
    {
        $this->assertHandles($item);

        $partialRule = $this->getImplicitWriteRule();
        $partialRule->withOptionalKeys(array_keys($partialRule->getRules()));

        $data = $this->withoutHiddenKeys($this->validateWrite($request, $partialRule));
        unset($data[$item::primaryKey()]);

        $item->merge($data)->save();

        return $this->withoutHiddenFields($item::find($item->id()));
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

    /**
     * @param TModel $item
     * @return TModel
     */
    protected function withoutHiddenFields(Model $item): Model
    {
        foreach ($this->getHiddenFields() as $field)
            unset($item->data->{$field});

        return $item;
    }

    protected function withoutHiddenKeys(array $row): array
    {
        return array_diff_key($row, array_flip($this->getHiddenFields()));
    }

    protected function getImplicitWriteRule(): ObjectParam
    {
        $model = $this->getModelClass();

        $hiddenFields = $this->getHiddenFields();
        if ($primaryKey = $model::primaryKey())
            $hiddenFields[] = $primaryKey;

        $modelRules = $model::toObjectParam(withRelations: false)->getRules();

        return Param::object($modelRules)->without($hiddenFields);
    }

    protected function validateWrite(Request $request, ObjectParam $implicitRule): mixed
    {
        if (!$request->getObjectParam() instanceof AnyParam)
            return $request->validated();

        $body = $request->all();
        $rule = is_array($body) && Utils::isList($body)
            ? Param::array($implicitRule)
            : $implicitRule;

        $validation = $rule->validate($body, 'request');
        if ($validation->isValid())
            return $validation->getResult();

        $response = Response::json($validation->getErrors(), StatusCode::UNPROCESSABLE_CONTENT)
            ->withHeaders(['Content-Type' => 'application/json']);

        throw new ResponseException('Given request is not valid', $response);
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
