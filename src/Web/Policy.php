<?php

namespace Cube\Web;

use Closure;
use Cube\Core\Autoloader;
use Cube\Data\Models\Model;
use Cube\Web\Http\Response;
use Cube\Web\Http\Request;
use ReflectionMethod;
use ReflectionUnionType;
use RuntimeException;

/**
 * @template T of Model
 */
abstract class Policy implements Middleware
{
    /**
     * @param Model|T $model
     */
    abstract public static function verify(Model $model, Request $request): Request|Response;

    protected static function resolveModelParameter(Request $request): Model
    {
        $method = new ReflectionMethod(static::class, 'verify');
        $modelParameter = $method->getParameters()[0]->getType();

        $modelType = $modelParameter instanceof ReflectionUnionType 
            ? array_last($modelParameter->getTypes())
            : $modelParameter->getType()
        ;

        if (!$modelParameter) {
            throw new RuntimeException("Could not resolve verify() parameter type, please strong-type it in your policy code");
        }
        /** @var class-string<Model> $modelClass */
        $modelClass = $modelType->getName();

        if (!Autoloader::extends($modelClass, Model::class)) {
            throw new RuntimeException("\$model type must extends Model class");
        }

        foreach ($request->getSlugObjects() as $object) {
            if ($object instanceof $modelClass) {
                /** @var Model $object */
                return $object;
            }
        }

        throw new RuntimeException("Could not resolve parameter of type $modelClass for provided request");
    }

    public static function handle(Request $request, Closure $next): Request|Response
    {
        $model = static::resolveModelParameter($request);
        $policyResponse = static::verify($model, $request);
        if ($policyResponse instanceof Response)
            return $policyResponse;

        return $next($request);
    }
}