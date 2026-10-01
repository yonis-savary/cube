<?php

namespace Cube\Utils;

use Cube\Core\Autoloader\Applications;
use Cube\Data\Bunch;
use Cube\Env\Logger\Logger;

/**
 * @todo Implement no-ansi mode
 */
class Console
{
    public const BLACK = 0;
    public const RED = 1;
    public const GREEN = 2;
    public const YELLOW = 3;
    public const BLUE = 4;
    public const MAGENTA = 5;
    public const CYAN = 6;
    public const WHITE = 7;
    public const DEFAULT = 9;

    protected static ?Logger $logger = null;

    public static function getLogger(): Logger
    {
        if (!self::$logger) {
            self::$logger = Logger::forFile('console.csv');
        }

        return self::$logger;
    }

    public static function saveCursor(): void
    {
        echo '7';
    }

    public static function restoreCursor(): void
    {
        echo '8';
    }

    public static function eraseFromCursor(): void
    {
        echo '[0J';
    }

    public static function reset(): void
    {
        echo '[0m';
    }

    public static function print(string|\Stringable ...$elements): void
    {
        if (php_sapi_name() !== 'cli') {
            return;
        }

        foreach ($elements as $element) {
            echo $element."\n";
        }
    }

    public static function log(string|\Stringable ...$elements): void
    {
        self::print(...$elements);

        $logger = self::getLogger();

        Bunch::of($elements)
            ->filter(fn ($x) => '' !== $x && null !== $x)
        // ->map(fn($x) => preg_replace("/[^ ]+/", "", (string) $x))
            ->map(fn ($x) => preg_replace('/(?:[@-Z\-_]|\[[0-?]*[ -\/]*[@-~])/', '', $x))
            ->forEach(fn ($x) => $logger->info($x))
        ;
    }

    public static function withColor(string $string, int $colorCode, bool $bright = false)
    {
        $colorCode += 30;
        $brightStr = $bright ? ';1' : '';

        return "[{$colorCode}".$brightStr.'m'.$string.'[0m';
    }

    public static function withBackground(string $string, int $colorCode, bool $bright = false)
    {
        $colorCode += 40;
        $brightStr = $bright ? ';1' : '';

        return "[{$colorCode}".$brightStr.'m'.$string.'[0m';
    }

    public static function withBlackColor(string $string, bool $bright = false)
    {
        return self::withColor($string, self::BLACK, $bright);
    }

    public static function withRedColor(string $string, bool $bright = false)
    {
        return self::withColor($string, self::RED, $bright);
    }

    public static function withGreenColor(string $string, bool $bright = false)
    {
        return self::withColor($string, self::GREEN, $bright);
    }

    public static function withYellowColor(string $string, bool $bright = false)
    {
        return self::withColor($string, self::YELLOW, $bright);
    }

    public static function withBlueColor(string $string, bool $bright = false)
    {
        return self::withColor($string, self::BLUE, $bright);
    }

    public static function withMagentaColor(string $string, bool $bright = false)
    {
        return self::withColor($string, self::MAGENTA, $bright);
    }

    public static function withCyanColor(string $string, bool $bright = false)
    {
        return self::withColor($string, self::CYAN, $bright);
    }

    public static function withWhiteColor(string $string, bool $bright = false)
    {
        return self::withColor($string, self::WHITE, $bright);
    }

    public static function withDefaultColor(string $string, bool $bright = false)
    {
        return self::withColor($string, self::DEFAULT, $bright);
    }

    public static function withBlackBackground(string $string, bool $bright = false)
    {
        return self::withBackground($string, self::BLACK, $bright);
    }

    public static function withRedBackground(string $string, bool $bright = false)
    {
        return self::withBackground($string, self::RED, $bright);
    }

    public static function withGreenBackground(string $string, bool $bright = false)
    {
        return self::withBackground($string, self::GREEN, $bright);
    }

    public static function withYellowBackground(string $string, bool $bright = false)
    {
        return self::withBackground($string, self::YELLOW, $bright);
    }

    public static function withBlueBackground(string $string, bool $bright = false)
    {
        return self::withBackground($string, self::BLUE, $bright);
    }

    public static function withMagentaBackground(string $string, bool $bright = false)
    {
        return self::withBackground($string, self::MAGENTA, $bright);
    }

    public static function withCyanBackground(string $string, bool $bright = false)
    {
        return self::withBackground($string, self::CYAN, $bright);
    }

