<?php

namespace Cube\Data;

use Cube\Env\Configuration\ConfigurationElement;

class DateTimeConfiguration extends ConfigurationElement
{
    public const RFC3339_FORMATS = [DATE_ATOM, DATE_RFC3339_EXTENDED];

    /**
     * @param string $outputFormat How a date with a time is written in a model's `toArray()` and a JSON response, as `DateTimeInterface::format()` reads it
     */
    public function __construct(
        public readonly string $outputFormat = DATE_ATOM
    ) {}

    public function isRfc3339(): bool
    {
        return in_array($this->outputFormat, self::RFC3339_FORMATS, true);
    }
}
