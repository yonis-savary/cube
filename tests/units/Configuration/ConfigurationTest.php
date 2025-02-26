<?php

namespace Cube\Tests\Units\Configuration;

use Cube\Configuration\Configuration;
use Cube\Configuration\GenericElement;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ConfigurationTest extends TestCase
{
    public function testConstructAndResolve()
    {
        $config = new Configuration(
            new GenericElement('generic-1', ['mike' => 'bob']),
            new GenericElement('generic-2', ['bob' => 'mike']),
        );
        $generic = $config->resolveGeneric('generic-1', false);
        $this->assertEquals(['mike' => 'bob'], $generic);

        $generic = $config->resolveGeneric('generic-2', false);
        $this->assertEquals(['bob' => 'mike'], $generic);
    }
}
