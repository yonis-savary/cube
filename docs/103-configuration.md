<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./102-getting-started.md">Previous : Getting started</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./104-commands.md">Next : Commands</a></div></td></tr></table>

# Configuration

Application / Component configuration is made through the `Configuration` component, which load the `cube.php` file in your project root directory

Here is an example of configuration

```php
return [
    new Applications('App'),

    new DatabaseConfiguration(
        "sqlite",
        env("DB_FILENAME", "app.sqlite")
    ),

    new RouterConfiguration(
        apis: [AssetServer::class]
    )
];
```

`cube.php` should return an array of configuration elements

You can also import some configuration from another file

```php
return [
    new Import('Config/production.php')
];
```

## Base configuration

So far, the most important configuration element is `Applications`, which defines directories that Cube must load

```php
return [
    new Applications('App') // Load files in `/App` when initializing cube
];
```


<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./102-getting-started.md">Previous : Getting started</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./104-commands.md">Next : Commands</a></div></td></tr></table>
