<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./102-getting-started.md">Previous : Getting started</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./104-commands.md">Next : Commands</a></div></td></tr></table>

# Application directory

One strong aspect of Cube is application loading, you can create multiple applications (can be considered as modules)

In this example, we shall create an application named `MyApp`

## Application loading

First, we specify to Cube that we want to load `MyApp/`

```php 
return [
    new Applications('MyApp')
]
```

## Directory structure

Your application can contain some special directories 

Files inside these directories shall be considered as "route" files and used by the `Router` component
- `Routes/`
- `Router/`

Files inside these directories shall be considered as "assets" files and used by the many components
- `Assets/`

Files inside these directories shall be considered as "require" files and used by the many components
- `Requires/`
- `Includes/`
- `Helpers/`
- `Schedules/`
- `Cron/`


You can retrieve every set of files with 
```php
Autoloader::getRoutesFiles();
Autoloader::getAssetsFiles();
Autoloader::getRequireFiles();
```


<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./102-getting-started.md">Previous : Getting started</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./104-commands.md">Next : Commands</a></div></td></tr></table>