    public static function withWhiteBackground(string $string, bool $bright = false)
    {
        return self::withBackground($string, self::WHITE, $bright);
    }

    public static function withDefaultBackground(string $string, bool $bright = false)
    {
        return self::withBackground($string, self::DEFAULT, $bright);
    }

    public static function withProgressBar(array $elements, callable $callback)
    {
        $elementsCount = count($elements);

        $barSize = 50;

        self::saveCursor();

        for ($i = 1; $i <= $elementsCount; ++$i) {
            ob_start();
            $element = $elements[$i - 1];
            $callback($element);

            if ($output = ob_get_clean()) {
                self::restoreCursor();
                self::eraseFromCursor();
                Console::log(...Bunch::fromExplode("\n", $output)->filter()->get());
                self::saveCursor();
            }

            self::restoreCursor();

            $progress = floor(($barSize * $i) / $elementsCount);
            $remain = $barSize - $progress;
            echo '['.str_repeat('=', max(0, $progress - 1)).'>'.str_repeat(' ', $remain)."] {$i} / {$elementsCount}";
        }

        echo "\n";
    }

    public static function table(array $data, array $columns = [], bool $logToo = true): void
    {
        $print = $logToo
            ? fn ($x) => Console::log($x)
            : fn ($x) => print $x."\n";

        if (!count($data)) {
            if ($columns) {
                $columnLine = join('  ', $columns);
                $print($columnLine);
                $print(str_repeat('-', strlen($columnLine)));

                return;
            }
            $print('No item to display');

            return;
        }

        $data = array_values($data);
        if (!$columns) {
            $columns = Utils::isAssoc($data[0]) ? array_keys($data[0]) : array_shift($data);
        }
        $data = array_map(fn (array $row) => array_values($row), $data);

        $columnsSizes = [];
        $updateColumnsSizes = function (array $data) use (&$columnsSizes) {
            for ($i = 0; $i < count($data); ++$i) {
                $columnsSizes[$i] = max($columnsSizes[$i] ?? 0, strlen((string) $data[$i]));
            }
        };

        $printValueLine = function (array $data) use (&$columnsSizes, $print) {
            $line = '';
            for ($i = 0; $i < count($data); ++$i) {
                $bit = (string) $data[$i];
                $bitLength = strlen($bit);
                $line .= $bit.str_repeat(' ', ($columnsSizes[$i] - $bitLength) + 2);
            }
            $print($line);

            return strlen($line);
        };

        $updateColumnsSizes($columns);

        $dataCount = count($data);
        for ($i = 0; $i < $dataCount; ++$i) {
            $updateColumnsSizes($data[$i]);
        }

        $length = $printValueLine($columns);
        $print(str_repeat('-', $length));

        foreach ($data as $row) {
            $printValueLine($row);
        }
    }

    /**
     * @return array{int,mixed} The index of the chosen element in `$choices`, and the element itself
     */
    public static function promptList(string $prompt, array $choices, ?int $defaultChoiceIndex = null): array
    {
        $choices = array_values($choices);
        $hasDefault = null !== $defaultChoiceIndex;
        $promptLine = $hasDefault
            ? '['.($defaultChoiceIndex + 1).' ('.((string) $choices[$defaultChoiceIndex]).')] > '
            : ' > ';

        while (true) {
            echo $prompt."\n";
            foreach ($choices as $index => $choice) {
                echo ($index + 1).' - '.((string) $choice)."\n";
            }
            echo "\n";

            $userChoice = readline($promptLine);
            if (('' === $userChoice) && $hasDefault) {
                return [$defaultChoiceIndex, $choices[$defaultChoiceIndex]];
            }

            $index = (int) $userChoice - 1;
            if (array_key_exists($index, $choices)) {
                return [$index, $choices[$index]];
            }
        }
    }

    public static function chooseApplication(): string
    {
        $appsToLoad = Applications::resolve();
        $paths = $appsToLoad->paths;

        if (1 === count($paths)) {
            return $paths[0];
        }
        if (count($paths)) {
            list($index, $_) = self::promptList(
                'Please choose an application to proceed',
                Bunch::of($appsToLoad->paths)->map(fn ($x) => Path::toRelative($x))->get()
            );

            return $paths[$index];
        }

        do {
            echo "No application to load found in your configuration\n";
            $path = readline('Please enter a path to proceed : ');
        } while (!is_dir($path));

        return $path;
    }
}
