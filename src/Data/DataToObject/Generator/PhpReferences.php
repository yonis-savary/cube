<?php

namespace Cube\Data\DataToObject\Generator;

use Cube\Data\Bunch;

/**
 * How a generated class refers to the classes it needs : its short name, imported when it lives in
 * another namespace, or its fully qualified name when that short name is already taken
 */
class PhpReferences
{
    /** @var array<string,string> fully qualified name => name to write */
    protected array $names = [];

    /**
     * @param string[] $fullNames
     */
    public function __construct(
        protected string $namespace,
        string $className,
        array $fullNames,
    ) {
        $takenShortNames = [strtolower($className)];
        foreach (array_unique($fullNames) as $fullName) {
            $shortName = preg_replace('/.+\\\/', '', $fullName);

            if (in_array(strtolower($shortName), $takenShortNames, true) && self::namespaceOf($fullName) !== $namespace) {
                $this->names[$fullName] = '\\'.$fullName;
                continue;
            }

            $takenShortNames[] = strtolower($shortName);
            $this->names[$fullName] = $shortName;
        }
    }

    public function nameOf(string $fullName): string
    {
        return $this->names[$fullName];
    }

    public function toUses(): string
    {
        $uses = Bunch::unzip($this->names)
            ->filter(fn ($pair) => !str_starts_with($pair[1], '\\') && self::namespaceOf($pair[0]) !== $this->namespace)
            ->map(fn ($pair) => "use {$pair[0]};")
            ->sort()
            ->join("\n");

        return $uses ? "{$uses}\n\n" : '';
    }

    protected static function namespaceOf(string $fullName): string
    {
        return preg_replace('/\\\[^\\\]+$/', '', $fullName);
    }
}
