<?php

namespace YonisSavary\Cube\Core;

use Exception;
use Throwable;
use Composer\Autoload\ClassLoader;
use ErrorException;
use YonisSavary\Cube\Core\Autoloader\Applications;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Env\Environment;
use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Http\Response;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Utils\Path;

class Autoloader
{
    protected static array $assetsFiles = [];
    protected static array $requireFiles = [];
    protected static array $routesFiles = [];

    protected static ?array $cachedClassesList = null;
    protected static ?string $projectPath = null;

    protected static ?ClassLoader $loader = null;


    public static function initialize(string $forceProjectPath=null, ?ClassLoader $loader=null)
    {
        self::registerErrorHandlers();

        self::$loader = $loader ?? (include Path::relative("vendor/autoload.php"));

        self::resolveProjectPath($forceProjectPath);
        self::loadApplications();

        $cubeSrc = (new Storage(__DIR__))->parent()->parent();
        $cubeHelpers = $cubeSrc->child("Helpers");
        foreach ($cubeHelpers->listFiles() as $helperFile)
            include_once $helperFile;

        foreach (self::$requireFiles as $file)
            include_once $file;
    }

    public static function registerErrorHandlers(): void
    {
        /**
         * To use the same code a the exception handler,
         * we transform the error into an `ErrorException`
         */
        set_error_handler(function(int $code, string $message, string $file, int $line){
            throw new ErrorException($message, $code, 1, $file, $line);
        });


        /**
         * Exception kill the request if not handled :
         * - For web users : a simple 'Internal Server Error' is displayed (+ An error message in a debug environment)
         * - For CLI users : a message is displayed telling that an error occurred
         */
        set_exception_handler(function(Throwable $exception)
        {
            while (ob_get_level())
                ob_end_clean();

            try
            {
                Logger::getInstance()->logThrowable($exception);

                if (php_sapi_name() === 'cli')
                    die(
                        "\n".
                        "Oops ! Caught a ". $exception::class ." \n".
                        $exception->getMessage().' at '.$exception->getFile().':'.$exception->getLine() . "\n" .
                        "Please read your logs for more informations \n"
                    );

                $errorMessage = 'Internal Server Error';

                if (!str_contains(Environment::getInstance()->get("environment", "debug"), "prod"))
                {
                    $errorMessage .= "\n\n" . $exception->getMessage();
                    $errorMessage .= "\n" . $exception->getTraceAsString();
                }

                (new Response($errorMessage, 500, ['Content-Type' => 'text/plain']))->display();
                die;
            }
            catch (Throwable $err)
            {
                // In case everything went wrong even logging/events !

                http_response_code(500);
                echo 'Internal Server Error';
                echo $err->getMessage();
                die;
            }
        });
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

    public static function getClassLoader(): ClassLoader
    {
        return self::$loader;
    }

    public static function classesList(): array
    {
        if (self::$cachedClassesList)
            return self::$cachedClassesList;

        /** @var ClassLoader $loader */
        $loader = self::getClassLoader();

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
        if (!class_exists($class))
            return false;

        if ($parents = class_parents($class))
            return in_array($parentClass, $parents);
        return false;
    }

    public static function implements($class, $interface): bool
    {
        if (!class_exists($class))
            return false;

        if ($implements = class_implements($class))
            return in_array($interface, $implements);
        return false;
    }

    public static function uses($class, $trait): bool
    {
        if (!class_exists($class))
            return false;

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