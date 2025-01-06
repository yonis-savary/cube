<?php

namespace YonisSavary\Cube\Core;

use Exception;
use Throwable;
use YonisSavary\Cube\Core\Autoloader\Applications;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Utils\Path;

class Autoloader
{
    protected static array $assetsFiles = [];
    protected static array $requireFiles = [];
    protected static array $routesFiles = [];

    protected static ?array $cachedClassesList = null;
    protected static ?string $projectPath = null;



    public static function initialize(string $forceProjectPath=null)
    {
        self::resolveProjectPath($forceProjectPath);
        self::loadApplications();

        foreach (self::$requireFiles as $file)
            include($file);
    }

    public static function resolveProjectPath(string $forceProjectPath=null): void
    {
        if ($forceProjectPath)
        {
            self::$projectPath = $forceProjectPath;
            return;
        }

        try
        {
            while (!is_dir("./vendor/yonis-savary/cube"))
                chdir("..");

            self::$projectPath = getcwd();
        }
        catch (Throwable $_)
        {
            throw new Exception("Could not resolve project root path");
        }
    }

    public static function getProjectPath(): string
    {
        if (is_null(self::$projectPath))
            self::resolveProjectPath();

        return self::$projectPath;
    }

    protected static function loadApplications()
    {
        $apps = Applications::resolve();

        foreach ($apps->paths as $app)
        {
            $app = new Storage($app);
            foreach ($app->listDirectory() as $directory)
            {
                $dirName = basename($directory);
                switch ($dirName)
                {
                    case 'Routes':
                    case 'Router':
                        self::$routesFiles[] = (new Storage($directory))->exploreFiles();
                    case 'Assets':
                        self::$assetsFiles[] = (new Storage($directory))->exploreFiles();
                    case 'Requires':
                    case 'Includes':
                        self::$requireFiles[] = (new Storage($directory))->exploreFiles();
                }
            }
        }
    }

    public static function getRoutesFiles(): array
    {
        return self::$routesFiles;
    }

    public static function getAssetsFiles(): array
    {
        return self::$assetsFiles;
    }

    public static function getRequireFiles(): array
    {
        return self::$requireFiles;
    }

    public static function classesList(): array
    {
        if (self::$cachedClassesList)
            return self::$cachedClassesList;

        /** @var Composer\Autoload\ClassLoader $loader */
        $loader = include Path::relative("vendor/autoload.php");

        $classes = Bunch::fromKeys($loader->getClassMap());

        foreach ($loader->getPrefixesPsr4() as $namespace => $directories)
        {
            foreach ($directories as $directory)
            {
                if (!is_dir($directory))
                {
                    Logger::getInstance()->warning("Could not read PSR4 directory [{dir}]", ["dir" => $directory]);
                    continue;
                }

                $storage = new Storage($directory);


                $files = Bunch::of($storage->exploreFiles())
                    ->filter(function($file) use (&$ignoreFile) {
                        $expectedClassName = pathinfo($file, PATHINFO_FILENAME);
                        $content = file_get_contents($file);

                        return
                            str_contains($content, "class $expectedClassName") ||
                            str_contains($content, "interface $expectedClassName") ||
                            str_contains($content, "trait $expectedClassName");
                    })
                    ->map(fn($path) => $namespace . Path::toRelative($path, $directory) )
                    ->map(fn($path) => str_replace("/", "\\", $path) )
                    ->map(fn($path) => preg_replace("/\..+$/", "", $path) )
                    ->get()
                ;

                restore_error_handler();

                $classes->push(...$files);
            }
        }

        self::$cachedClassesList = $classes
            ->uniques()
            ->get();

        return self::$cachedClassesList;
    }

    public static function extends($class, $parentClass): bool
    {
        if ($parents = class_parents($class))
            return in_array($parentClass, $parents);
        return false;
    }

    public static function implements($class, $interface): bool
    {
        if ($implements = class_implements($class))
            return in_array($interface, $implements);
        return false;
    }

    public static function uses($class, $trait): bool
    {
        if ($traits = class_uses($class))
            return in_array($trait, $traits);
        return false;
    }

    public static function classesThatExtends(string $parentClass): array
    {
        return Bunch::of(self::classesList())
        ->filter(fn($class) => self::extends($class, $parentClass))
        ->get();
    }

    public static function classesThatImplements(string $interface): array
    {
        return Bunch::of(self::classesList())
        ->filter(fn($class) => self::implements($class, $interface))
        ->get();
    }

    public static function classesThatUses(string $trait): array
    {
        return Bunch::of(self::classesList())
        ->filter(fn($class) => self::uses($class, $trait))
        ->get();
    }
}