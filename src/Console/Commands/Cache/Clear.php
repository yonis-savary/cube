<?php

namespace Cube\Console\Commands\Cache;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Env\Cache;
use Cube\Utils\Console;
use Cube\Utils\Path;

class Clear extends Command
{
    public function getHelp(): string
    {
        return "Clear every files in Storage/Cache";
    }

    public function getScope(): string
    {
        return "cache";
    }

    public function execute(Args $args): int
    {
        $cacheDirectory = Cache::getInstance()->getStorage();

        $files = array_reverse($cacheDirectory->exploreFiles());
        Console::log("Deleting files...");
        Console::withProgressBar($files, function($file){
            Console::log("Deleting [". Path::toRelative($file) ."]");
            unlink($file);
        });

        $directories = array_reverse($cacheDirectory->exploreDirectories());
        Console::log("Deleting sub-directories");
        Console::withProgressBar($directories, function($directory) {
            Console::log("Removing [". Path::toRelative($directory) ."] directory");
            rmdir($directory);
        });

        Console::log("", Console::withGreenColor("Cache cleared !"), "");

        return 0;
    }
}