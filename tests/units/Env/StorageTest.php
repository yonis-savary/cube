<?php

namespace Cube\Tests\Units\Env;

use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Utils\Path;
use PHPUnit\Framework\TestCase;

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

    public function test_the_root_directory_is_created_on_construction()
    {
        $this->assertTrue($this->storage->isDirectory('/'));
        $this->assertTrue($this->storage->isWritable());
        $this->assertTrue($this->storage->isReadable());
    }

    public function test_path_resolves_under_the_root()
    {
        $this->assertEquals(
            Path::join($this->storage->getRoot(), 'invoices/2026.csv'),
            $this->storage->path('invoices/2026.csv')
        );
    }

    public function test_path_leaves_an_already_absolute_path_alone()
    {
        $absolute = $this->storage->path('invoices.csv');

        $this->assertEquals($absolute, $this->storage->path($absolute));
    }

    public function test_to_string_gives_the_root()
    {
        $this->assertEquals($this->storage->getRoot(), (string) $this->storage);
    }

    public function test_write_then_read()
    {
        $this->assertTrue($this->storage->write('invoices.csv', 'first line'));
        $this->assertEquals('first line', $this->storage->read('invoices.csv'));
    }

    public function test_write_succeeds_when_it_shortens_an_existing_file()
    {
        $this->storage->write('invoices.csv', 'a long first content');

        $this->assertTrue($this->storage->write('invoices.csv', 'short'));
        $this->assertEquals('short', $this->storage->read('invoices.csv'));
    }

    public function test_write_appends_when_asked_to()
    {
        $this->storage->write('invoices.csv', 'first');
        $this->storage->write('invoices.csv', '-second', FILE_APPEND);

        $this->assertEquals('first-second', $this->storage->read('invoices.csv'));
    }

    public function test_write_reports_a_failure_instead_of_throwing()
    {
        $this->storage->makeDirectory('reports');

        $this->assertFalse($this->storage->write('reports', 'a directory cannot be written over'));
    }

    public function test_exists_distinguishes_files_from_directories()
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

    public function test_make_directory_creates_the_missing_parents()
    {
        $this->assertTrue($this->storage->makeDirectory('reports/2026/january'));
        $this->assertTrue($this->storage->isDirectory('reports/2026'));
    }

    public function test_unlink_removes_the_file()
    {
        $this->storage->write('invoices.csv', 'content');

        $this->assertTrue($this->storage->unlink('invoices.csv'));
        $this->assertFalse($this->storage->exists('invoices.csv'));
    }

    public function test_files_and_directories_are_listed_apart_as_absolute_paths()
    {
        $this->storage->write('invoices.csv', 'content');
        $this->storage->makeDirectory('reports');

        $this->assertEquals([$this->storage->path('invoices.csv')], $this->storage->files());
        $this->assertEquals([$this->storage->path('reports')], $this->storage->directories());
    }

    public function test_explore_walks_the_whole_tree()
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

    public function test_child_scopes_a_new_storage_to_a_subdirectory()
    {
        $child = $this->storage->child('reports');
        $child->write('january.csv', 'content');

        $this->assertEquals($this->storage->path('reports'), $child->getRoot());
        $this->assertTrue($this->storage->isFile('reports/january.csv'));
    }

    public function test_parent_goes_back_up_one_level()
    {
        $child = $this->storage->child('reports');

        $this->assertEquals($this->storage->getRoot(), $child->parent()->getRoot());
    }

    /** path() joins the given path without resolving "..", so it can leave the root. */
    public function test_path_does_not_escape_the_root()
    {
        $this->assertStringStartsWith(
            $this->storage->getRoot(),
            realpath(dirname($this->storage->path('../../outside.csv')))
        );
    }
}
