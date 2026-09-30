<?php

namespace Cube\Core;

use Cube\Core\Exceptions\ResponseException;
use Cube\Data\Bunch;
use Cube\Data\Models\Model;
use Cube\Env\Configuration\ConfigurationElement;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use InvalidArgumentException;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

class Injector
{
    use Component;

    protected array $provided = [];
    protected array $providedVariadic = [];

    /**
     * Specify to Injector which value, callback must be provided
     * when a specific class/interface/trait request a certain type for a parameter
     *
     * @param string $class Class, Interface or Trait full name
     * @param mixed|\Closure(string):mixed $elementOrCallback Provided Class/Interface/Trait or some callback that resolve the good object
     * @note If a callback is provided, the first argument given when called shalled be the requester class
     */
    public function provide(string $class, mixed $elementOrCallback): void
    {
        $this->provided[$class] = $elementOrCallback;
    }

    /**
     * Specify to Injector which value, callback must be provided
     * when a specific class/interface/trait request a certain type for a variadic parameter
     *
     * @param string $class Class, Interface or Trait full name
     * @param mixed|\Closure(string):mixed $elementOrCallback Provided Class/Interface/Trait or some callback that resolve the good object
     * @note If a callback is provided, the first argument given when called shalled be the requester class
     */
    public function provideVariadic(string $class, mixed $elementOrCallback): void
    {
        $this->providedVariadic[$class] = $elementOrCallback;
    }

    protected function valueOrInstantiate(mixed $value): mixed {
        return is_string($value)
            ? $this->instanciate($value)
            : $value
        ;
    }

    protected function assertProvidedRespectType(string $class, mixed $provided) : void {
        if ($provided === null)
            return;

        $providedClass = $provided::class;
        if (class_exists($class)) {
            Autoloader::extends($providedClass, $class)
                || throw new RuntimeException("Provided class of type $providedClass does not extends $class");
        }
        else if (interface_exists($class)) {
            Autoloader::implements($providedClass, $class)
                || throw new RuntimeException("Provided class of type $providedClass does not implements interface $class");
        }
        else if (trait_exists($class)) {
            Autoloader::uses($providedClass, $class)
                || throw new RuntimeException("Provided class of type $providedClass does not use $class trait");
        }
        else {
            throw new InvalidArgumentException("Could not determine if $class is a Class/Interface/Trait");
        }
    }

    public function getProvidedValue(string $class, bool $variadic = false, ?string $caller = null): mixed {
        $store = $variadic ? $this->providedVariadic : $this->provided;
        if (!array_key_exists($class, $store))
            return null;

        $value = $store[$class];

        if (is_callable($value))
            $value = $value($caller);

        if (!$variadic) {
            $value = $this->valueOrInstantiate($value);

            $this->assertProvidedRespectType($class, $value);

            return $value;
        }

        return Bunch::of($value)
            ->map(fn($value) => $this->valueOrInstantiate($value))
            ->forEach(fn($subvalue) => $this->assertProvidedRespectType($class, $subvalue))
        ;
    }

    /**
     * @template TClass
     * @param class-string<TClass> $class
     * @return TClass
     */
    public function instanciate(string $class, array $args=[], ?string $caller = null)
    {
        if ($provided = $this->getProvidedValue($class, false, $caller))
            return $provided;

        $parameters = method_exists($class, '__construct')
            ? $this->getDependencies([$class, '__construct'], $args)
            : $args;

        return new $class(...$parameters);
    }

    /**
     * @return ReflectionParameter[]
     */
    public function resolveClosureParameters(callable|array $callback): array
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
    public function getDependencies(callable|array $callback, array $initialValues=[]): array
    {
        $parameters = $this->resolveClosureParameters($callback);

        if (!count($parameters)) {
            return $initialValues;
        }

        $caller = is_array($callback) // [class, method]
            ? $callback[0]
            : null;

        $injectedParams = [];

        for ($i = 0; $i < count($parameters); ++$i) {
            $parameter = $parameters[$i];

            if ($parameter->isVariadic()) {
                array_push($injectedParams, ...$this->resolveVariadicParameter($parameter, $caller)->toArray());
                continue;
            }

            $injectedParams[] = array_key_exists($i, $initialValues)
                ? $this->resolveParameterFromGivenValue($parameter, $initialValues[$i])
                : $this->resolveParameterFromNothing($parameter, $caller)
            ;
        }

        return $injectedParams;
    }

    protected function resolveParameterTypeName(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();
        return $type instanceof ReflectionNamedType ? $type->getName() : null;
    }

    protected function resolveVariadicParameter(ReflectionParameter $parameter, ?string $caller = null): Bunch {
        if (!$classname = $this->resolveParameterTypeName($parameter))
            throw new RuntimeException("Variadic parameter \${$parameter->getName()} must name a single class or interface, got ".$parameter->getType());

        if ($provided = $this->getProvidedValue($classname, true, $caller))
            return $provided;

        if (interface_exists($classname))
            return Bunch::fromImplements($classname);

        if (class_exists($classname))
            return Bunch::fromExtends($classname);

        throw new RuntimeException("Could not make values for type $classname");
    }

    protected function resolveParameterFromNothing(ReflectionParameter $parameter, ?string $caller = null) {
        if (!$requestType = $this->resolveParameterTypeName($parameter)) {
            if ($parameter->isOptional() && $parameter->isDefaultValueAvailable())
                return $parameter->getDefaultValue();

            throw new \InvalidArgumentException("Could not create dependency injection values for callback, \${$parameter->getName()} is typed ".$parameter->getType().' and no single class can be resolved from it');
        }

        if ($provided = $this->getProvidedValue($requestType, false, $caller))
            return $provided;

        if (Autoloader::uses($requestType, Component::class))
            return $requestType::getInstance();

        if (Autoloader::extends($requestType, ConfigurationElement::class))
            return $requestType::resolve();

        if (class_exists($requestType))
            return $this->instanciate($requestType, caller: $caller);

        if ($parameter->isOptional() && $parameter->isDefaultValueAvailable())
            return $parameter->getDefaultValue();

        throw new \InvalidArgumentException('Could not create dependency injection values for callback, no value for '.$parameter->getName().' parameter');
    }

    protected function resolveParameterFromGivenValue(ReflectionParameter $parameter, mixed $injected) {
        if (!$requestType = $this->resolveParameterTypeName($parameter))
            return $injected;

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
