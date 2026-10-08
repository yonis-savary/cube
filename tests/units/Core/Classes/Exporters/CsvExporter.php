<?php

namespace Cube\Tests\Units\Core\Classes\Exporters;

class CsvExporter implements InvoiceExporter
{
    public static int $built = 0;

    public function __construct(
        public string $separator = ','
    ) {
        ++self::$built;
    }

    public static function supports(string $format): bool
    {
        return in_array($format, ['csv', 'spreadsheet']);
    }
}
