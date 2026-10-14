<?php

namespace Cube\Data\Database\Migration;

use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Plans\DryRunPlan;
use Throwable;

abstract class Migration
{
    public function up(Plan $plan, Database $database) {}
    public function down(Plan $plan, Database $database) {}

    public function dryRun(Database $database): ?Throwable {
        return $this->tryOnDryRunPlan($this->up(...), $database);
    }

    public function execute(Plan $plan, Database $database): ?Throwable {
        return $this->apply($this->up(...), $plan, $database);
    }

    public function revert(Plan $plan, Database $database): ?Throwable {
        return $this->apply($this->down(...), $plan, $database);
    }

    /**
     * @param \Closure(Plan,Database):mixed $step
     */
    protected function apply(\Closure $step, Plan $plan, Database $database): ?Throwable {
        if ($error = $database->dryRun(fn() => $this->tryOnDryRunPlan($step, $database)))
            return $error;

        return $database->transaction(fn() => $step($plan, $database));
    }

    /**
     * @param \Closure(Plan,Database):mixed $step
     */
    protected function tryOnDryRunPlan(\Closure $step, Database $database): ?Throwable {
        try {
            $step(new DryRunPlan($database), $database);
            return null;
        } catch (Throwable $error) {
            return $error;
        }
    }
}
