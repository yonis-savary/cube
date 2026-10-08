<?php

namespace Cube\Console\Commands\Model;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Data\Database\Database;
use Cube\Env\Storage;
use Cube\Data\Models\ModelGenerator;
use Cube\Data\Models\ModelGenerator\GeneratedModels;
use Cube\Utils\Console;
use Cube\Utils\Path;
use Cube\Web\ModelAPI\ModelAPIGenerator;

class Generate extends Command
{
    public function getHelp(): string
    {
        return 'Generate model files inside your application';
    }

    public function getManual(): array
    {
        return [
            'models:generate [--apis]',
            '',
            'Generate one model class per table of your database, inside the Models directory of your application',
            '',
            '-a, --apis  Also generate a ModelAPI subclass per model in Http/Apis (ProductAPI for Product),',
            '            existing files are left untouched',
        ];
    }

    public function getScope(): string
    {
        return 'models';
    }

    public function execute(Args $args): int
    {
        $app = Console::chooseApplication();
        $modelsStorage = (new Storage($app))->child('Models');

        $generator = ModelGenerator::getInstance();

        $files = $generator->processDatabase(
            Database::getInstance(),
            $modelsStorage
        );

        foreach ($files as $file) {
            Console::log('Generated '.$file);
        }

        (new GeneratedModels)->dispatch();

        if ($args->has('-a', '--apis'))
            $this->generateApis($files, $modelsStorage, (new Storage($app))->child('Http/Apis'));

        return 0;
    }

    /**
     * @param string[] $modelFiles
     */
    protected function generateApis(array $modelFiles, Storage $modelsStorage, Storage $apisStorage): void
    {
        $modelsNamespace = Path::pathToNamespace($modelsStorage->getRoot());
        $apisNamespace = Path::pathToNamespace($apisStorage->getRoot());
        $generator = ModelAPIGenerator::getInstance();

        foreach ($modelFiles as $modelFile) {
            $modelClass = $modelsNamespace.'\\'.basename($modelFile, '.php');

            if ($file = $generator->generateInto($apisStorage, $apisNamespace, $modelClass))
                Console::log('Generated '.$file);
        }
    }
}
