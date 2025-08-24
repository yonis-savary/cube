<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./101-introduction.md">Previous : Introduction</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./103-applications.md">Next : Applications</a></div></td></tr></table>

# Getting started

## Setting up a Cube project

First, install Cube dependency with `composer`

```sh
composer require yonis-savary/cube
```

Then, copy the server files

```sh
cp -r vendor/yonis-savary/cube/server/* .
```

Server files are utilities such as the `Public/index.php` entrypoint, `do` script (used to launch commands)...etc. Files that are at the root directory of your project

## Configuration / Env

Your application and frameworks are configured through `cube.php`, which is a file returning an array of configuration elements

The goal here is to allow anyone to configure Cube through strong typed configuration elements, your IDE will help you out !

Here is an example of configuration

```php
return [
    # Applications to load
    new Applications('App'),

    new DatabaseConfiguration(
        "sqlite",
        env("DB_FILENAME", "app.sqlite")
    ),

    new RouterConfiguration(
        apis: [AssetServer::class]
    )

    # You can also import some configuration from another file
    new Import('Config/production.php')
];
```

## Components

Cube components are classes that uses the `Component` trait. This trait
is a simple way to implement singleton through the app

Here is an example of a simple component

```php
class Multiplier
{
    use Component;

    public static function getDefaultInstance()
    {
        return new self(5);
    }

    public function __construct(
        protected int $baseFactor=1
    ){}

    public function multiply(int $number)
    {
        return $this->number * $this->baseFactor;
    }
}
```

And how to use it

```php
$global = Multiplier::getInstance();
$global->multiplty(5); // 25
$global->multiplty(2); // 10


$doubler = new Multiplier(2);
$doubler->multiply(50); // 100
Multiplier::setInstance($doubler); // Replace the global instance

$tripler = new Multiplier(3);

$tripler->asGlobalInstance(function(){
    $global = Multiplier::getInstance(); // Return $tripler only in this function
});
```

Most notable components are
- `Router`
- `Configuration`
- `Logger`
- `Database`
- `Authentication`


<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./101-introduction.md">Previous : Introduction</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./103-applications.md">Next : Applications</a></div></td></tr></table>
