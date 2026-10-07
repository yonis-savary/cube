<?php

namespace Cube\Tests\Units\Core;

use Composer\Autoload\ClassLoader;
use Cube\Core\Autoloader;
use Cube\Core\Component;
use Cube\Env\Storage;
use Cube\Tests\Integration\IntegrationApplicationTest;
use Cube\Tests\Units\Core\Classes\Bird;
use Cube\Tests\Units\Core\Classes\Common;
use Cube\Tests\Units\Core\Classes\Counter;
use Cube\Tests\Units\Core\Classes\Dragon;
use Cube\Tests\Units\Core\Classes\Habitat;
use Cube\Tests\Units\Core\Classes\SpecializedCounter;
use Cube\Tests\Units\Core\Classes\Zombie;
use Cube\Tests\Units\Core\Contracts\CanFly;
use Cube\Web\Controller;
use Cube\Web\Helpers\WebAPI;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AutoloaderTest extends TestCase
{
    public function test_class_loader_is_the_composer_one()
    {
        $this->assertInstanceOf(ClassLoader::class, Autoloader::getClassLoader());
    }

    public function test_classes_list_holds_framework_and_application_classes()
    {
        $classes = Autoloader::classesList();

        $this->assertContains(Autoloader::class, $classes);
        $this->assertContains(Bird::class, $classes);
    }

    public function test_classes_list_leaves_out_vendor_classes()
    {
        $this->assertNotContains(TestCase::class, Autoloader::classesList());
    }

    public function test_classes_list_holds_enums()
    {
        $this->assertContains(Habitat::class, Autoloader::classesList());
    }

    public function test_classes_that_extends_finds_every_child()
    {
        $children = Autoloader::classesThatExtends(Common::class);

        $this->assertContains(Bird::class, $children);
        $this->assertContains(Dragon::class, $children);
        $this->assertContains(Zombie::class, $children);
        $this->assertNotContains(Common::class, $children);
    }

    public function test_classes_that_extends_rejects_abstracts_unless_asked()
    {
        $this->assertNotContains(Controller::class, Autoloader::classesThatExtends(WebAPI::class));
        $this->assertContains(Controller::class, Autoloader::classesThatExtends(WebAPI::class, false));
    }

    public function test_classes_that_implements_finds_every_implementation()
    {
        $flying = Autoloader::classesThatImplements(CanFly::class);

        $this->assertContains(Bird::class, $flying);
        $this->assertContains(Dragon::class, $flying);
        $this->assertNotContains(Zombie::class, $flying);
    }

    public function test_classes_that_uses_finds_every_user_of_a_trait()
    {
        $this->assertContains(Counter::class, Autoloader::classesThatUses(Component::class));
    }

    public function test_extends_predicate()
    {
        $this->assertTrue(Autoloader::extends(Bird::class, Common::class));
        $this->assertFalse(Autoloader::extends(Bird::class, Dragon::class));

        // a class is considered to extend itself, unless told otherwise
        $this->assertTrue(Autoloader::extends(Common::class, Common::class));
        $this->assertFalse(Autoloader::extends(Common::class, Common::class, false));

        $this->assertFalse(Autoloader::extends('Cube\Tests\NotAClass', Common::class));
    }

    public function test_extends_predicate_accepts_an_instance()
    {
        $this->assertTrue(Autoloader::extends(new Bird(), Common::class));
        $this->assertTrue(Autoloader::extends(new Bird(), Bird::class));
        $this->assertFalse(Autoloader::extends(new Bird(), Bird::class, false));
    }

    public function test_implements_predicate()
    {
        $this->assertTrue(Autoloader::implements(Bird::class, CanFly::class));
        $this->assertFalse(Autoloader::implements(Zombie::class, CanFly::class));
        $this->assertFalse(Autoloader::implements('Cube\Tests\NotAClass', CanFly::class));
    }

    public function test_uses_predicate()
    {
        $this->assertTrue(Autoloader::uses(Counter::class, Component::class));
        $this->assertFalse(Autoloader::uses(Bird::class, Component::class));
    }

    /** class_uses() only reports the traits a class declares itself, not the ones of its parents. */
    public function test_uses_predicate_sees_a_trait_inherited_from_a_parent()
    {
        $this->assertTrue(Autoloader::uses(SpecializedCounter::class, Component::class));
    }

    public function test_class_exists_answers_for_both_indexed_and_unknown_classes()
    {
        $this->assertTrue(Autoloader::classExists(Bird::class));
        $this->assertTrue(Autoloader::classExists(Autoloader::class));
        $this->assertFalse(Autoloader::classExists('Cube\Tests\NotAClass'));
    }

    /**
     * Applications are explored once : a file discovered twice means the guard that
     * remembers explored applications stopped working, and the lists would then grow
     * at every boot when the autoloader cache is on.
     */
    public function test_discovered_file_lists_hold_no_duplicate()
    {
        $lists = [
            'routes' => Autoloader::getRoutesFiles(),
            'assets' => Autoloader::getAssetsFiles(),
            'views' => Autoloader::getViewFiles(),
            'requires' => Autoloader::getRequireFiles(),
        ];

        foreach ($lists as $label => $files) {
            $this->assertCount(
                count(array_unique($files)),
                $files,
                "The {$label} file list holds duplicated entries"
            );
        }
    }

    public function test_classes_outside_the_explored_directories_are_left_out_until_added()
    {
        $this->assertNotContains(IntegrationApplicationTest::class, Autoloader::classesThatExtends(TestCase::class));

        Autoloader::addToExploreMap('tests/integration');

        $this->assertContains(IntegrationApplicationTest::class, Autoloader::classesThatExtends(TestCase::class));
    }

    public function test_only_a_directory_can_be_explored()
    {
        $this->expectException(InvalidArgumentException::class);

        Autoloader::addToExploreMap('tests/bootstrap.php');
    }

    public function test_classes_found_through_the_classmap_are_listed_by_name()
    {
        $directory = Storage::getInstance()->child(uniqid('autoloader-classmap-test-'));
        $directory->write('Invoice.php', "<?php\n\nnamespace Acme\\Billing;\n\nclass Invoice {}\n");

        try {
            Autoloader::getClassLoader()->addClassMap(['Acme\\Billing\\Invoice' => $directory->path('Invoice.php')]);
            Autoloader::addToExploreMap($directory->getRoot());

            $this->assertContains('Acme\\Billing\\Invoice', Autoloader::classesList());
            $this->assertNotContains($directory->path('Invoice.php'), Autoloader::classesList());

            // The classmap entry outlives the file : loading the class now keeps later discoveries from including it
            class_exists('Acme\\Billing\\Invoice');
        } finally {
            unlink($directory->path('Invoice.php'));
            rmdir($directory->getRoot());
        }
    }
}
