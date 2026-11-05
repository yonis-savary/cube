<?php

namespace Cube\Tests\Units\Console;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Tests\Units\Console\Classes\SayHello;
use Cube\Tests\Units\Console\Classes\ScopedSayHello;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class CommandTest extends TestCase
{
    protected function setUp(): void
    {
        SayHello::$executions = 0;
        SayHello::$lastArgs = null;
    }

    public function testTheNameIsTheKebabCasedClassName()
    {
        $this->assertEquals('say-hello', (new SayHello())->getName());
    }

    public function testTheScopeDefaultsToTheRootNamespace()
    {
        $this->assertEquals('cube', (new SayHello())->getScope());
    }

    public function testTheFullIdentifierJoinsTheScopeAndTheName()
    {
        $this->assertEquals('cube:say-hello', (new SayHello())->getFullIdentifier());
    }

    public function testACommandCanDeclareItsOwnScope()
    {
        $this->assertEquals('greeting:scoped-say-hello', (new ScopedSayHello())->getFullIdentifier());
    }

    public function testCallExecutesTheCommandAndGivesItsStatusBack()
    {
        $this->assertEquals(0, SayHello::call());
        $this->assertEquals(1, SayHello::call(Args::fromArgv(['--fail'])));
        $this->assertEquals(2, SayHello::$executions);
    }

    public function testCallHandsAnEmptyArgsWhenGivenNone()
    {
        SayHello::call();

        $this->assertEquals([], SayHello::$lastArgs->dump());
    }

    public function testTheDefaultHelpAsksForOne()
    {
        $this->assertEquals('Please write a help section for this command', (new SayHello())->getHelp());
    }

    public function testTheDefaultManualNamesTheCommand()
    {
        $this->assertStringContainsString('cube:say-hello', (new SayHello())->getManual());
    }

    /**
     * Identifiers are what users type : renaming a command class or moving it to another
     * namespace has to be a deliberate change, not a side effect.
     */
    public function testEveryFrameworkCommandKeepsItsIdentifier()
    {
        $identifiers = Bunch::wrap(Autoloader::classesThatExtends(Command::class))
            ->filter(fn (string $class) => str_starts_with($class, 'Cube\Console\Commands\\'))
            ->instanciates()
            ->map(fn (Command $command) => $command->getFullIdentifier())
            ->get()
        ;

        sort($identifiers);

        $this->assertEquals([
            'cache:clear',
            'configuration:cache',
            'cube:dispatch',
            'cube:hello-world',
            'cube:help',
            'cube:queue',
            'cube:test',
            'key:generate',
            'make:dto',
            'make:integration',
            'make:migration',
            'make:openapi',
            'migrate:migrate',
            'migrate:rollback',
            'models:generate',
            'models:to-types',
            'routine:generate',
            'routine:launch',
            'routine:run',
            'unix:serve',
            'web:serve',
            'websocket:serve',
        ], $identifiers);
    }

    /** `cube:dispatch`, `make:openapi` and `cube:test` showed the default help in `cube:help`. */
    public function testEveryFrameworkCommandHasAHelp()
    {
        $withoutHelp = (new Bunch(Autoloader::classesThatExtends(Command::class)))
            ->filter(fn (string $class) => str_starts_with($class, 'Cube\Console\Commands\\'))
            ->instanciates()
            ->filter(fn (Command $command) => $command->getHelp() === (new SayHello())->getHelp())
            ->map(fn (Command $command) => $command->getFullIdentifier())
            ->values()
            ->get()
        ;

        $this->assertEquals([], $withoutHelp);
    }
}
