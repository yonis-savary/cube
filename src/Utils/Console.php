<?php

namespace YonisSavary\Cube\Utils;

use YonisSavary\Cube\Logger\Logger;

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

    public static function saveCursor(): void
    {
        echo "7";
    }

    public static function restoreCursor(): void
    {
        echo "8";
    }

    public static function reset(): void
    {
        echo "[0m";
    }

    public static function log(mixed ...$elements): void
    {
        if (str_contains(php_sapi_name(), "cli"))
        {
            foreach ($elements as $element)
                echo $element . "\n";
        }
        else
        {
            foreach ($elements as $element)
                Logger::getInstance()->info($element);
        }

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
            $callback($callback);

            if ($output = ob_get_clean())
            {
                echo $output . "\n";
                self::saveCursor();
            }

            self::restoreCursor();

            $progress = floor(($barSize * $i) / $elementsCount);
            $remain = $barSize - $progress;
            echo "[" . str_repeat("=", $progress) . str_repeat(" ", $remain) . "] $i / $elementsCount";
        }

        echo "\n";
    }


    public static function table(array $data, array $columns=[]): void
    {
        if (!count($data))
        {
            if ($columns)
            {
                $columnLine = join("  ", $columns);
                Console::log($columnLine, str_repeat("-", strlen($columnLine)));
                return;
            }
            Console::log("No item to display");
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

        $printValueLine = function(array $data) use (&$columnsSizes) {
            $line = "";
            for ($i=0; $i<count($data); $i++)
            {
                $bit = $data[$i];
                $bitLength = strlen($bit);
                $line .= $bit . str_repeat(" ", ($columnsSizes[$i] - $bitLength) + 2);
            }
            Console::log($line);
            return strlen($line);
        };

        $updateColumnsSizes($columns);

        $dataCount = count($data);
        for ($i=0; $i<$dataCount; $i++)
            $updateColumnsSizes($data[$i]);


        $length = $printValueLine($columns);
        Console::log(str_repeat("-", $length));

        foreach ($data as $row)
            $printValueLine($row);
    }
}