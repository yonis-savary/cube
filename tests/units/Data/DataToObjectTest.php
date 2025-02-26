<?php

namespace Cube\Tests\Units\Data;

use Cube\Data\Bunch;
use Cube\Tests\Units\Data\Sample\Album;
use Cube\Tests\Units\Data\Sample\AlbumCollection;
use Cube\Tests\Units\Data\Sample\Artist;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DataToObjectTest extends TestCase
{
    public function testFromData()
    {
        $artist = Artist::fromData(['name' => 'Rick James']);
        $this->assertEquals('Rick James', $artist->name);

        $album = Album::fromData(['name' => "People's Champion", 'artist' => ['name' => 'Käärijä']]);
        $this->assertEquals("People's Champion", $album->name);
        $this->assertEquals('Käärijä', $album->artist->name);

        $collection = AlbumCollection::fromData([
            'albums' => [
                ['name' => "People's Champion", 'artist' => ['name' => 'Käärijä']],
                ['name' => 'Life of a DON', 'artist' => ['name' => 'Don Toliver']],
                ['name' => "Mugzy's move", 'artist' => ['name' => 'Royal Crown Revue']],
            ],
        ]);

        $this->assertEquals(
            ["People's Champion", 'Life of a DON', "Mugzy's move"],
            Bunch::of($collection->albums)->key('name')->toArray()
        );

        $this->assertEquals(
            ['Käärijä', 'Don Toliver', 'Royal Crown Revue'],
            Bunch::of($collection->albums)->key('artist.name')->toArray()
        );
    }
}
