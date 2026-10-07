<?php

namespace Cube\Data\Models\Relations;

use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Models\Model;

/**
 * Subquery resolver to fix the N+1 problem
 * Idea is: table B.fk points to table A.pk
 * We fetch every B where fk in (A.pk), and map array of A relations with B mapped by A.pk
 */
class RelationResolver
{
    /** No HasOne, already resolved by Query */
    public function __construct(
        protected HasMany $relation,
        protected array $accArray,
        protected array $treeToExplore
    ) {}

    /**
     * @param Model[] $data
     */
    public function enrichData(array $data, ?Database $database = null): void
    {
        $fromKey = $this->relation->fromColumn;
        $toKey = $this->relation->toColumn;
        $toModel = $this->relation->toModel;

        $owners = Bunch::of($data)
            ->when(
                count($this->accArray),
                fn(Bunch $owners) => $owners->key(join('.', $this->accArray))
            );

        $keys = $owners
            ->key($fromKey)
            ->filter()
            ->uniques()
            ->values();

        $targetData = $toModel::select()
            ->exploreTree($toModel, $this->treeToExplore, $toModel::table())
            ->where($toKey, $keys)
            ->toBunch($database)
            ->groupBy(fn (Model $el) => $el->$toKey);

        foreach ($owners->get() as $owner) {
            $owner->setReference($this->relation->relationName, $targetData[$owner->$fromKey] ?? []);
        }
    }
}
