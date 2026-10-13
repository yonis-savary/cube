<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Database\Migration\Plans\DryRunPlan;
use Cube\Data\Database\Migration\Plans\Exceptions\DryRunPlanException;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DryRunPlanTest extends TestCase
{
    protected DryRunPlan $plan;

    /** @var class-string<Model> */
    protected string $agencyModel;

    protected function setUp(): void
    {
        $this->plan = new DryRunPlan((new SQLiteProvider())->getEmptyDatabase());
        $this->plan->create('invoice', [ModelField::id()]);

        $this->agencyModel = (new class extends Model {
            public static function table(): string { return 'billing_agency'; }
            public static function fields(): array { return ['id' => ModelField::id()]; }
            public static function relations(): array { return []; }
        })::class;
    }

    public function testAReferenceToAnExistingTableIsChecked()
    {
        $this->plan->create('billing_agency', [ModelField::id()]);

        $this->plan->addColumn('invoice', ModelField::integer('agency')->references('billing_agency', 'id'));

        $this->expectException(DryRunPlanException::class);
        $this->plan->addColumn('invoice', ModelField::integer('agency_code')->references('billing_agency', 'code'));
    }

    public function testAModelWhoseTableIsNotCreatedYetIsCheckedThroughItsFields()
    {
        $this->plan->addColumn('invoice', ModelField::integer('agency')->references($this->agencyModel, 'id'));

        $this->expectException(DryRunPlanException::class);
        $this->plan->addColumn('invoice', ModelField::integer('agency_code')->references($this->agencyModel, 'code'));
    }

    public function testAReferenceToNeitherATableNorAModelIsRefused()
    {
        $this->expectException(DryRunPlanException::class);
        $this->plan->addColumn('invoice', ModelField::integer('agency')->references('billing_agency', 'id'));
    }
}
