<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./103-applications.md">Previous : Applications</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./105-logging.md">Next : Logging</a></div></td></tr></table>

# Commands

When copying server files to your project directory, you may have noticed the `do` script. This script can launch any command from the framework or your application

Example, launch PHP web server
```bash
php do web:serve
```

Here, the `web` part is called the "scope", and `serve` is an automatic name made from the command classname

## Creating a command

To create a command, you can create a file in `<YourApp>/Commands`, here is an example 

```php
class SayHello extends Command
{
    public function getScope(): string
    {
        return "app";
    }

    public function execute(Args $args): int
    {
        Console::print(Console::withGreenBackground("Hello, world !"));

        return 0;
    }
}
```

To create a command, two criteria are needed:
- extend from `Command`
- implement `execute(Args $args)`, which returns the exit code of your command

`getScope()` is optional : left alone, the scope is the root namespace of your command,
so `App\Commands\SayHello` answers to `app:say-hello`. Override it when you want to group
several commands under one word, the way the framework does with `models:` or `make:`.

Now, you can launch your brand new command with either
```bash
php do say-hello #Command name converted to kebab-case
```
Or this, if your applications has multiples `say-hello`
```bash
php do app:say-hello
```

### use `Args`

The `execute()` command takes the `$args` parameter, which contains the argv passed when calling the `do` script

Here is how you can use it 
```php
// In this example, we call
// php do say-hello -n some-custom-name -f file1 -f file2 --file file3

// Every parameter holds the list of the values that followed it
$args->dump(); // ["-n" => ["some-custom-name"], "-f" => ["file1", "file2"], "--file" => ["file3"]]
$args->toString(); // convert to -n some-custom-name -f file1 -f file2 --file file3
$args->has("-f", "--file"); // true !
$args->getValues("-f", "--file"); // Get ["file1", "file2", "file3"]
$args->getValue("-n", "--name"); // Get "some-custom-name"
```

## Cube Commands

The Cube framework contains a bunch of commands you can use out-of-the-box !, which are 

| Command | Purpose |
|---------|---------|
| `cube:hello-world` | Say hello ! |
| `cube:help [COMMAND]` | Print the command list, or the manual of one command |
| `cube:queue --queue=<QUEUE>` | Run a queue, or flush it with `-f` |
| `cube:test` | Run the PHPUnit suite of your project |
| `web:serve [PORT]` | Start PHP Builtin Webserver to serve your app |
| `unix:serve <SOCKET_PATH>` | Serve your socket routes on a unix socket |
| `configuration:cache` | Cache your app configuration |
| `cache:clear` | Clear every cache items |
| `make:dto` | Can create a DTO object from a JSON user input |
| `make:migration <MIGRATION_NAME>` | Create a migration file |
| `make:openapi` | Generate the OpenAPI document of your API |
| `migrate:migrate` | Apply migrations to your database |
| `models:generate [--apis]` | Generate Models Classes from your database tables, and with `--apis` a `ModelAPI` subclass per model |
| `models:to-types` | Generate a Typescript file exporting your database types (useful to make bridge between front and back-end) |
| `routine:generate` | Generate a CRON command to launch Cube routine script |
| `routine:launch` | Launch the Cube routine script |
| `websocket:serve` | Start Cube Websocket server ! |



<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./103-applications.md">Previous : Applications</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./105-logging.md">Next : Logging</a></div></td></tr></table>
