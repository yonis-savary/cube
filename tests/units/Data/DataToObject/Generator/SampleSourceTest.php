<?php

namespace Cube\Tests\Units\Data\DataToObject\Generator;

use Cube\Data\DataToObject\Generator\Sources\SampleSource;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class SampleSourceTest extends TestCase
{
    use GeneratesClasses;

    /** An ISO date generated a `?\Datetime` parameter, which fromData() then filled with the raw string : TypeError. */
    public function testAnIsoDateIsGeneratedAsAString()
    {
        $sources = $this->render((new SampleSource('Invoice', ['created_at' => '2025-02-03T08:47:06.000Z']))->toClasses());

        $this->assertStringContainsString('public ?string $createdAt,', $sources['Invoice\\Invoice']);
        $this->assertStringContainsString("'createdAt' => 'created_at',", $sources['Invoice\\Invoice']);
        $this->assertStringNotContainsStringIgnoringCase('datetime', $sources['Invoice\\Invoice']);
    }

    public function testNestedObjectsGetTheirOwnSubNamespace()
    {
        $classes = (new SampleSource('Album', [
            'name' => "People's Champion",
            'artist' => ['name' => 'Käärijä'],
            'tracks' => [['title' => 'Cha Cha Cha']],
            'tags' => ['eurovision'],
            'released' => null,
        ]))->toClasses();

        $sources = $this->render($classes);

        $this->assertEqualsCanonicalizing(['Album\\Album', 'Album\\Artist\\Artist', 'Album\\Tracks\\Tracks'], array_keys($sources));
        $this->assertStringContainsString('public ?Artist $artist,', $sources['Album\\Album']);
        $this->assertStringContainsString("#[ArrayOf(Tracks::class)]\n        public ?array \$tracks,", $sources['Album\\Album']);
        $this->assertStringContainsString('@param list<string>|null $tags', $sources['Album\\Album']);
        $this->assertStringContainsString('public mixed $released,', $sources['Album\\Album']);
    }

    public function testGeneratedClassesHydrateTheSample()
    {
        $sample = ['name' => "People's Champion", 'artist' => ['name' => 'Käärijä'], 'tracks' => [['title' => 'Cha Cha Cha']]];
        $this->writeAndAutoload((new SampleSource('Album', $sample))->toClasses());

        $album = $this->generatedClass('Album\\Album')::fromData($sample);

        $this->assertEquals('Käärijä', $album->artist->name);
        $this->assertEquals('Cha Cha Cha', $album->tracks[0]->title);
    }
}
