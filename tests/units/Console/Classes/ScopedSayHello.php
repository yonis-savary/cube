<?php

namespace Cube\Tests\Units\Console\Classes;

class ScopedSayHello extends SayHello
{
    public function getScope(): string
    {
        return 'greeting';
    }

    public function getHelp(): string
    {
        return 'Greet whoever is reading';
    }
}
