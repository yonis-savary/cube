<?php

namespace Cube\Web\ModelAPI;

use Cube\Core\Component;
use Cube\Data\Models\Model;
use Cube\Env\Logger\Logger;
use Cube\Env\Storage;
use Cube\Utils\Text;

class ModelAPIGenerator
{
    use Component;

    /**
     * @param class-string<Model> $modelClass
     * @return ?string Path of the written file, `null` when nothing was written
     */
    public function generateInto(Storage $destination, string $namespace, string $modelClass): ?string
    {
        if (str_starts_with($modelClass::table(), '__'))
            return null;

        $modelName = preg_replace('~.+\\\\~', '', $modelClass);
        $className = "{$modelName}API";
        $fileName = "{$className}.php";

        if ($destination->exists($fileName))
            return null;

        $destination->write($fileName, Text::toFile("
        <?php

        namespace {$namespace};

        use {$modelClass};
        use ".ModelAPI::class.";

        /**
         * @extends ModelAPI<{$modelName}>
         */
        class {$className} extends ModelAPI
        {
            public function getModelClass(): string
            {
                return {$modelName}::class;
            }
        }
        ")."\n");

        $writtenFile = $destination->path($fileName);
        Logger::getInstance()->info('Model API written at [{path}]', ['path' => $writtenFile]);

        return $writtenFile;
    }
}
