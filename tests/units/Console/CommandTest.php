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

    public function test_the_name_is_the_kebab_cased_class_name()
    {
        $this->assertEquals('say-hello', (new SayHello())->getName());
    }

    public function test_the_scope_defaults_to_the_root_namespace()
    {
        $this->assertEquals('cube', (new SayHello())->getScope());
    }

    public function test_the_full_identifier_joins_the_scope_and_the_name()
    {
        $this->assertEquals('cube:say-hello', (new SayHello())->getFullIdentifier());
    }

    public function test_a_command_can_declare_its_own_scope()
    {
        $this->assertEquals('greeting:scoped-say-hello', (new ScopedSayHello())->getFullIdentifier());
    }

    public function test_call_executes_the_command_and_gives_its_status_back()
    {
        $this->assertEquals(0, SayHello::call());
        $this->assertEquals(1, SayHello::call(Args::fromArgv(['--fail'])));
        $this->assertEquals(2, SayHello::$executions);
    }

    public function test_call_hands_an_empty_args_when_given_none()
    {
        SayHello::call();

        $this->assertEquals([], SayHello::$lastArgs->dump());
    }

    public function test_the_default_help_asks_for_one()
    {
        $this->assertEquals('Please write a help section for this command', (new SayHello())->getHelp());
    }

    public function test_the_default_manual_names_the_command()
    {
        $this->assertStringContainsString('cube:say-hello', (new SayHello())->getManual());
    }

    /**
     * Identifiers are what users type : renaming a command class or moving it to another
     * namespace has to be a deliberate change, not a side effect.
     */
    public function test_every_framework_command_keeps_its_identifier()
    {
        $identifiers = Bunch::of(Autoloader::classesThatExtends(Command::class))
            ->filter(fn (string $class) => str_starts_with($class, 'Cube\\Console\\Commands\\'))
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
            'make:dto',
            'make:migration',
            'make:openapi',
            'migrate:migrate',
            'models:generate',
            'models:to-types',
            'routine:generate',
            'routine:launch',
            'unix:serve',
            'web:serve',
            'websocket:serve',
        ], $identifiers);
    }
}
