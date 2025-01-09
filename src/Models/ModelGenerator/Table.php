<?php

namespace YonisSavary\Cube\Models\ModelGenerator;

use YonisSavary\Cube\Data²base\Query\Field;
use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Models\Model;
use YonisSavary\Cube\Models\ModelField;

class Table
{
    /** @var array<ModelField> */
    public array $fields;

    public function __construct(
        public readonly string $table,
        array $fields=[]
    )
    {
        $this->fields = $fields;
    }

    public function getClassname(): string
    {
        $table = $this->table;
        $table = preg_replace_callback('/([a-z])_([a-z])/', function($m){
            return $m[1] . strtoupper($m[2]);
        }, $table);
        $table = ucfirst($table);

        return $table;
    }

    public function addField(ModelField $field): self
    {
        $this->fields[] = $field;
        return $this;
    }


    /**
     * @param Storage $destination
     * @param string $namespace
     * @param array<Relation> $constraints
     */
    public function generateInto(Storage $destination, string $namespace, array $constraints)
    {
        $className = $this->getClassname();

        $fileContent = "
        <?php

        use ".Model::class.";

        namespace $namespace;

        class $className extends Model
        {
            public static function table(): string
            {
                return '".$this->table."';
            }

            public static function fields(): array
            {
                return [];
            }
        }
        ";

        $fileName = "$className.php";
        $destination->write($fileName, $fileContent);
        Logger::getInstance()->info("Model written at [{path}]", ["path" => $destination->path($fileName)]);
    }
}