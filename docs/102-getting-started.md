<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./101-introduction.md">Previous : Introduction</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./103-configuration.md">Next : Configuration</a></div></td></tr></table>


# Getting started

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


<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./101-introduction.md">Previous : Introduction</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./103-configuration.md">Next : Configuration</a></div></td></tr></table>
