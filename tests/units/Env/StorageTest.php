<?php

namespace Cube\Tests\Units\Env;

use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Utils\Path;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class StorageTest extends TestCase
{
    use HasTemporaryStorage;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('storage-test-');
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    public function testTheRootDirectoryIsCreatedOnConstruction()
    {
        $this->assertTrue($this->storage->isDirectory('/'));
        $this->assertTrue($this->storage->isWritable());
        $this->assertTrue($this->storage->isReadable());
    }

    public function testPathResolvesUnderTheRoot()
    {
        $this->assertEquals(
            Path::join($this->storage->getRoot(), 'invoices/2026.csv'),
            $this->storage->path('invoices/2026.csv')
        );
    }

    public function testPathLeavesAnAlreadyAbsolutePathAlone()
    {
        $absolute = $this->storage->path('invoices.csv');

        $this->assertEquals($absolute, $this->storage->path($absolute));
    }

    public function testToStringGivesTheRoot()
    {
        $this->assertEquals($this->storage->getRoot(), (string) $this->storage);
    }

    public function testWriteThenRead()
    {
        $this->assertTrue($this->storage->write('invoices.csv', 'first line'));
        $this->assertEquals('first line', $this->storage->read('invoices.csv'));
    }

    public function testWriteSucceedsWhenItShortensAnExistingFile()
    {
        $this->storage->write('invoices.csv', 'a long first content');

        $this->assertTrue($this->storage->write('invoices.csv', 'short'));
        $this->assertEquals('short', $this->storage->read('invoices.csv'));
    }

    public function testWriteAppendsWhenAskedTo()
    {
        $this->storage->write('invoices.csv', 'first');
        $this->storage->write('invoices.csv', '-second', FILE_APPEND);

        $this->assertEquals('first-second', $this->storage->read('invoices.csv'));
    }

    public function testWriteReportsAFailureInsteadOfThrowing()
    {
        $this->storage->makeDirectory('reports');

        $this->assertFalse($this->storage->write('reports', 'a directory cannot be written over'));
    }

    public function testExistsDistinguishesFilesFromDirectories()
    {
        $this->storage->write('invoices.csv', 'content');
        $this->storage->makeDirectory('reports');

        $this->assertTrue($this->storage->exists('invoices.csv'));
        $this->assertTrue($this->storage->isFile('invoices.csv'));
        $this->assertFalse($this->storage->isDirectory('invoices.csv'));

        $this->assertTrue($this->storage->exists('reports'));
        $this->assertTrue($this->storage->isDirectory('reports'));
        $this->assertFalse($this->storage->isFile('reports'));

        $this->assertFalse($this->storage->exists('nothing-here'));
    }

    public function testMakeDirectoryCreatesTheMissingParents()
    {
        $this->assertTrue($this->storage->makeDirectory('reports/2026/january'));
        $this->assertTrue($this->storage->isDirectory('reports/2026'));
    }

    public function testUnlinkRemovesTheFile()
    {
        $this->storage->write('invoices.csv', 'content');

        $this->assertTrue($this->storage->unlink('invoices.csv'));
        $this->assertFalse($this->storage->exists('invoices.csv'));
    }

    public function testFilesAndDirectoriesAreListedApartAsAbsolutePaths()
    {
        $this->storage->write('invoices.csv', 'content');
        $this->storage->makeDirectory('reports');

        $this->assertEquals([$this->storage->path('invoices.csv')], $this->storage->files());
        $this->assertEquals([$this->storage->path('reports')], $this->storage->directories());
    }

    public function testExploreWalksTheWholeTree()
    {
        $this->storage->write('invoices.csv', 'content');
        $this->storage->makeDirectory('reports/2026');
        $this->storage->write('reports/2026/january.csv', 'content');

        $expectedFiles = [
            $this->storage->path('invoices.csv'),
            $this->storage->path('reports/2026/january.csv'),
        ];
        $expectedDirectories = [
            $this->storage->path('reports'),
            $this->storage->path('reports/2026'),
        ];

        $this->assertEqualsCanonicalizing($expectedFiles, $this->storage->exploreFiles());
        $this->assertEqualsCanonicalizing($expectedDirectories, $this->storage->exploreDirectories());
        $this->assertEqualsCanonicalizing(
            array_merge($expectedFiles, $expectedDirectories),
            $this->storage->explore()
        );
    }

    public function testChildScopesANewStorageToASubdirectory()
    {
        $child = $this->storage->child('reports');
        $child->write('january.csv', 'content');

        $this->assertEquals($this->storage->path('reports'), $child->getRoot());
        $this->assertTrue($this->storage->isFile('reports/january.csv'));
    }

    public function testParentGoesBackUpOneLevel()
    {
        $child = $this->storage->child('reports');

        $this->assertEquals($this->storage->getRoot(), $child->parent()->getRoot());
    }

    /** path() joins the given path without resolving "..", so it can leave the root. */
    public function testPathDoesNotEscapeTheRoot()
    {
        $this->assertStringStartsWith(
            $this->storage->getRoot(),
            realpath(dirname($this->storage->path('../../outside.csv')))
        );
    }
}
