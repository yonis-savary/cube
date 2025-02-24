<?php

namespace Cube\Utils;

use Stringable;
use Cube\Core\Autoloader\Applications;
use Cube\Data\Bunch;
use Cube\Logger\Logger;

/**
 * @todo Implement no-ansi mode
 */
class Console
{
    const BLACK     = 0;
    const RED       = 1;
    const GREEN     = 2;
    const YELLOW    = 3;
    const BLUE      = 4;
    const MAGENTA   = 5;
    const CYAN      = 6;
    const WHITE     = 7;
    const DEFAULT   = 9;

    protected static ?Logger $logger = null;

    public static function getLogger(): Logger
    {
        if (!self::$logger)
            self::$logger = new Logger("console.csv");

        return self::$logger;
    }

    public static function saveCursor(): void
    {
        echo "7";
    }

    public static function restoreCursor(): void
    {
        echo "8";
    }

    public static function eraseFromCursor(): void
    {
        echo "[0J";
    }

    public static function reset(): void
    {
        echo "[0m";
    }

    public static function print(string|Stringable ...$elements): void
    {
        if (!str_contains(php_sapi_name(), "cli"))
            return;

        foreach ($elements as $element)
            echo $element . "\n";
    }

    public static function log(string|Stringable ...$elements): void
    {
        self::print(...$elements);

        $logger = self::getLogger();

        Bunch::of($elements)
        ->filter(fn($x) => $x !== "" && $x !== null)
        //->map(fn($x) => preg_replace("/[^ ]+/", "", (string) $x))
        ->map(fn($x) => preg_replace("/(?:[@-Z\\-_]|\\[[0-?]*[ -\\/]*[@-~])/", "", $x))
        ->forEach(fn($x) => $logger->info($x));
    }

    public static function withColor(string $string, int $colorCode, bool $bright=false)
    {
        $colorCode += 30;
        $brightStr = $bright ? ";1": "";
        return "[$colorCode" . $brightStr . "m" . $string . "[0m";
    }

    public static function withBackground(string $string, int $colorCode, bool $bright=false)
    {
        $colorCode += 40;
        $brightStr = $bright ? ";1": "";
        return "[$colorCode" . $brightStr . "m" . $string . "[0m";
    }

    public static function withBlackColor       (string $string, bool $bright=false) { return self::withColor($string, self::BLACK, $bright); }
    public static function withRedColor         (string $string, bool $bright=false) { return self::withColor($string, self::RED, $bright); }
    public static function withGreenColor       (string $string, bool $bright=false) { return self::withColor($string, self::GREEN, $bright); }
    public static function withYellowColor      (string $string, bool $bright=false) { return self::withColor($string, self::YELLOW, $bright); }
    public static function withBlueColor        (string $string, bool $bright=false) { return self::withColor($string, self::BLUE, $bright); }
    public static function withMagentaColor     (string $string, bool $bright=false) { return self::withColor($string, self::MAGENTA, $bright); }
    public static function withCyanColor        (string $string, bool $bright=false) { return self::withColor($string, self::CYAN, $bright); }
    public static function withWhiteColor       (string $string, bool $bright=false) { return self::withColor($string, self::WHITE, $bright); }
    public static function withDefaultColor     (string $string, bool $bright=false) { return self::withColor($string, self::DEFAULT, $bright); }
    public static function withBlackBackground  (string $string, bool $bright=false) { return self::withBackground($string, self::BLACK, $bright); }
    public static function withRedBackground    (string $string, bool $bright=false) { return self::withBackground($string, self::RED, $bright); }
    public static function withGreenBackground  (string $string, bool $bright=false) { return self::withBackground($string, self::GREEN, $bright); }
    public static function withYellowBackground (string $string, bool $bright=false) { return self::withBackground($string, self::YELLOW, $bright); }
    public static function withBlueBackground   (string $string, bool $bright=false) { return self::withBackground($string, self::BLUE, $bright); }
    public static function withMagentaBackground(string $string, bool $bright=false) { return self::withBackground($string, self::MAGENTA, $bright); }
    public static function withCyanBackground   (string $string, bool $bright=false) { return self::withBackground($string, self::CYAN, $bright); }
    public static function withWhiteBackground  (string $string, bool $bright=false) { return self::withBackground($string, self::WHITE, $bright); }
    public static function withDefaultBackground(string $string, bool $bright=false) { return self::withBackground($string, self::DEFAULT, $bright); }


