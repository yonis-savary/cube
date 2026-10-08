<?php

namespace Cube\Tests\Units\Core;

use Cube\Core\Exceptions\ImplementationNotFoundException;
use Cube\Utils\Implementations;
use Cube\Data\Database\Builders\MySQL;
use Cube\Data\Database\Builders\QueryBuilder;
use Cube\Tests\Units\Core\Classes\Exporters\CsvExporter;
use Cube\Tests\Units\Core\Classes\Exporters\InvoiceExporter;
use Cube\Tests\Units\Core\Classes\Exporters\XlsxExporter;
use PHPUnit\Framework\TestCase;

class ImplementationsTest extends TestCase
{
    protected function setUp(): void
    {
        CsvExporter::$built = 0;
        XlsxExporter::$built = 0;
    }

    public function testFindGivesTheImplementationOfAnInterfaceThatSupportsTheCase()
    {
        $exporter = Implementations::find(InvoiceExporter::class, fn (string $class) => $class::supports('xlsx'));

        $this->assertInstanceOf(XlsxExporter::class, $exporter);
    }

    public function testFindGivesTheSubclassThatSupportsTheCase()
    {
        $builder = Implementations::find(QueryBuilder::class, fn (string $class) => $class::supports('mysql'));

        $this->assertInstanceOf(MySQL::class, $builder);
    }

    public function testOnlyTheChosenImplementationIsBuilt()
    {
        Implementations::find(InvoiceExporter::class, fn (string $class) => $class::supports('csv'));

        $this->assertEquals(1, CsvExporter::$built);
        $this->assertEquals(0, XlsxExporter::$built);
    }

    public function testConstructorArgumentsReachTheImplementation()
    {
        $exporter = Implementations::find(InvoiceExporter::class, fn (string $class) => $class::supports('csv'), [';']);

        $this->assertEquals(';', $exporter->separator);
    }

    public function testFindGivesNullWhenNoImplementationSupportsTheCase()
    {
        $this->assertNull(Implementations::find(InvoiceExporter::class, fn (string $class) => $class::supports('pdf')));
    }

    public function testFindOrFailGivesTheImplementationThatSupportsTheCase()
    {
        $exporter = Implementations::findOrFail(InvoiceExporter::class, fn (string $class) => $class::supports('csv'), for: 'csv');

        $this->assertInstanceOf(CsvExporter::class, $exporter);
    }

    public function testFindAllGivesEveryImplementationThatSupportsTheCase()
    {
        $exporters = Implementations::findAll(InvoiceExporter::class, fn (string $class) => $class::supports('spreadsheet'));

        $this->assertEqualsCanonicalizing([CsvExporter::class, XlsxExporter::class], $exporters->map(fn ($exporter) => $exporter::class)->get());
    }

    public function testFindAllGivesAList()
    {
        foreach (['csv', 'xlsx'] as $format) {
            $exporters = Implementations::findAll(InvoiceExporter::class, fn (string $class) => $class::supports($format));

            $this->assertTrue(array_is_list($exporters->get()), "The exporters supporting [{$format}] are not a list");
        }
    }

    public function testFindAllBuildsNothingWhenNoImplementationSupportsTheCase()
    {
        $this->assertEquals([], Implementations::findAll(InvoiceExporter::class, fn (string $class) => $class::supports('pdf'))->get());
        $this->assertEquals(0, CsvExporter::$built + XlsxExporter::$built);
    }

    public function testAnUnsupportedCaseNamesTheBaseTheSubjectAndTheCandidates()
    {
        try {
            Implementations::findOrFail(InvoiceExporter::class, fn (string $class) => $class::supports('pdf'), for: 'pdf');
            $this->fail('An unsupported case must throw');
        } catch (ImplementationNotFoundException $exception) {
            $this->assertEquals(InvoiceExporter::class, $exception->base);
            $this->assertEqualsCanonicalizing([CsvExporter::class, XlsxExporter::class], $exception->candidates->get());
            $this->assertStringContainsString('supports [pdf]', $exception->getMessage());
            $this->assertStringContainsString(CsvExporter::class, $exception->getMessage());
        }
    }
}
