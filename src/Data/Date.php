<?php

namespace Cube\Data;

/**
 * A calendar date, without time nor timezone : the value of a `DATE` field. It is manipulated like any
 * `DateTimeImmutable`, but answered `Y-m-d` by `toArray()` and JSON responses.
 */
class Date extends \DateTimeImmutable
{
    public const FORMAT = 'Y-m-d';
}
