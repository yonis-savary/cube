<?php

namespace Cube\Tests\Units\Data;

use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class BunchTest extends TestCase
{
    use TestMultipleDrivers;

    public function testOf()
    {
        $this->assertEquals([], Bunch::of([])->get());
        $this->assertEquals([1, 2, 3], Bunch::of([1, 2, 3])->get());
        $this->assertEquals([['A' => 0]], Bunch::of(['A' => 0])->get());
        $this->assertEquals(['test'], Bunch::of('test')->get());
    }

    public function testFill()
    {
        $this->assertEquals([0, 0, 0], Bunch::fill(3, 0)->get());
        $this->assertEquals([null], Bunch::fill(1, null)->get());
        $this->assertEquals([], Bunch::fill(0, null)->get());
    }

    public function testRange()
    {
        $this->assertEquals([1, 2, 3, 4, 5], Bunch::range(5)->get());
        $this->assertEquals([0, 2, 4, 6, 8, 10], Bunch::range(10, 0, 2)->get());
    }

    public function testFromValues()
    {
        $this->assertEquals([0, 1, 2], Bunch::fromValues(['A' => 0, 'B' => 1, 'C' => 2])->get());
        $this->assertEquals([455, 1024, 3002], Bunch::fromValues([455, 1024, 3002])->get());
    }

    public function testFromKeys()
    {
        $this->assertEquals(['A', 'B', 'C'], Bunch::fromKeys(['A' => 0, 'B' => 1, 'C' => 2])->get());
        $this->assertEquals([0, 1, 2], Bunch::fromKeys([455, 1024, 3002])->get());
    }

    public function testUnzip()
    {
        $this->assertEquals([
            ['A', 0],
            ['B', 1],
            ['C', 2],
        ], Bunch::unzip(['A' => 0, 'B' => 1, 'C' => 2])->get());
    }

    public function testClone()
    {
        $first = Bunch::of([1, 2, 3]);
        $second = clone $first;

        $first->push(4);

        $this->assertCount(4, $first->get());
        $this->assertCount(3, $second->get());
    }

    public function testGet()
    {
        // Implicitly tested by other tests
        // Same for toArray() which is an alias of get()
        $this->assertEquals([1, 2, 3], Bunch::of([1, 2, 3])->get());
    }

    public function testAsIntegers()
    {
        $this->assertEquals([1, -2, 3], Bunch::of(['1', '-2', '3.1416'])->asIntegers()->get());
        $this->assertEquals([1, -2, 3], Bunch::of(['1', null, '-2', '3.1416'])->asIntegers()->get());
        $this->assertEquals([1, -2, 3], Bunch::of(['1', 'Hello World', '-2', '3.1416'])->asIntegers()->get());
        $this->assertEquals([1, 12, -2, 3], Bunch::of(['1', 12, '-2', '3.1416'])->asIntegers()->get());
    }

    public function testAsFloats()
    {
        $this->assertEquals([1, -2, 3.1416], Bunch::of(['1', '-2', '3.1416'])->asFloats()->get());
        $this->assertEquals([1, -2, 3.1416], Bunch::of(['1', null, '-2', '3.1416'])->asFloats()->get());
        $this->assertEquals([1, -2, 3.1416], Bunch::of(['1', 'Hello World', '-2', '3.1416'])->asFloats()->get());
        $this->assertEquals([1, 12, -2, 3.1416], Bunch::of(['1', 12, '-2', '3.1416'])->asFloats()->get());
    }

    public function testFilter()
    {
        $this->assertEquals([0, 2, 4, 6, 8], Bunch::of([0, 1, 2, 3, 4, 5, 6, 7, 8, 9])->filter(fn ($x) => 0 == $x % 2)->get());
    }

    public function testPartitionFilter()
    {
        list($group1, $group2, $group3) = Bunch::of([
            '1-paris',
            '2-berlin',
            '3-rome',
            '4-tokyo',
            '5-oulan-bator',
            '6-washington',
        ])->partitionFilter(fn ($x) => floor((intval(substr($x, 0, 1)) - 1) / 2));

        $this->assertEquals(['1-paris', '2-berlin'], $group1);
        $this->assertEquals(['3-rome', '4-tokyo'], $group2);
        $this->assertEquals(['5-oulan-bator', '6-washington'], $group3);
    }

    public function testMap()
    {
        $this->assertEquals(
            [0, 1, 8, 27],
            Bunch::of([0, 1, 2, 3])
                ->map(fn ($x) => pow($x, 3))
                ->get()
        );
    }

    public function testMerge()
    {
        $first = Bunch::of([0, 1, 2]);
        $second = Bunch::of([3, 4, 5]);
        $third = [6, 7, 8, 9];

        $first->merge($second)->merge($third);

        $this->assertEquals([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], $first->get());
    }

    public function testSort()
    {
        $target = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
        $set = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];

        for ($i = 0; $i < 100; ++$i) {
            shuffle($set);
            $this->assertNotEquals($set, $target);
            $this->assertEquals(Bunch::of($set)->sort()->get(), $target);
        }

        $elements = [['A' => 3], ['A' => 2], ['A' => 5], ['A' => 12], ['A' => -3], ['A' => 0]];

        $sorted = Bunch::of($elements)->sort(fn ($x) => $x['A'])->get();

        $this->assertEquals(
            [['A' => -3], ['A' => 0], ['A' => 2], ['A' => 3], ['A' => 5], ['A' => 12]],
            $sorted
        );
    }

    public function testForEach()
    {
        $sum = 0;
        Bunch::of([1000, 100, 10, 1])
            ->forEach(function ($x) use (&$sum) {
                $sum += $x;
            })
        ;
        $this->assertEquals(1111, $sum);
    }

    public function testAny()
    {
        $elements = [['A' => 3], ['A' => 2], ['A' => 5], ['A' => 12], ['A' => -3], ['A' => 0]];
        $elements = Bunch::of($elements);

        $this->assertTrue($elements->any(fn ($x) => 3 === $x['A']));
        $this->assertFalse($elements->any(fn ($x) => 5292 === $x['A']));
    }

    public function testAll()
    {
        $elements = [['A' => 3], ['A' => 2], ['A' => 5], ['A' => 12], ['A' => -3], ['A' => 0]];
        $elements = Bunch::of($elements);

        $this->assertTrue($elements->all(fn ($x) => $x['A'] > -30));
        $this->assertFalse($elements->all(fn ($x) => $x['A'] > 0));
    }

    public function testUniques()
    {
        $this->assertEquals(
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            Bunch::of([0, 1, 2, 1, 1, 3, 4, 5, 6, 7, 5, 5, 8, 0, 9])->uniques()->get()
        );
    }

    public function testPush()
    {
        $this->assertEquals([1, 2, 3, 'A', 'B', 'C'], Bunch::of([1, 2, 3])->push('A', 'B')->push('C')->get());
    }

    public function testUnshift()
    {
        $this->assertEquals(['A', 'B', 'C', 1, 2, 3], Bunch::of([1, 2, 3])->unshift('B', 'C')->unshift('A')->get());
    }

    public function testPop()
    {
        $this->assertEquals([1, 2], Bunch::of([1, 2, 3])->pop(1)->get());
        $this->assertEquals([1], Bunch::of([1, 2, 3])->pop(2)->get());
        $this->assertEquals([], Bunch::of([1, 2, 3])->pop(3)->get());
        $this->assertEquals([], Bunch::of([1, 2, 3])->pop(12)->get());
    }

    public function testShift()
    {
        $this->assertEquals([2, 3], Bunch::of([1, 2, 3])->shift(1)->get());
        $this->assertEquals([3], Bunch::of([1, 2, 3])->shift(2)->get());
        $this->assertEquals([], Bunch::of([1, 2, 3])->shift(3)->get());
        $this->assertEquals([], Bunch::of([1, 2, 3])->shift(12)->get());
    }

    public function testJoin()
    {
        $this->assertEquals('Hello World !', Bunch::of(['Hello', 'World', '!'])->join(' '));
    }

    public function testFirst()
    {
        $elements = [['A' => 3], ['A' => 2], ['A' => 5], ['A' => 12], ['A' => -3], ['A' => 0]];
        $elements = Bunch::of($elements);

        $this->assertEquals(['A' => 3], $elements->first());
        $this->assertEquals(['A' => 5], $elements->first(fn ($x) => 4 < $x['A']));
        $this->assertNull($elements->first(fn ($x) => 4787 === $x['A']));
    }

    public function testLast()
    {
        $elements = [['A' => -1], ['A' => 2], ['A' => 5], ['A' => 12], ['A' => -3], ['A' => 0]];
        $elements = Bunch::of($elements);

        $this->assertEquals(['A' => 0], $elements->last());
        $this->assertEquals(['A' => -3], $elements->last(fn ($x) => 0 > $x['A']));
        $this->assertNull($elements->last(fn ($x) => 4787 === $x['A']));
    }

    public function testHas()
    {
        $elements = [['A' => 3], ['A' => 2], ['A' => 5], ['A' => 12], ['A' => -3], ['A' => 0]];
        $elements = Bunch::of($elements);

        $this->assertTrue($elements->has(['A' => 3]));
        $this->assertFalse($elements->has(['A' => 3000]));
    }

    public function testCount()
    {
        $this->assertEquals(4, Bunch::of([1, 2, 3])->push(4)->count());
        $this->assertEquals(1000, Bunch::fill(1000, null)->count());
    }

    public function testReduce()
    {
        $this->assertEquals(120, Bunch::range(5)->reduce(fn ($acc, $cur) => $acc * $cur, 1));
    }

    public function testSum()
    {
        $this->assertEquals(1+2+3+4+5, Bunch::of([1,2,3,4,5])->sum());
        $this->assertEquals(2+4+6+8+10, Bunch::of([1,2,3,4,5])->sum(fn($x) => $x*2));
        $this->assertEquals(1+2+3+4+5, Bunch::of([1,2,3,4,5])->map(fn($x) => ['myKey' => $x])->sum('myKey'));
    }

    public function testKey()
    {
        $baseData = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];
        $this->assertEquals([1, 2, 3], Bunch::of($baseData)->key('id')->get());

        $compound = [
            ['a' => ['b' => ['c' => 1]]],
            ['a' => ['b' => ['c' => 2]]],
            ['a' => ['b' => ['c' => 3]]],
        ];
        $this->assertEquals([1, 2, 3], Bunch::of($compound)->key('a.b.c')->get());

        $multiples = [
            ['apiV1' => ['info' => ['name' => 1]]],
            ['V3Name' => 2],
            ['_V2' => ['name' => 3]],
        ];
        $this->assertEquals([1, 2, 3], Bunch::of($multiples)->key(['apiV1.info.name', '_V2.name', 'V3Name'])->get());

        $dotsInKey = [
            ['api.V1' => ['meta.info' => ['name' => 1]]],
            ['V3.name' => 2],
            ['V2' => ['name' => 3]],
        ];
        $this->assertEquals([1, 2, 3], Bunch::of($dotsInKey)->key(['api.V1_meta.info_name', 'V2_name', 'V3.name'], '_')->get());

        $baseData = [
            ['id' => 1],
            ['serial' => 2],
            ['id' => 3],
        ];
        $this->expectException(\InvalidArgumentException::class);
        $this->assertEquals([1, 2, 3], Bunch::of($baseData)->key('id')->get());

        $baseData = [
            ['id' => 1],
            ['serial' => 2],
            ['id' => 3],
        ];
        $this->assertEquals([1, 2, 3], Bunch::of($baseData)->key(['id', 'serial'])->get());
    }

    public function testMax()
    {
        $this->assertEquals(10, Bunch::of([1, 5, -5, 9, 10])->max());
        $this->assertEquals(null, Bunch::of([])->max());
        $this->assertEquals(12, Bunch::of([])->max(12));
        $this->assertEquals(12, Bunch::of([])->max() ?? 12);
    }

    public function testMin()
    {
        $this->assertEquals(-5, Bunch::of([1, 5, -5, 9, 10])->min());
        $this->assertEquals(null, Bunch::of([])->min());
        $this->assertEquals(12, Bunch::of([])->min(12));
        $this->assertEquals(12, Bunch::of([])->min() ?? 12);
    }

    public function testAverage()
    {
        $this->assertEquals(50, Bunch::of([0, 100])->average());
        $this->assertEquals(null, Bunch::of([])->average());
        $this->assertEquals(12, Bunch::of([])->average(12));
        $this->assertEquals(12, Bunch::of([])->average() ?? 12);
    }


    #[ DataProvider('getDatabases') ]
    public function testFromQuery(Database $database)
    {
        $fullData = Bunch::fromQuery("SELECT id, label FROM user_type", database: $database);

        $this->assertCount(3, $fullData->get());
        $this->assertCount(2, $fullData->at(0));

        $onlyIds = Bunch::fromQuery("SELECT id FROM user_type", database: $database, key: 'id');

        $this->assertEquals(
            [1,2,3],
            $onlyIds->sort()->get()
        );
    }
}
