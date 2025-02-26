<?php

namespace Cube\Tests\Units\Data\Sample;

use Cube\Data\Bunch;
use Cube\Data\Classes\BunchOf;
use Cube\Data\DataToObject;

class AlbumCollection extends DataToObject
{
    /**
     * @param Bunch<Album> $albums
     */
    public function __construct(
        #[BunchOf(Album::class)]
        public Bunch $albums
    ) {}
}
