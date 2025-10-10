<?php

namespace Cube\Data\Models\Relations;

use Cube\Data\Bunch;
use Cube\Data\Models\Events\SavedModel;
use Cube\Data\Models\Model;
use Cube\Utils\Text;

/**
 * @template TModel of Model
 *
 * @property Model               $model
 * @property class-string<Model> $fromModel
 * @property class-string<Model> $toModel
 */
class HasOne implements Relation
{
    /**
     * @param TModel $model
     */
    public function __construct(
        public readonly string $name,
        public readonly string $fromModel,
        public readonly string $fromColumn,
        public readonly string $toModel,
        public readonly string $toColumn,
        public Model &$model
    ) {}

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
        return $this->name;
    }

    /**
     * @return TModel
     */
    public function bind(Model $model): Model
    {
        $thisModel = &$this->model;
        $fromColumn = $this->fromColumn;
        $toColumn = $this->toColumn;

        $thisModel->onSaved(function (SavedModel $event) use ($model, $thisModel, $toColumn, $fromColumn) {
            if ($model::hasField($toColumn)) {
                $model->data->{$toColumn} = $thisModel->data->{$fromColumn};
            }

            $model->reload($event->database);
            $model->save($event->database);
        });

        $thisModel->setReference($this->getName(), $model);

        return $this->model;
    }

    /**
     * @return TModel
     */
    public function load(): Model
    {
        $thisModel = &$this->model;
        $fromColumn = $this->fromColumn;

        $toModel = $this->toModel;
        $toColumn = $this->toColumn;

        $data = $toModel::findWhere([$toColumn => $thisModel->$fromColumn]);
        $thisModel->setReference($this->getName(), $data);

        return $data;
    }
}
