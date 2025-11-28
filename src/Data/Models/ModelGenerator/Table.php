<?php

namespace Cube\Data\Models\ModelGenerator;

use Cube\Data\Bunch;
use Cube\Env\Storage;
use Cube\Env\Logger\Logger;
use Cube\Data\Models\DummyModel;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use Cube\Data\Models\Relations\HasMany;
use Cube\Data\Models\Relations\HasOne;
use Cube\Data\Models\Relations\Relation;
use Cube\Utils\Attributes\Generated;
use Cube\Utils\Console;
use Cube\Utils\Path;
use Cube\Utils\Text;

class Table
{
    /** @var ModelField[] */
    public array $fields;

    public function __construct(
        public readonly string $table,
        array $fields = [],
        public readonly ?string $primaryKey = null
    ) {
        $this->fields = $fields;
    }

    public static function getClassname(string $tableName): string
    {
        $tableName = preg_replace_callback('/([a-z])_([a-z])/', function ($m) {
            return $m[1].strtoupper($m[2]);
        }, $tableName);

        return ucfirst($tableName);
    }

    public function addField(ModelField $field): self
    {
        $this->fields[] = $field;

        return $this;
    }

    public function getClassNonGeneratedMethods(string $className): array
    {
        $class = new \ReflectionClass($className);

        $file = $class->getFileName();
        $lines = explode("\n", file_get_contents($file));

        $methods = [];

        foreach ($class->getMethods() as $method) {
            if ($method->getFileName() != $file) {
                continue;
            }

            if (count($method->getAttributes(Generated::class))) {
                continue;
            }

            $trueStartLine = $method->getStartLine();
            while ('' != trim($lines[$trueStartLine])) {
                --$trueStartLine;
                if (0 == $trueStartLine) {
                    throw new \RuntimeException('Could not determine start line of method '.$method->getName().' in '.$method->getFileName());
                }
            }
            ++$trueStartLine;

            $methodContent = array_slice($lines, $trueStartLine, $method->getEndLine() - $trueStartLine);

            Console::print(
                Console::withBlueColor(
                    '- Recovering non-generated method ['.$method->getName().'] in '.Path::toRelative($method->getFileName())
                )
            );

            $methods[] = Bunch::of($methodContent)->map(fn ($line) => "\t\t{$line}")->join("\n");
        }

        return $methods;
    }

