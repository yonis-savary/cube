<?php

namespace Cube\Web;

use Cube\Data\Models\Model;
use Cube\Security\Authentication;
use Cube\Web\Http\Response;
use Cube\Web\Http\Request;

abstract class AuthPolicy extends Policy
{
    abstract public static function authorize(Model $model, Model $user, Request $request): Request|Response;

    public static function verify(Model $model, Request $request): Request|Response {
        $authentication = Authentication::getInstance();
        $user = $authentication->user();
        return static::authorize($model, $user, $request);
    }

    /**
     * @param \Closure(Model,Request) $callback
     */
    public static function authorizeCallback(Model $model, Request $request, callable $callback){
        $policyReturn = self::verify($model, $request);
        if ($policyReturn instanceof Request)
            $callback($model, $request);
    }
}