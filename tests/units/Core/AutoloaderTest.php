<?php

namespace Cube\Tests\Units\Core;

use Composer\Autoload\ClassLoader;
use Cube\Core\Autoloader;
use Cube\Core\Component;
use Cube\Env\Storage;
use Cube\Tests\Integration\Application\ApplicationTest;
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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
class AutoloaderTest extends TestCase
{
    public function testClassLoaderIsTheComposerOne()
    {
        $this->assertInstanceOf(ClassLoader::class, Autoloader::getClassLoader());
    }

    public function testClassesListHoldsFrameworkAndApplicationClasses()
    {
        $classes = Autoloader::classesList();

        $this->assertContains(Autoloader::class, $classes);
        $this->assertContains(Bird::class, $classes);
    }

    public function testClassesListLeavesOutVendorClasses()
    {
        $this->assertNotContains(TestCase::class, Autoloader::classesList());
    }

    public function testClassesListHoldsEnums()
    {
        $this->assertContains(Habitat::class, Autoloader::classesList());
    }

    public function testClassesThatExtendsFindsEveryChild()
    {
        $children = Autoloader::classesThatExtends(Common::class);

        $this->assertContains(Bird::class, $children);
        $this->assertContains(Dragon::class, $children);
        $this->assertContains(Zombie::class, $children);
        $this->assertNotContains(Common::class, $children);
    }

    public function testClassesThatExtendsRejectsAbstractsUnlessAsked()
    {
        $this->assertNotContains(Controller::class, Autoloader::classesThatExtends(WebAPI::class));
        $this->assertContains(Controller::class, Autoloader::classesThatExtends(WebAPI::class, false));
    }

    public function testClassesThatImplementsFindsEveryImplementation()
    {
        $flying = Autoloader::classesThatImplements(CanFly::class);

        $this->assertContains(Bird::class, $flying);
        $this->assertContains(Dragon::class, $flying);
        $this->assertNotContains(Zombie::class, $flying);
    }

    public function testClassesThatUsesFindsEveryUserOfATrait()
    {
        $this->assertContains(Counter::class, Autoloader::classesThatUses(Component::class));
    }

    public function testExtendsPredicate()
    {
        $this->assertTrue(Autoloader::extends(Bird::class, Common::class));
        $this->assertFalse(Autoloader::extends(Bird::class, Dragon::class));

        // a class is considered to extend itself, unless told otherwise
        $this->assertTrue(Autoloader::extends(Common::class, Common::class));
        $this->assertFalse(Autoloader::extends(Common::class, Common::class, false));

        $this->assertFalse(Autoloader::extends('Cube\Tests\NotAClass', Common::class));
    }

    public function testExtendsPredicateAcceptsAnInstance()
    {
        $this->assertTrue(Autoloader::extends(new Bird(), Common::class));
        $this->assertTrue(Autoloader::extends(new Bird(), Bird::class));
        $this->assertFalse(Autoloader::extends(new Bird(), Bird::class, false));
    }

    public function testImplementsPredicate()
    {
        $this->assertTrue(Autoloader::implements(Bird::class, CanFly::class));
        $this->assertFalse(Autoloader::implements(Zombie::class, CanFly::class));
        $this->assertFalse(Autoloader::implements('Cube\Tests\NotAClass', CanFly::class));
    }

    public function testUsesPredicate()
    {
        $this->assertTrue(Autoloader::uses(Counter::class, Component::class));
        $this->assertFalse(Autoloader::uses(Bird::class, Component::class));
    }

    /** class_uses() only reports the traits a class declares itself, not the ones of its parents. */
    public function testUsesPredicateSeesATraitInheritedFromAParent()
    {
        $this->assertTrue(Autoloader::uses(SpecializedCounter::class, Component::class));
    }

    public function testClassExistsAnswersForBothIndexedAndUnknownClasses()
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
    public function testDiscoveredFileListsHoldNoDuplicate()
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

