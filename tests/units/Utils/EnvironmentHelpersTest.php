<?php

namespace Cube\Tests\Units\Utils;

use Cube\Env\Environment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Cube\isDebug;
use function Cube\isProduction;

class EnvironmentHelpersTest extends TestCase
{
    /** @return array<string,array{array<string,string>,bool,bool}> */
    public static function getEnvironments(): array
    {
        return [
            'no key' => [[], false, false],
            'env debug' => [['env' => 'debug'], false, true],
            'env production' => [['env' => 'production'], true, false],
            'environment prod' => [['environment' => 'prod'], true, false],
            'environment debug' => [['environment' => 'debug'], false, true],
            'env wins over environment' => [['env' => 'debug', 'environment' => 'production'], false, true],
        ];
    }

    /**
     * The exception handler read `environment` while isProduction() read `env`, and showed traces when neither was set
     */
    #[DataProvider('getEnvironments')]
    public function testProductionAndDebugModes(array $values, bool $production, bool $debug)
    {
        $environment = new Environment(null);
        foreach ($values as $key => $value) {
            $environment->set($key, $value);
        }

        Environment::withInstance($environment, function () use ($production, $debug) {
            $this->assertSame($production, isProduction());
            $this->assertSame($debug, isDebug());
        });
    }
}
