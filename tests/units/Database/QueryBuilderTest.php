<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Database\Builders\MySQL;
use Cube\Data\Database\Builders\Postgres;
use Cube\Data\Database\Builders\QueryBuilder;
use Cube\Data\Database\Builders\SQLite;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class QueryBuilderTest extends TestCase
{
    /** @return array<string,array{QueryBuilder,string,string}> */
    public static function getQuotedIdentifiers(): array
    {
        return [
            'mysql' => [new MySQL(), 'product', '`product`'],
            'mysql delimiter' => [new MySQL(), 'odd`table', '`odd``table`'],
            'pgsql' => [new Postgres(), 'product', '"product"'],
            'pgsql delimiter' => [new Postgres(), 'odd"table', '"odd""table"'],
            'sqlite' => [new SQLite(), 'product', '`product`'],
            'sqlite delimiter' => [new SQLite(), 'odd`table', '`odd``table`'],
        ];
    }

    #[DataProvider('getQuotedIdentifiers')]
    public function testQuoteWrapsAndEscapesTheIdentifier(QueryBuilder $builder, string $identifier, string $expected)
    {
        $this->assertSame($expected, $builder->quote($identifier));
    }
}
