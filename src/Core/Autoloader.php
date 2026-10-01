<?php

namespace Cube\Core;

use Composer\Autoload\ClassLoader;
use Cube\Core\Autoloader\Applications;
use Cube\Core\Autoloader\AutoloaderConfiguration;
use Cube\Data\Bunch;
use Cube\Env\Cache;
use Cube\Env\Storage;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Env\Logger\Logger;
use Cube\Utils\Path;
use Cube\Utils\Shell;
use ErrorException;
use Exception;
use RuntimeException;

use function Cube\isDebug;

class Autoloader
{
    protected static array $knownApplications = [];
    protected static array $assetsFiles = [];
    protected static array $requireFiles = [];
    protected static array $routesFiles = [];
    protected static array $viewFiles = [];

    protected static ?array $cachedClassesList = null;
    protected static ?string $projectPath = null;

    protected static ?ClassLoader $loader;
    protected static mixed $classIndex = [];
    protected static Cache $autoloadCache;

    public static bool $loadedThroughApcu = false;

    public static function clearApcuCache(): void 
    {
        if (!function_exists('apcu_fetch'))
            return;

        apcu_delete(__DIR__ . ".autoload-data");
    }

    public static function tryToLoadThroughApcu(): bool
    {
        if (!function_exists('apcu_fetch'))
            return false;

        $success = false;
        $autoloadFullData = apcu_fetch(__DIR__ . ".autoload-data", $success);

        if (!$success)
            return false;

        if (!(is_array($autoloadFullData) && count($autoloadFullData)))
            return false;

        list(
            self::$knownApplications,
            self::$assetsFiles,
            self::$requireFiles,
            self::$routesFiles,
            self::$viewFiles,
            self::$cachedClassesList,
            self::$projectPath,
            self::$classIndex,
        ) = $autoloadFullData;

        Path::resolveProjectPath(self::$projectPath);

        self::$loadedThroughApcu = true;
        return true;
    }

    public static function saveToApcu(): void
    {
        if (!function_exists('apcu_fetch'))
            return;

        apcu_store(__DIR__ . ".autoload-data", [
            self::$knownApplications,
            self::$assetsFiles,
            self::$requireFiles,
            self::$routesFiles,
            self::$viewFiles,
            self::$cachedClassesList,
            Path::getProjectPath(),
            self::$classIndex,
        ]);
    }

    public static function initialize(?string $forceProjectPath = null, ?AutoloaderConfiguration $configuration = null)
    {
        self::registerErrorHandlers();
        self::$loader = self::findClassLoader();

        $cubeSrc = (new Storage(__DIR__))->parent();
        $cubeHelpers = $cubeSrc->child('Helpers');
        foreach ($cubeHelpers->files() as $helperFile) {
            include_once $helperFile;
        }

        if (self::tryToLoadThroughApcu())
            return self::includeRequireFiles();

        Path::resolveProjectPath($forceProjectPath);

        $configuration ??= AutoloaderConfiguration::resolve();

        if ($configuration->cached) {
            $lockFile = Path::relative('composer.lock');
            $cacheIdentifier = is_file($lockFile) ? md5_file($lockFile) : 'default';
            self::$autoloadCache = Cache::getInstance();
            self::$classIndex = &self::$autoloadCache->getReference($cacheIdentifier, []);

            self::$knownApplications = &self::$autoloadCache->getReference("cube:$cacheIdentifier:apps", []);
            self::$assetsFiles = &self::$autoloadCache->getReference("cube:$cacheIdentifier:assets", []);
            self::$requireFiles = &self::$autoloadCache->getReference("cube:$cacheIdentifier:require", []);
            self::$routesFiles = &self::$autoloadCache->getReference("cube:$cacheIdentifier:routes", []);
            self::$viewFiles = &self::$autoloadCache->getReference("cube:$cacheIdentifier:views", []);
        } else {
            self::$classIndex = [];
        }

        self::loadApplications();
        self::includeRequireFiles();
        self::saveToApcu();
    }

    protected static function findClassLoader(): ClassLoader
    {
        foreach (spl_autoload_functions() as $autoloadFunction) {
            // Composer registers its loader as [ClassLoader, 'loadClass']
            if (!is_array($autoloadFunction))
                continue;

            $possibleLoader = $autoloadFunction[0] ?? null;
            if ($possibleLoader instanceof ClassLoader)
                return $possibleLoader;
        }

        throw new RuntimeException('Could not find composer ClassLoader among the registered autoloaders');
    }

    public static function includeRequireFiles(): void
    {
        foreach (self::$requireFiles as $file) {
            include_once Path::relative($file);
        }
    }

