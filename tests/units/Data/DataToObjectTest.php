<?php

namespace Cube\Tests\Units\Data;

use Cube\Data\Bunch;
use Cube\Data\DataToObject;
use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Utils\Text;
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
            Bunch::wrap($collection->albums)->key('name')->toArray()
        );

        $this->assertEquals(
            ['Käärijä', 'Don Toliver', 'Royal Crown Revue'],
            Bunch::wrap($collection->albums)->key('artist.name')->toArray()
        );
    }

    /** A failed construction logged every value it was given : a password sent with a wrong shape ended in the logs. */
    public function testAFailedConstructionLogsTheShapeOfTheDataOnly()
    {
        $logger = new class extends NullLogger {
            public string $written = '';

            public function log($level, null|string|\Stringable $message, array $context = []): void
            {
                $this->written .= Text::interpolate($message, $context)."\n";
            }
        };

        Logger::withInstance($logger, function () {
            try {
                Artist::fromData(['name' => ['a-secret-value']]);
                $this->fail('An array cannot be an artist name');
            } catch (\TypeError) {
            }
        });

        $this->assertStringContainsString('name', $logger->written);
        $this->assertStringContainsString('array', $logger->written);
        $this->assertStringNotContainsString('a-secret-value', $logger->written);
    }

    /** A missing key always passed null, even to a parameter declaring a default value. */
    public function testAMissingKeyTakesTheParameterDefaultValue()
    {
        $class = new class('', 0, null) extends DataToObject {
            public function __construct(
                public string $name,
                public int $quantity = 1,
                public ?string $note = 'none',
                public ?string $comment = null,
            ) {}
        };

        $line = $class::fromData(['name' => 'screen', 'comment' => 'fragile']);

        $this->assertEquals(1, $line->quantity);
        $this->assertEquals('none', $line->note);
        $this->assertEquals('fragile', $line->comment);
    }
}
