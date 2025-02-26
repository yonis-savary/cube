<?php

namespace Cube\Utils;

class File
{
    public const BYTES = 1;
    public const KILOBYTES = self::BYTES * 1024;
    public const MEGABYTES = self::KILOBYTES * 1024;
    public const GIGABYTES = self::MEGABYTES * 1024;

    public const SUFFIXES = [
        self::BYTES => 'b',
        self::KILOBYTES => 'kb',
        self::MEGABYTES => 'Mb',
        self::GIGABYTES => 'Gb',
    ];

    public static function getPrettySize(int $bytes): string
    {
        $sizeUnit = self::BYTES;

        foreach ([
            self::GIGABYTES,
            self::MEGABYTES,
            self::KILOBYTES,
        ] as $unit) {
            if ($bytes >= $unit) {
                $sizeUnit = $unit;

                break;
            }
        }

        $suffix = self::SUFFIXES[$sizeUnit];

        return round($bytes / $sizeUnit, 2).' '.$suffix;
    }
}
