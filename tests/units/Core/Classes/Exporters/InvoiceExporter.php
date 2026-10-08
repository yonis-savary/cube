<?php

namespace Cube\Tests\Units\Core\Classes\Exporters;

interface InvoiceExporter
{
    public static function supports(string $format): bool;
}
