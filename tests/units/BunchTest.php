<?php

namespace YonisSavary\Cube\Tests\Units;

use PHPUnit\Framework\TestCase;
use YonisSavary\Cube\Data\Bunch;

class BunchTest extends TestCase
{
    public function test_of()
    {
        $this->assertEquals([], Bunch::of([])->get());
        $this->assertEquals([1,2,3], Bunch::of([1,2,3])->get());
        $this->assertEquals([["A" => 0]], Bunch::of(["A" => 0])->get());
        $this->assertEquals(["test"], Bunch::of("test")->get());
    }

    public function test_fill()
    {
        $this->assertEquals([0, 0, 0], Bunch::fill(3, 0)->get());
        $this->assertEquals([null], Bunch::fill(1, null)->get());
        $this->assertEquals([], Bunch::fill(0, null)->get());
    }

    public function test_range()
    {
        $this->assertEquals([1,2,3,4,5], Bunch::range(5)->get());
        $this->assertEquals([0,2,4,6,8,10], Bunch::range(10, 0, 2)->get());
    }

    public function test_fromValues()
    {
        $this->assertEquals([0, 1, 2], Bunch::fromValues(["A" => 0, "B" => 1, "C" => 2])->get());
        $this->assertEquals([455, 1024, 3002], Bunch::fromValues([455, 1024, 3002])->get());
    }

    public function test_fromKeys()
    {
        $this->assertEquals(["A", "B", "C"], Bunch::fromKeys(["A" => 0, "B" => 1, "C" => 2])->get());
        $this->assertEquals([0, 1, 2], Bunch::fromKeys([455, 1024, 3002])->get());
    }

    public function test_clone()
    {
        $first = Bunch::of([1,2,3]);
        $second = clone($first);

        $first->push(4);

        $this->assertCount(4, $first->get());
        $this->assertCount(3, $second->get());
    }

    public function test_get()
    {
        // Implicitly tested by other tests
        // Same for toArray() which is an alias of get()
        $this->assertEquals([1,2,3], Bunch::of([1,2,3])->get());
    }

    public function test_asIntegers()
    {
        $this->assertEquals([1, -2, 3], Bunch::of(["1", "-2", "3.1416"])->asIntegers()->get());
        $this->assertEquals([1, -2, 3], Bunch::of(["1", null, "-2", "3.1416"])->asIntegers()->get());
        $this->assertEquals([1, -2, 3], Bunch::of(["1", "Hello World", "-2", "3.1416"])->asIntegers()->get());
        $this->assertEquals([1, 12, -2, 3], Bunch::of(["1", 12, "-2", "3.1416"])->asIntegers()->get());
    }

    public function test_asFloats()
    {
        $this->assertEquals([1, -2, 3.1416], Bunch::of(["1", "-2", "3.1416"])->asFloats()->get());
        $this->assertEquals([1, -2, 3.1416], Bunch::of(["1", null, "-2", "3.1416"])->asFloats()->get());
        $this->assertEquals([1, -2, 3.1416], Bunch::of(["1", "Hello World", "-2", "3.1416"])->asFloats()->get());
        $this->assertEquals([1, 12, -2, 3.1416], Bunch::of(["1", 12, "-2", "3.1416"])->asFloats()->get());
    }

    public function test_filter()
    {
        $this->assertEquals([0, 2, 4, 6, 8], Bunch::of([0,1,2,3,4,5,6,7,8,9])->filter(fn($x) => $x % 2 == 0)->get());
    }

    public function test_partitionFilter()
    {
        list($group1, $group2, $group3) = Bunch::of([
            "1-paris",
            "2-berlin",
            "3-rome",
            "4-tokyo",
            "5-oulan-bator",
            "6-washington"
        ])->partitionFilter(fn($x) => floor((intval(substr($x, 0, 1))-1)/2)  );

        $this->assertEquals(["1-paris", "2-berlin"], $group1);
        $this->assertEquals(["3-rome", "4-tokyo"], $group2);
        $this->assertEquals(["5-oulan-bator", "6-washington"], $group3);
    }

    public function test_map()
    {
        $this->assertEquals(
            [0,1,8,27],
            Bunch::of([0,1,2,3])
            ->map(fn($x) => pow($x, 3))
            ->get()
        );
    }

    public function test_merge()
    {
        $first = Bunch::of([0,1,2]);
        $second = Bunch::of([3,4,5]);
        $third = [6,7,8,9];

        $first->merge($second)->merge($third);

        $this->assertEquals([0,1,2,3,4,5,6,7,8,9], $first->get());
    }