    public static function registerErrorHandlers(): void
    {
        /*
         * To use the same code a the exception handler,
         * we transform the error into an `ErrorException`
         */
        set_error_handler(function (int $code, string $message, string $file, int $line) {
            $exception = new \ErrorException($message, $code, 1, $file, $line);
            if (($code & E_DEPRECATED) || ($code & E_USER_DEPRECATED)) {
                $logger = Logger::forFile('warnings.csv');
                $logger->logThrowable($exception);
                return true;
            }

            return false;
        });

        /*
         * Exception kill the request if not handled :
         * - For web users : a simple 'Internal Server Error' is displayed (+ An error message in a debug environment)
         * - For CLI users : a message is displayed telling that an error occurred
         */
        set_exception_handler(function (\Throwable $exception) {
            while (ob_get_level()) {
                ob_end_clean();
            }

            try {
                $logger = Logger::forFile('fatal.csv');
                $logger->logThrowable($exception);

                if ('cli' === php_sapi_name()) {
                    exit(
                        "\n"
                        .'Oops ! Caught a '.$exception::class." \n"
                        .$exception->getMessage().' at '.$exception->getFile().':'.$exception->getLine()."\n"
                        ."Please read your logs for more informations \n"
                    );
                }

                $errorMessage = 'Internal Server Error';

                if (isDebug()) {
                    $errorMessage .= "\n\n".$exception->getMessage();
                    $errorMessage .= "\n".$exception->getTraceAsString();
                }

                $response = Response::text($errorMessage, 500);
                Shell::logRequestAndResponseToStdOut(Request::fromGlobals(), $response);
                $response->exit();
            } catch (\Throwable $_) {
                // In case everything went wrong (even logging/events) !

                http_response_code(500);
                echo "Internal Server Error\n";

                exit;
            }
        });
    }


    public static function getRoutesFiles(): array
    {
        return self::$routesFiles;
    }

    public static function getAssetsFiles(): array
    {
        return self::$assetsFiles;
    }

    public static function getViewFiles(): array 
    {
        return self::$viewFiles;
    }

    public static function getRequireFiles(): array
    {
        return self::$requireFiles;
    }

    public static function getClassLoader(): ClassLoader
    {
        return self::$loader;
    }

    /**
     * @return array<class-string>
     */
    public static function classesList(): array
    {
        if (isset(self::$classIndex['list'])) {
            return self::$classIndex['list'];
        }

        $loader = self::getClassLoader();
        $classes = Bunch::fromKeys($loader->getClassMap());

        if (!$loader->isClassMapAuthoritative()) {
            $classes->push(...self::classesInPsr4Directories($loader));
        }

        self::$classIndex['list'] = $list = $classes->uniques()->get();
        self::saveToApcu();

        return $list;
    }

    /** @return array<class-string> */
    protected static function classesInPsr4Directories(ClassLoader $loader): array
    {
        $classMapFiles = array_flip(
            Bunch::fromValues($loader->getClassMap())
                ->map(realpath(...))
                ->filter()
                ->get()
        );

        $vendorDirectory = Path::relative('vendor');
        $vendorDirectory = realpath($vendorDirectory) ?: $vendorDirectory;
        $cubeDirectory = realpath(Path::relative('vendor/yonis-savary/cube/src'));

        $classes = Bunch::of([]);
        foreach ($loader->getPrefixesPsr4() as $namespace => $directories) {
            $realDirectories = Bunch::of($directories)->map(realpath(...))->get();
            $isCubeNamespace = in_array($cubeDirectory, $realDirectories, true);

            foreach ($directories as $directory) {
                if (!is_dir($directory)) {
                    Logger::getInstance()->warning('Could not read PSR4 directory [{dir}]', ['dir' => $directory]);
                    continue;
                }

                $directory = realpath($directory);
                if (!$isCubeNamespace && str_starts_with($directory, $vendorDirectory)) {
                    continue;
                }

                $classes->push(...self::classesInPsr4Directory($namespace, $directory, $classMapFiles));
            }
        }

        return $classes->get();
    }

    /**
     * @param array<string,int> $classMapFiles
     * @return array<class-string>
     */
    protected static function classesInPsr4Directory(string $namespace, string $directory, array $classMapFiles): array
    {
        return Bunch::of((new Storage($directory))->exploreFiles())
            ->filter(fn ($file) => str_ends_with($file, '.php'))
            ->filter(fn ($file) => !isset($classMapFiles[$file]))
            ->filter(self::declaresTypeNamedAfterFile(...))
            ->map(fn ($path) => $namespace.Path::toRelative($path, $directory))
            ->map(fn ($path) => str_replace('/', '\\', $path))
            ->map(fn ($path) => preg_replace('/\.php$/', '', $path))
            ->get()
        ;
    }

    protected static function declaresTypeNamedAfterFile(string $file): bool
    {
        $expectedName = preg_quote(pathinfo($file, PATHINFO_FILENAME), '/');

        return (bool) preg_match("/\\b(class|interface|trait|enum)\\s+{$expectedName}\\b/", file_get_contents($file));
    }

