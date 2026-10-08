<?php

namespace Cube\Tests\Units\Core\Classes\Exporters;

class XlsxExporter implements InvoiceExporter
{
    public static int $built = 0;

    public function __construct()
    {
        ++self::$built;
    }

    public static function supports(string $format): bool
    {
        return in_array($format, ['xlsx', 'spreadsheet']);
    }
}
