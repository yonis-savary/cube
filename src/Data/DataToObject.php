<?php

namespace Cube\Data;

use Cube\Core\Autoloader;
use Cube\Data\Classes\ArrayOf;
use Cube\Data\Classes\BunchOf;
use Cube\Env\Logger\Logger;

abstract class DataToObject
{
    public static function keys(): array
    {
        return [];
    }

    public static function bunch(array $collection): Bunch
    {
        return Bunch::of($collection)
            ->map(fn ($element) => static::fromData($element))
        ;
    }

    /**
     * @return array<static>
     */
    public static function array(array $elements): array
    {
        return static::bunch($elements)->toArray();
    }

    public static function fromData(array $rawData): static
    {
        $specialKeys = static::keys();

        $reflectionClass = new \ReflectionClass(static::class);
        $constructor = $reflectionClass->getConstructor();

        $constructorValues = [];

        foreach ($constructor->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            $parameterName = $specialKeys[$parameterName] ?? $parameterName;

            if (!array_key_exists($parameterName, $rawData)) {
                $constructorValues[] = null;

                continue;
            }

            $keyValue = $rawData[$parameterName];
            $type = self::getFirstParameterType($parameter->getType());

            if ($collectionType = $parameter->getAttributes(ArrayOf::class)[0] ?? null) {
                /** @var class-string<DataToObject> */
                $dataToObjectType = $collectionType->getArguments()[0];
                $constructorValues[] = $dataToObjectType::array($keyValue);
            } elseif ($collectionType = $parameter->getAttributes(BunchOf::class)[0] ?? null) {
                /** @var class-string<DataToObject> */
                $dataToObjectType = $collectionType->getArguments()[0];
                $constructorValues[] = $dataToObjectType::bunch($keyValue);
            } elseif (Autoloader::extends($type, DataToObject::class)) {
                // @var class-string<DataToObject> $type
                $constructorValues[] = $type::fromData($keyValue);
            } else {
                $constructorValues[] = $keyValue;
            }
        }

        try {
            return new static(...array_values($constructorValues));
        } catch (\Throwable $err) {
            $logger = Logger::getInstance();
            $logger->error("Could not create item of type {class} with data {data}", ['class' => static::class, 'data' => $constructorValues]);
            $logger->logThrowable($err);

            throw $err;
        }
    }

    protected static function getFirstParameterType(\ReflectionIntersectionType|\ReflectionNamedType|\ReflectionUnionType $type)
    {
        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof \ReflectionUnionType) {
            return self::getFirstParameterType($type->getTypes()[0]);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return (string) $type->getTypes()[0];
        }

        return 'mixed';
    }
}