    public static function extends($class, $parentClass, bool $considerSelfAsExtending = true): bool
    {
        if (is_string($class) && (!self::classExists($class))) {
            return false;
        }

        if ($considerSelfAsExtending && ($parentClass === $class)) {
            return true;
        }

        if ($parents = self::classParents($class)) {
            return in_array($parentClass, $parents);
        }

        return false;
    }

    public static function implements($class, $interface): bool
    {
        if (!self::classExists($class)) {
            return false;
        }

        if ($implements = self::classImplements($class)) {
            return in_array($interface, $implements);
        }

        return false;
    }

    public static function uses($class, $trait): bool
    {
        if (!self::classExists($class)) {
            return false;
        }

        if ($traits = self::classUses($class)) {
            return in_array($trait, $traits);
        }

        return false;
    }

    /**
     * @template TClass
     *
     * @param class-string<TClass> $parentClass
     *
     * @return array<class-string<TClass>>
     */
    public static function classesThatExtends(string $parentClass, bool $rejectAbstracts = true): array
    {
        self::$classIndex['extends'] ??= [];

        return self::filterClassesWithCache(
            self::$classIndex['extends'],
            ((string) $parentClass).($rejectAbstracts ? '' : '-r'),
            fn ($class) => self::extends($class, $parentClass, false),
            $rejectAbstracts
        );
    }

    /**
     * @template TInterface
     *
     * @param class-string<TInterface> $interface
     *
     * @return array<TInterface>
     */
    public static function classesThatImplements(string $interface, bool $rejectAbstracts = true): array
    {
        self::$classIndex['implements'] ??= [];

        return self::filterClassesWithCache(
            self::$classIndex['implements'],
            ((string) $interface).($rejectAbstracts ? '' : '-r'),
            fn ($class) => self::implements($class, $interface),
            $rejectAbstracts
        );
    }

    /**
     * @template TTrait
     *
     * @param class-string<TTrait> $trait
     *
     * @return array<TTrait>
     */
    public static function classesThatUses(string $trait, bool $rejectAbstracts = true): array
    {
        self::$classIndex['uses'] ??= [];

        return self::filterClassesWithCache(
            self::$classIndex['uses'],
            ((string) $trait).($rejectAbstracts ? '-r' : ''),
            fn ($class) => self::uses($class, $trait),
            $rejectAbstracts
        );
    }

    public static function classExists(string $class, bool $autoload = true): bool
    {
        self::$classIndex['lookup'] ??= array_flip(self::classesList());

        if (isset(self::$classIndex['lookup'][$class])) {
            return true;
        }

        try {
            return class_exists($class, $autoload);
        } catch (\Throwable $_) {
            return false;
        }
    }

    public static function classParents(mixed $class, bool $autoload = true): array
    {
        try {
            return class_parents($class, $autoload);
        } catch (\Throwable $_) {
            return [];
        }
    }

    public static function classImplements(mixed $class, bool $autoload = true): array
    {
        try {
            return class_implements($class, $autoload);
        } catch (\Throwable $_) {
            return [];
        }
    }

    public static function classUses(mixed $class, bool $autoload = true): array
    {
        try {
            return class_uses($class, $autoload);
        } catch (\Throwable $_) {
            return [];
        }
    }

    protected static function loadApplications()
    {
        $apps = Applications::resolve();

        $appsToExplore = array_diff($apps->paths, self::$knownApplications);

        foreach ($appsToExplore as $app) {
            if (!is_dir($app)) {
                Logger::getInstance()->warning('Cannot load {app} directory, target is not a directory', ['app' => $app]);
                continue;
            }

            self::$knownApplications[] = $app;

            $appStorage = new Storage($app);
            foreach ($appStorage->directories() as $directory) {
                $dirName = basename($directory);

                switch ($dirName) {
                    case 'Routes':
                    case 'Router':
                        array_push(self::$routesFiles, ...(new Storage($directory))->exploreFiles());
                        break;

                    case 'Assets':
                        array_push(self::$assetsFiles, ...(new Storage($directory))->exploreFiles());
                        break;

                    case 'Requires':
                    case 'Includes':
                    case 'Helpers':
                    case 'Schedules':
                    case 'Cron':
                        array_push(self::$requireFiles, ...(new Storage($directory))->exploreFiles());
                        break;

                    case 'Views':
                        array_push(self::$viewFiles, ...(new Storage($directory))->exploreFiles());
                        break;
                }
            }
        }
    }

    /**
     * @return array<class-string>
     */
    protected static function filterClassesWithCache(array &$holder, string $identifier, callable $filter, bool $rejectAbstracts = true)
    {
        if ($preprocessed = $holder[$identifier] ?? false) {
            return $preprocessed;
        }

        $classes = Bunch::of(self::classesList())->filter($filter);

        if ($rejectAbstracts) {
            $classes = $classes->filter(function ($class) {
                $reflection = new \ReflectionClass($class);

                return !$reflection->isAbstract();
            });
        }

        $holder[$identifier] = $classes->get();
        self::saveToApcu();

        return $holder[$identifier];
    }
}
