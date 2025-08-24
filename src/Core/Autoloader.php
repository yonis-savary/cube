<?php

namespace Cube\Core;

use Composer\Autoload\ClassLoader;
use Cube\Configuration\ConfigurationElement;
use Cube\Core\Autoloader\Applications;
use Cube\Core\Autoloader\AutoloaderConfiguration;
use Cube\Data\Bunch;
use Cube\Env\Cache;
use Cube\Env\Environment;
use Cube\Env\Storage;
use Cube\Exceptions\ResponseException;
use Cube\Http\Request;
use Cube\Http\Response;
use Cube\Logger\Logger;
use Cube\Models\Model;
use Cube\Utils\Path;
use Cube\Utils\Shell;
use ErrorException;
use Exception;

class Autoloader
{
    protected static array $knownApplications = [];
    protected static array $assetsFiles = [];
    protected static array $requireFiles = [];
    protected static array $routesFiles = [];

    protected static ?array $cachedClassesList = null;
    protected static ?string $projectPath = null;

    protected static AutoloaderConfiguration $configuration;

    protected static ?ClassLoader $loader;
    protected static mixed $classIndex;
    protected static Cache $autoloadCache;

    public static function initialize(?string $forceProjectPath = null, ?AutoloaderConfiguration $conf = null)
    {
        self::registerErrorHandlers();
        self::resolveProjectPath($forceProjectPath);

        self::$loader = spl_autoload_functions()[0][0];

        $cubeSrc = (new Storage(__DIR__))->parent();
        $cubeHelpers = $cubeSrc->child('Helpers');
        foreach ($cubeHelpers->files() as $helperFile) {
            include_once $helperFile;
        }

        self::$configuration = $conf ?? AutoloaderConfiguration::resolve();

        if (self::$configuration->cached) {
            $lockFile = Path::relative('composer.lock');
            $cacheIdentifier = is_file($lockFile) ? md5_file($lockFile) : 'default';
            self::$autoloadCache = Cache::getInstance()->child('AutoLoad');
            self::$classIndex = &self::$autoloadCache->getReference($cacheIdentifier, []);

            self::$knownApplications = &self::$autoloadCache->getReference("$cacheIdentifier.apps", []);
            self::$assetsFiles = &self::$autoloadCache->getReference("$cacheIdentifier.assets", []);
            self::$requireFiles = &self::$autoloadCache->getReference("$cacheIdentifier.require", []);
            self::$routesFiles = &self::$autoloadCache->getReference("$cacheIdentifier.routes", []);
        } else {
            self::$classIndex = [];
        }

        self::loadApplications();

        foreach (self::$requireFiles as $file) {
            include_once $file;
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
                $logger = new Logger('warnings.csv');
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
                $logger = new Logger('fatal.csv');
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

                $env = Environment::getInstance();
                if (!str_contains($env->get('environment', 'debug'), 'prod')) {
                    $errorMessage .= "\n\n".$exception->getMessage();
                    $errorMessage .= "\n".$exception->getTraceAsString();
                }

                $response = (new Response(500, str_replace("\n", "<br>", $errorMessage), ['Content-Type' => 'text/html']));
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

    public static function resolveProjectPath(?string $forceProjectPath = null): void
    {
        if ($forceProjectPath) {
            self::$projectPath = $forceProjectPath;
            return;
        }

        try {
            while (!is_dir('./vendor/yonis-savary/cube')) {
                chdir('..');
            }

            self::$projectPath = getcwd();
        } catch (\Throwable $_) {
            throw new \Exception('Could not resolve project root path');
        }
    }

    public static function getProjectPath(): string
    {
        if (is_null(self::$projectPath)) {
            self::resolveProjectPath();
        }

        return self::$projectPath;
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

    /**
     * @return array<class-string>
     */
    public static function classesList(): array
    {
        if (self::$classIndex['list'] ?? false) {
            return self::$classIndex['list'];
        }

        /** @var ClassLoader $loader */
        $loader = self::getClassLoader();
        $classMap = $loader->getClassMap();

        $classes = Bunch::fromKeys($classMap);
        $classMapFiles = Bunch::fromValues($classMap)->map(fn ($path) => realpath($path));

        $vendorDirectory = Path::relative('vendor');
        $cubeDirectory = Path::relative('vendor/yonis-savary/cube/src');
        $directoriesToScan = $loader->getPrefixesPsr4();

        foreach ($directoriesToScan as $namespace => $directories) {

            $directories = Bunch::of($directories)->map(realpath(...))->toArray();
            $safeDirectories = Bunch::of($directories);

            if ($directories[0] !== $cubeDirectory) {
                $safeDirectories = $safeDirectories->filter(fn($p) => !str_starts_with($p, $vendorDirectory));
            }

            foreach ($safeDirectories->toArray() as $directory) {
                if (!is_dir($directory)) {
                    Logger::getInstance()->warning('Could not read PSR4 directory [{dir}]', ['dir' => $directory]);

                    continue;
                }

                $storage = new Storage($directory);

                $files = Bunch::of($storage->exploreFiles())
                    ->filter(function ($file) use ($classMapFiles) {
                        if ($classMapFiles->has(realpath($file))) {
                            return false;
                        }

                        $expectedClassName = pathinfo($file, PATHINFO_FILENAME);
                        $content = file_get_contents($file);

                        return
                            str_contains($content, "class {$expectedClassName}")
                            || str_contains($content, "interface {$expectedClassName}")
                            || str_contains($content, "trait {$expectedClassName}");
                    })
                    ->map(fn ($path) => $namespace.Path::toRelative($path, $directory))
                    ->map(fn ($path) => str_replace('/', '\\', $path))
                    ->map(fn ($path) => preg_replace('/\..+$/', '', $path))
                    ->get()
                ;

                $classes->push(...$files);
            }
        }

        self::$classIndex['list'] = $list = $classes->uniques()->get();

        return $list;
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
            ((string) $trait).($rejectAbstracts ? '' : '-r'),
            fn ($class) => self::uses($class, $trait),
            $rejectAbstracts
        );
    }

    public static function classExists(string $class, bool $autoload = true): bool
    {
        if (in_array($class, self::classesList())) {
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

    public static function instanciate(string $class, array $args=[])
    {
        $parameters = [];
        if (method_exists($class, '__construct'))
            $parameters = self::getDependencies([$class, '__construct'], $args);

        return new $class(...$parameters);
    }

    protected static function call(callable|\Closure $callback, array $args=[])
    {
        $args = self::getDependencies($callback, $args);

        return ($callback)(...$args);
    }

    public static function getDependencies(callable|array $callback, array $initialValues): array
    {
        if (is_array($callback)) {
            $controller = new \ReflectionClass($callback[0]);
            $reflection = $controller->getMethod($callback[1]);
        } else {
            $reflection = new \ReflectionFunction($callback);
        }
        $parameters = $reflection->getParameters();

        if (!count($parameters)) {
            return $initialValues;
        }

        $injectedParams = [];

        for ($i = 0; $i < count($parameters); ++$i) {
            $parameter = $parameters[$i];
            $type = $parameter->getType();
            $requestType = $type ? $type->getName() : Request::class;

            if (!isset($initialValues[$i])) {
                if (Autoloader::uses($requestType, Component::class)) {
                    $injected = $requestType::getInstance();
                } elseif (Autoloader::extends($requestType, ConfigurationElement::class)) {
                    $injected = $requestType::resolve();
                } elseif (class_exists($requestType)) {
                    $injected = self::instanciate($requestType);
                } elseif ($parameter->isOptional() && $default = $parameter->getDefaultValue()) {
                    $injected = $default;
                } else {
                    throw new \InvalidArgumentException('Could not create dependency injection values for callback, no value for '.$parameter->getName().' parameter');
                }

                $injectedParams[] = $injected;

                continue;
            }

            $injected = $initialValues[$i];

            if (Autoloader::extends($requestType, Request::class)) {
                /** @var Request $requestType */
                $request = $requestType::fromRequest($injected);

                $result = $request->isValid();
                if (true !== $result) {
                    throw new ResponseException('Given request is not valid', Response::unprocessableContent(json_encode($result, JSON_THROW_ON_ERROR)));
                }

                $injected = $request;
            }
            elseif (Autoloader::extends($requestType, Model::class)) {
                $key = $injected;
                $injected = $requestType::find($key);
                if (null === $injected) {
                    throw new ResponseException("{$requestType} not found with id ({$key})", Response::notFound('Resource not found'));
                }
            }

            $injectedParams[] = $injected;
        }

        return $injectedParams;
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

            $app = new Storage($app);
            foreach ($app->directories() as $directory) {
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

        return $holder[$identifier] = $classes->get();
    }
}
