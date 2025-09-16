<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./103-applications.md">Previous : Applications</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./105-logging.md">Next : Logging</a></div></td></tr></table>

# Commands

When copying server files to your project directory, you may have noticed the `do` script. This script can launch any command from the framework or your application

Example, launch PHP web server
```bash
php do cube:serve
```

Here, the `cube` part is called the "scope", and `serve` is an automatic name made from the command classname

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
    }
}
```

To create a command, three criteria are needed:
- extend from `Command`
- implements `getScope()`
- implements `execute(Args $args)`

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

$args->dump(); // ["-n" => "some-custom-name"]
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
| `cube:help` | Print the command list |
| `web:serve` | Start PHP Builtin Webserver to serve your app |
| `configuration:cache` | Cache your app configuration |
| `cache:clear` | Clear every cache items |
| `dto:generate` | Can create a DTO object from a JSON user input |
| `migrate:make <MIGRATION_NAME>` | Create a migration file |
| `migrate:migrate` | Apply migrations to your database |
| `models:generate` | Generate Models Classes from your database tables |
| `models:to-types` | Generate a Typescript file exporting your database types (useful to make bridge between front and back-end) |
| `routine:generate` | Generate a CRON command to launch Cube routine script |
| `routine:launch` | Launch the Cube routine script |
| `websocket:serve` | Start Cube Websocket server ! |



<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./103-applications.md">Previous : Applications</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./105-logging.md">Next : Logging</a></div></td></tr></table>
