<?php

namespace Cube\Tests\Units\Utils;

use Cube\Utils\Console;
use PHPUnit\Framework\TestCase;

class ConsoleTest extends TestCase
{
    /** table() never read the keys of associative rows, and dropped the last row instead of the header one. */
    public function testATableOfAssociativeRowsUsesTheKeysAsHeader()
    {
        $this->expectOutputString(join("\n", ['name  age  ', '-----------', 'a     1    ', 'bob   22   ', '']));

        Console::table([['name' => 'a', 'age' => 1], ['name' => 'bob', 'age' => 22]], [], false);
    }

    public function testATableOfListsUsesTheFirstRowAsHeader()
    {
        $this->expectOutputString(join("\n", ['Name  Age  ', '-----------', 'a     1    ', '']));

        Console::table([['Name', 'Age'], ['a', '1']], [], false);
    }
}