    public function test_sort()
    {
        $target = [0,1,2,3,4,5,6,7,8,9];
        $set = [0,1,2,3,4,5,6,7,8,9];

        for ($i=0; $i<100; $i++)
        {
            shuffle($set);
            $this->assertNotEquals($set, $target);
            $this->assertEquals(Bunch::of($set)->sort()->get(), $target);
        }


        $elements = [["A"=>3], ["A"=>2], ["A"=>5], ["A"=>12], ["A"=>-3], ["A"=>0]];

        $sorted = Bunch::of($elements)->sort(fn($x) => $x["A"])->get();

        $this->assertEquals(
            [["A"=>-3], ["A"=>0], ["A"=>2], ["A"=>3], ["A"=>5], ["A"=>12]],
            $sorted
        );
    }

    public function test_forEach()
    {
        $sum = 0;
        Bunch::of([1000,100,10,1])
        ->forEach(function($x) use (&$sum) {
            $sum += $x;
        });
        $this->assertEquals(1111, $sum);
    }

    public function test_any()
    {
        $elements = [["A"=>3], ["A"=>2], ["A"=>5], ["A"=>12], ["A"=>-3], ["A"=>0]];
        $elements = Bunch::of($elements);

        $this->assertTrue($elements->any(fn($x) => $x["A"] === 3));
        $this->assertFalse($elements->any(fn($x) => $x["A"] === 5292));
    }

    public function test_all()
    {
        $elements = [["A"=>3], ["A"=>2], ["A"=>5], ["A"=>12], ["A"=>-3], ["A"=>0]];
        $elements = Bunch::of($elements);

        $this->assertTrue($elements->all(fn($x) => $x["A"] > -30));
        $this->assertFalse($elements->all(fn($x) => $x["A"] > 0));
    }

    public function test_uniques()
    {
        $this->assertEquals(
            [0,1,2,3,4,5,6,7,8,9],
            Bunch::of([0,1,2,1,1,3,4,5,6,7,5,5,8,0,9])->uniques()->get()
        );
    }

    public function test_push()
    {
        $this->assertEquals([1,2,3,"A","B","C"], Bunch::of([1,2,3])->push("A", "B")->push("C")->get());
    }

    public function test_unshift()
    {
        $this->assertEquals(["A","B","C",1,2,3], Bunch::of([1,2,3])->unshift("B", "C")->unshift("A")->get());

    }

    public function test_pop()
    {
        $this->assertEquals([1,2], Bunch::of([1,2,3])->pop(1)->get());
        $this->assertEquals([1], Bunch::of([1,2,3])->pop(2)->get());
        $this->assertEquals([], Bunch::of([1,2,3])->pop(3)->get());
        $this->assertEquals([], Bunch::of([1,2,3])->pop(12)->get());
    }

    public function test_shift()
    {
        $this->assertEquals([2,3], Bunch::of([1,2,3])->shift(1)->get());
        $this->assertEquals([3], Bunch::of([1,2,3])->shift(2)->get());
        $this->assertEquals([], Bunch::of([1,2,3])->shift(3)->get());
        $this->assertEquals([], Bunch::of([1,2,3])->shift(12)->get());
    }

    public function test_join()
    {
        $this->assertEquals("Hello World !", Bunch::of(["Hello", "World", "!"])->join(" "));
    }

    public function test_first()
    {
        $elements = [["A"=>3], ["A"=>2], ["A"=>5], ["A"=>12], ["A"=>-3], ["A"=>0]];
        $elements = Bunch::of($elements);

        $this->assertEquals(["A"=>3], $elements->first(fn($x) => $x["A"] == 3));
        $this->assertNull($elements->first(fn($x) => $x["A"] === 4787));
    }

    public function test_has()
    {
        $elements = [["A"=>3], ["A"=>2], ["A"=>5], ["A"=>12], ["A"=>-3], ["A"=>0]];
        $elements = Bunch::of($elements);

        $this->assertTrue($elements->has(["A"=>3]));
        $this->assertFalse($elements->has(["A"=>3000]));
    }

    public function test_count()
    {
        $this->assertEquals(4, Bunch::of([1,2,3])->push(4)->count());
        $this->assertEquals(1000, Bunch::fill(1000, null)->count());
    }

    public function test_reduce()
    {
        $this->assertEquals(120, Bunch::range(5)->reduce(fn($acc, $cur) => $acc * $cur, 1));
    }
}