    public static function withProgressBar(array $elements, callable $callback)
    {
        $elementsCount = count($elements);

        $barSize = 50;

        self::saveCursor();

        for ($i=1; $i<=$elementsCount; $i++)
        {
            ob_start();
            $element = $elements[$i-1];
            $callback($element);

            if ($output = ob_get_clean())
            {
                self::restoreCursor();
                self::eraseFromCursor();
                Console::log(... Bunch::fromExplode("\n", $output)->filter()->get());
                self::saveCursor();
            }

            self::restoreCursor();

            $progress = floor(($barSize * $i) / $elementsCount);
            $remain = $barSize - $progress;
            echo "[" . str_repeat("=", max(0, $progress-1)) . ">" . str_repeat(" ", $remain) . "] $i / $elementsCount";
        }

        echo "\n";
    }


    public static function table(array $data, array $columns=[], bool $logToo=true): void
    {
        $print = $logToo ?
            fn($x) => Console::log($x):
            fn($x) => print($x . "\n");

        if (!count($data))
        {
            if ($columns)
            {
                $columnLine = join("  ", $columns);
                $print($columnLine);
                $print(str_repeat("-", strlen($columnLine)));
                return;
            }
            $print("No item to display");
            return;
        }

        $firstItem = $data[0];
        $columns ??= Utils::isAssoc($firstItem) ? array_keys($firstItem) : $firstItem;
        if ($columns === $firstItem)
            array_pop($data);

        $columnsSizes = array_fill(0, count($columns), 0);
        $updateColumnsSizes = function(array $data) use (&$columnsSizes) {
            for ($i=0; $i<count($data); $i++)
                $columnsSizes[$i] = max($columnsSizes[$i], strlen($data[$i]));
        };

        $printValueLine = function(array $data) use (&$columnsSizes, $print) {
            $line = "";
            for ($i=0; $i<count($data); $i++)
            {
                $bit = $data[$i];
                $bitLength = strlen($bit);
                $line .= $bit . str_repeat(" ", ($columnsSizes[$i] - $bitLength) + 2);
            }
            $print($line);
            return strlen($line);
        };

        $updateColumnsSizes($columns);

        $dataCount = count($data);
        for ($i=0; $i<$dataCount; $i++)
            $updateColumnsSizes($data[$i]);


        $length = $printValueLine($columns);
        $print(str_repeat("-", $length));

        foreach ($data as $row)
            $printValueLine($row);
    }


    public static function promptList(string $prompt, array $choices, ?int $defaultChoiceIndex=null): array
    {
        $hasDefault = $defaultChoiceIndex !== null;
        $defaultChoiceString = $hasDefault ? (string) $choices[$defaultChoiceIndex] : "";

        $choicesCount = count($choices);
        do
        {
            for ($i=1; $i<=$choicesCount; $i++)
            {
                $choice = $choices[$i];
                echo $prompt . "\n";
                echo "$i - " . ((string) $choice) . "\n";
                echo "\n";

                $promptLine = $hasDefault ? "[$defaultChoiceIndex ($defaultChoiceString)] > ": ' > ';
                $userChoice = readline($promptLine);

                if (($userChoice === "") && $hasDefault)
                    return [$defaultChoiceIndex, $choices[$defaultChoiceIndex]];

                $userChoice = (int) $userChoice;

            }
        } while (!(0 < $userChoice && $userChoice < ($choicesCount-1)));

        return [$userChoice, $choices[$userChoice]];
    }

    public static function chooseApplication(): string
    {
        $appsToLoad = Applications::resolve();
        $paths = $appsToLoad->paths;

        if (count($paths) === 1)
        {
            return $paths[0];
        }
        else if (count($paths))
        {
            list($index, $_) = self::promptList(
                "Please choose an application to proceed",
                Bunch::of($appsToLoad->paths)->map(fn($x) => Path::toRelative($x))->get()
            );
        }

        do
        {
            echo "No application to load found in your configuration\n";
            $path = readline("Please enter a path to proceed : ");
        }
        while (!is_dir($path));

        return $path;
    }
}