    public function testClassesOutsideTheExploredDirectoriesAreLeftOutUntilAdded()
    {
        $this->assertNotContains(ApplicationTest::class, Autoloader::classesThatExtends(TestCase::class));

        Autoloader::addToExploreMap('tests/integration');

        $this->assertContains(ApplicationTest::class, Autoloader::classesThatExtends(TestCase::class));
    }

    public function testOnlyADirectoryCanBeExplored()
    {
        $this->expectException(\InvalidArgumentException::class);

        Autoloader::addToExploreMap('tests/bootstrap.php');
    }

    public function testClassesFoundThroughTheClassmapAreListedByName()
    {
        $directory = Storage::getInstance()->child(uniqid('autoloader-classmap-test-'));
        $directory->write('Invoice.php', "<?php\n\nnamespace Acme\\Billing;\n\nclass Invoice {}\n");

        try {
            Autoloader::getClassLoader()->addClassMap(['Acme\Billing\Invoice' => $directory->path('Invoice.php')]);
            Autoloader::addToExploreMap($directory->getRoot());

            $this->assertContains('Acme\Billing\Invoice', Autoloader::classesList());
            $this->assertNotContains($directory->path('Invoice.php'), Autoloader::classesList());

            // The classmap entry outlives the file : loading the class now keeps later discoveries from including it
            class_exists('Acme\Billing\Invoice');
        } finally {
            unlink($directory->path('Invoice.php'));
            rmdir($directory->getRoot());
        }
    }

    /** A CLI usually loads the apcu extension with apc.enable_cli=0, where every APCu call throws. */
    public function testADisabledApcuExtensionIsIgnored()
    {
        if (!extension_loaded('apcu'))
            $this->markTestSkipped('Needs the apcu extension loaded');

        $process = new Process([
            PHP_BINARY, '-d', 'apc.enable_cli=0', '-r',
            'require "vendor/autoload.php"; Cube\Core\Autoloader::clearApcuCache(); echo "cleared";',
        ], timeout: 30);
        $process->run();

        $this->assertEquals('cleared', $process->getOutput(), $process->getErrorOutput());
    }

    /** PHP's own default is to print warnings, absolute paths included, into the response. */
    public function testPhpErrorsAreOnlyDisplayedInADebugEnvironment()
    {
        $displayErrorsIn = function (string $environment): string {
            $process = new Process([
                PHP_BINARY, '-d', 'display_errors=1', '-r',
                'require "vendor/autoload.php"; Cube\Core\Autoloader::initialize(getcwd()); echo ini_get("display_errors");',
            ], env: ['env' => $environment], timeout: 30);
            $process->run();

            return $process->getOutput();
        };

        $this->assertEquals('0', $displayErrorsIn('staging'));
        $this->assertEquals('1', $displayErrorsIn('dev'));
    }

    /** Every file of Requires/ or Routes/ was included : a README or an editor backup was printed into every response. */
    public function testOnlyPhpFilesAreIncluded()
    {
        $project = Storage::getInstance()->child(uniqid('autoloader-include-test-'));
        $requires = $project->child('App')->child('Requires');
        $project->write('cube.php', '<?php return [new Cube\Core\Autoloader\Applications("App")];');
        $requires->write('helper.php', '<?php');
        $requires->write('notes.md', 'private notes');

        try {
            $process = new Process([
                PHP_BINARY, '-r',
                'require "vendor/autoload.php"; Cube\Core\Autoloader::initialize($argv[1]); echo json_encode(array_map("basename", Cube\Core\Autoloader::getRequireFiles()));',
                $project->getRoot(),
            ], timeout: 30);
            $process->run();

            $this->assertEquals('["helper.php"]', $process->getOutput(), $process->getErrorOutput());
        } finally {
            Process::fromShellCommandline('rm -rf '.escapeshellarg($project->getRoot()))->run();
        }
    }
}
