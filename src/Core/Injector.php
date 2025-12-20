<?php

namespace Cube\Core;

use Cube\Core\Exceptions\ResponseException;
use Cube\Data\Bunch;
use Cube\Data\Models\Model;
use Cube\Env\Configuration\ConfigurationElement;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use ReflectionParameter;
use RuntimeException;

class Injector
{

    /**
     * @template TClass
     * @param class-string<TClass> $class
     * @return TClass
     */
    public static function instanciate(string $class, array $args=[])
    {
        $parameters = [];
        if (method_exists($class, '__construct'))
            $parameters = self::getDependencies([$class, '__construct'], $args);

        return new $class(...$parameters);
    }

    /**
     * @return ReflectionParameter[]
     */
    public static function resolveClosureParameters(callable|array $callback): array
    {
        if (is_array($callback)) {
            $controller = new \ReflectionClass($callback[0]);
            $reflection = $controller->getMethod($callback[1]);
        } else {
            $reflection = new \ReflectionFunction($callback);
        }
        return $reflection->getParameters();
    }

    /**
     * @return array<mixed>
     */
    public static function getDependencies(callable|array $callback, array $initialValues=[]): array
    {
        $parameters = self::resolveClosureParameters($callback);

        if (!count($parameters)) {
            return $initialValues;
        }

        $injectedParams = [];

        for ($i = 0; $i < count($parameters); ++$i) {
            $parameter = $parameters[$i];

            if ($parameter->isVariadic()) {
                array_push($injectedParams, ...self::resolveVariadicParameter($parameter)->toArray());
                continue;
            }

            $injectedParams[] = isset($initialValues[$i])
                ? self::resolveParameterFromGivenValue($parameter, $initialValues[$i])
                : self::resolveParameterFromNothing($parameter)
            ;
        }

        return $injectedParams;
    }

    protected static function resolveVariadicParameter(ReflectionParameter $parameter): Bunch {
        $classname = $parameter->getType()->getName();

        if (interface_exists($classname))
            return Bunch::fromImplements($classname);

        if (class_exists($classname))
            return Bunch::fromExtends($classname);

        throw new RuntimeException("Could not make values for type $classname");
    }

    protected static function resolveParameterFromNothing(ReflectionParameter $parameter) {
        $type = $parameter->getType();
        $requestType = $type ? $type->getName() : Request::class;

        if (Autoloader::uses($requestType, Component::class))
            return $requestType::getInstance();

        if (Autoloader::extends($requestType, ConfigurationElement::class))
            return $requestType::resolve();

        if (class_exists($requestType))
            return Injector::instanciate($requestType);

        if ($parameter->isOptional() && $default = $parameter->getDefaultValue())
            return $default;

        throw new \InvalidArgumentException('Could not create dependency injection values for callback, no value for '.$parameter->getName().' parameter');
    }

    protected static function resolveParameterFromGivenValue(ReflectionParameter $parameter, mixed $injected) {
        $type = $parameter->getType();
        $requestType = $type ? $type->getName() : Request::class;

        if (Autoloader::extends($requestType, Request::class)) {
            /** @var Request $request */
            $request = $requestType::fromRequest($injected);

            $result = $request->validate();
            if (!$result->isValid())
                throw new ResponseException(
                    'Given request is not valid', 
                    Response::unprocessableContent(json_encode($result->getErrors(), JSON_THROW_ON_ERROR))
                );

            return $request;
        }
        elseif (Autoloader::extends($requestType, Model::class)) {
            $key = $injected;
            if ($foundModel = $requestType::find($key))
                return $foundModel;

            throw new ResponseException("{$requestType} not found with id ({$key})", Response::notFound('Resource not found'));

        }

        return $injected;
    }


}