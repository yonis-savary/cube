<?php

namespace Cube;

use Cube\Env\Logger\Logger;

function debug(mixed ...$values): void
{
    foreach ($values as $value) {
        Logger::getInstance()->debug('{value}', ['value' => $value]);
    }
}

function emergency(mixed ...$values): void
{
    foreach ($values as $value) {
        Logger::getInstance()->emergency('{value}', ['value' => $value]);
    }
}

function alert(mixed ...$values): void
{
    foreach ($values as $value) {
        Logger::getInstance()->alert('{value}', ['value' => $value]);
    }
}

function critical(mixed ...$values): void
{
    foreach ($values as $value) {
        Logger::getInstance()->critical('{value}', ['value' => $value]);
    }
}

function error(mixed ...$values): void
{
    foreach ($values as $value) {
        Logger::getInstance()->error('{value}', ['value' => $value]);
    }
}

function warning(mixed ...$values): void
{
    foreach ($values as $value) {
        Logger::getInstance()->warning('{value}', ['value' => $value]);
    }
}

function notice(mixed ...$values): void
{
    foreach ($values as $value) {
        Logger::getInstance()->notice('{value}', ['value' => $value]);
    }
}

function info(mixed ...$values): void
{
    foreach ($values as $value) {
        Logger::getInstance()->info('{value}', ['value' => $value]);
    }
}
