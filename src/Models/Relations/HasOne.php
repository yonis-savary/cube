<?php

namespace YonisSavary\Cube\Models\Relations;

use YonisSavary\Cube\Models\Model;

/**
 * @template TModel of Model
 *
 * @property class-string<Model> $model
 * @property class-string<Model> $fromModel
 * @property class-string<Model> $toModel
 */
class HasOne implements Relation
{
    /**
     * @param TModel $model
     */
    public function __construct(
        public readonly string $fromModel,
        public readonly string $fromColumn,
        public readonly string $toModel,
        public readonly string $toColumn,
        public Model &$model
    ){}

    public function isSource(string $model, string $column): bool
    {
        return ($model === $this->fromModel) && ($column === $this->fromColumn);
    }

    public function concern(string $model): bool
    {
        return $model === $this->fromModel || $model === $this->toModel;
    }

    public function getName(): string
    {
        return strtolower(basename(str_replace("\\", "/", $this->toModel)));
    }

    /**
     * @return TModel
     */
    public function bind(Model $model): Model
    {
        $thisModel = &$this->model;
        $fromColumn = $this->fromColumn;
        $toColumn = $this->toColumn;

        $thisModel->onSaved(function() use ($model, $thisModel, $toColumn, $fromColumn) {
            if ($model::hasField($toColumn))
                $model->data->$toColumn = $thisModel->data->$fromColumn;

            $model->save();
        });

        $thisModel->setReference($this->getName(), $model);

        return $this->model;
    }

    public function load(): void
    {
        $thisModel = &$this->model;
        $fromColumn = $this->fromColumn;

        $toModel = $this->toModel;
        $toColumn = $this->toColumn;

        $thisModel->setReference(
            $this->getName(),
            $toModel::findWhere([$toColumn => $thisModel->$fromColumn])
        );
    }
}