<?php

namespace YonisSavary\Cube\Models\Relations;

use YonisSavary\Cube\Models\Model;
use YonisSavary\Cube\Utils\Text;

/**
 * @property Model|string $model
 * @property Model|string $fromModel
 * @property Model|string $toModel
 */
class HasMany implements Relation
{
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
        return Text::endsWith(str_replace(
            strtolower(basename(str_replace("\\", "/", $this->fromModel))),
            '',
            strtolower(basename(str_replace("\\", "/", $this->toModel)))
        ), 's');
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
            $model->data->$toColumn = $thisModel->data->$fromColumn;
            $model->save();
        });

        $thisModel->pushReference($this->getName(), $model);

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
            $toModel::select()->where($toColumn, $thisModel->$fromColumn)->fetch()
        );
    }
}