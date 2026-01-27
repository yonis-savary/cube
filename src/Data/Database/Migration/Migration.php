<?php

namespace Cube\Data\Database\Migration;

use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Plans\DryRunPlan;
use Cube\Data\Database\Migration\Plans\Exceptions\DryRunPlanException;
use Throwable;

abstract class Migration
{
    public function up(Plan $plan, Database $database) {}
    public function down(Plan $plan, Database $database) {}

    public function dryRun(Database $database): ?Throwable {
        $plan = new DryRunPlan($database);
        try {
            $this->up($plan, $database);
            return null;
        } catch (DryRunPlanException $planCrashOut) {
            return $planCrashOut;
        }
    }

    public function execute(Plan $plan, Database $database): ?Throwable {
        if ($error = $database->dryRun(fn() => $this->dryRun($database)))
            return $error;

        return $database->transaction(function() use ($plan, $database) {
            $this->up($plan, $database);
        });
    }
}