    /**
     * @param Relation[] $relations
     */
    public function generateInto(Storage $destination, string $namespace, array $relations): ?string
    {
        $className = self::getClassname($this->table);

        $relationsNames = [];

        $existingMethod = [];
        $fullClassname = "{$namespace}\\{$className}";
        if (class_exists($fullClassname)) {
            $existingMethod = $this->getClassNonGeneratedMethods($fullClassname);
        }

        $relationProps = Bunch::of($relations)
            ->map(fn($relation) => $relation->getName())
            ->get();

        $fieldsNames = Bunch::of($this->fields)->key('name');

        $hasOneRelations = Bunch::of($relations)
            ->onlyInstancesOf(HasOne::class)
            ->filter(fn (HasOne $relation) => $relation->concern($className))
            ->filter(fn (HasOne $relation) => $relation->fromModel === $className);

        $addedRelationNames = [];

        $hasManyRelations = Bunch::of($relations)
        ->onlyInstancesOf(HasOne::class)
        ->filter(fn (HasOne $relation) => $relation->concern($className))
        ->filter(fn (HasOne $relation) => $relation->fromModel !== $className)
        ->map(function (HasOne $relation) use (&$relationsNames, $fieldsNames, &$addedRelationNames) {
            $toModel    = $relation->fromModel;
            $toColumn   = $relation->fromColumn;
            $fromModel  = $relation->toModel;
            $fromColumn = $relation->toColumn;

            $relationName = Text::endsWith(str_replace(
                strtolower(basename(str_replace('\\', '/', $fromModel))),
                '',
                strtolower(basename(str_replace('\\', '/', $toModel)))
            ), 's');

            while ($fieldsNames->has($relationName) || in_array($relationName, $addedRelationNames))
                $relationName = "_$relationName";

            $addedRelationNames[] = $relationName;

            $dummyModel = new DummyModel();
            $relation = new HasMany($relationName, $fromModel, $fromColumn, $toModel, $toColumn, $dummyModel);
            $relationName = $relation->getName();

            $relationsNames[] = $relationName;

            return $relation;
        });

        $mergedRelations = Bunch::of($hasOneRelations)->merge($hasManyRelations);

        $fileContent = Text::toFile("
        <?php

        namespace {$namespace};

        use ".Model::class.';
        use '.ModelField::class.';
        use '.HasMany::class.';
        use '.HasOne::class.';
        use '.Generated::class.';

        /**
         * Generated by `'.self::class."`\n"
            .Bunch::of($this->fields)
                ->filter(fn(ModelField $field) => !in_array($field->name, $relationProps))
                ->map(fn (ModelField $field) => $this->getFieldPHPDoc($field))
                ->join("\n")."\n"

            .$this->getRelationsPHPDoc($className, $mergedRelations)
        ."
         */
        class {$className} extends Model
        {
            #[Generated]
            public static function table(): string
            {
                return '".$this->table."';
            }"

            .(($primary = $this->primaryKey) ? "

            #[Generated]
            public static function primaryKey(): string
            {
                return '{$primary}';
            }" : '')."

            /**
             * @return array<string,ModelField>
             */
            #[Generated]
            public static function fields(): array
            {
                return [\n"
                    .Bunch::of($this->fields)
                        ->map(
                            fn (ModelField $field) => "                    '".$field->name."' => ".$field->toPHPExpression()
                        )
                        ->join(",\n")
                ."
                ];
            }


            \n".Bunch::of($hasOneRelations)
            ->map(function (HasOne $relation) use (&$relationsNames) {
                $fromColumn = $relation->fromColumn;
                $toModel = $relation->toModel;
                $relationName = $relation->getName();
                $toColumn = $relation->toColumn;

                $relationsNames[] = $relationName;

                return Text::toFile("
                #[Generated]
                public function {$relationName}(): HasOne
                {
                    return \$this->hasOne('$relationName', '{$fromColumn}', {$toModel}::class, '{$toColumn}');
                }
                ", 1);
                    })->join("\n\n")."

            \n".Bunch::of($hasManyRelations)
            ->map(function (HasMany $relation) {
                $toModel    = $relation->toModel;
                $toColumn   = $relation->toColumn;
                $fromColumn = $relation->fromColumn;
                $relationName = $relation->getName();

                return Text::toFile("
                #[Generated]
                public function {$relationName}(): HasMany
                {
                    return \$this->hasMany('$relationName', {$toModel}::class, '{$toColumn}', '{$fromColumn}');
                }
                ", 1);
                    })->join("\n\n").'


            #[Generated]
            public static function relations(): array
            {
                return ['.Bunch::of($relationsNames)
                    ->map(fn ($x) => '
                    "'.$x.'",')
                    ->join('')
                    .'
                ];
            }

            '.(
                        count($existingMethod)
                    ? ("\n".join("\n\n", $existingMethod))
                    : ''
                    ).'
        }
        ');

        $fileContent = preg_replace('/^ +$/m', '', $fileContent);
        $fileContent = preg_replace("/\n{3,}/", "\n\n", $fileContent);

        $fileName = "{$className}.php";
        $destination->write($fileName, $fileContent);

        $writtenFile = $destination->path($fileName);
        Logger::getInstance()->info('Model written at [{path}]', ['path' => $writtenFile]);

        return $writtenFile;
    }

    protected function getFieldPHPDoc(ModelField $field): string
    {
        $phpDocType = match ($field->type) {
            ModelField::STRING => 'string',
            ModelField::INTEGER => 'int',
            ModelField::FLOAT => 'float',
            ModelField::BOOLEAN => 'bool',
            ModelField::DECIMAL => 'string',
            ModelField::DATE => '\DateTime',
            ModelField::DATETIME => '\DateTime',
            ModelField::TIMESTAMP => 'string',
        };

        return " * @property {$phpDocType} $".$field->name;
    }

    /**
     * @param array<Relation> $relations
     */
    protected function getRelationsPHPDoc(string $className, Bunch $relations): string
    {
        return join("\n", [
            ...Bunch::of($relations)
                ->onlyInstancesOf(HasOne::class)
                ->map(function (HasOne $relation) {
                    $toModel = $relation->toModel;

                    return ' * @property '.$toModel.' $'.$relation->getName();
                })->toArray(),

            ...Bunch::of($relations)
                ->onlyInstancesOf(HasMany::class)
                ->map(function (HasMany $relation) {
                    $toModel = $relation->toModel;

                    return ' * @property '.$toModel.'[] $'.$relation->getName();
                })->toArray(),
        ]);
    }
}
