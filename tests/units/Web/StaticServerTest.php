<?php

namespace Cube\Tests\Units\Web;

use Cube\Env\Storage;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Web\Helpers\StaticServer;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * The served directory is a child of the temporary storage, so the test owns a directory
 * *outside* the one being served and can check what crossing that boundary does.
 *
 * @internal
 */
class StaticServerTest extends TestCase
{
    use HasTemporaryStorage;

    protected Storage $public;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('static-server-test-');

        $this->public = $this->storage->child('public');
        $this->public->write('index.html', '<h1>home</h1>');
        $this->public->write('style.css', 'body { color: red; }');
        $this->public->child('js')->write('app.js', 'console.log("hello")');

        $this->storage->write('secret.txt', 'the secret');
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    public function testAFileOfTheDirectoryIsServed()
    {
        $this->assertEquals('body { color: red; }', $this->served('/style.css')->getBody());
    }

    public function testANestedFileIsServed()
    {
        $this->assertEquals('console.log("hello")', $this->served('/js/app.js')->getBody());
    }

    public function testTheRootServesTheIndexFile()
    {
        $this->assertEquals('<h1>home</h1>', $this->served('/')->getBody());
    }

    public function testAnUnknownPathIsLeftToTheRouter()
    {
        $this->assertNull($this->served('/unknown.css'));
    }

    public function testATraversingPathIsRefused()
    {
        $this->assertNull($this->served('/../secret.txt'));
        $this->assertNull($this->served('/js/../../secret.txt'));
    }

    /**
     * A symlink leaving the served directory holds no `..` to look for : checking the path as a
     * string served whatever it pointed at, anywhere on the filesystem.
     */
    public function testASymlinkLeavingTheDirectoryIsRefused()
    {
        $this->symlinkOrSkip($this->storage->path('secret.txt'), $this->public->path('escape.txt'));

        $this->assertNull($this->served('/escape.txt'));
    }

    public function testASymlinkStayingInsideTheDirectoryIsServed()
    {
        $this->symlinkOrSkip($this->public->path('style.css'), $this->public->path('alias.css'));

        $this->assertEquals('body { color: red; }', $this->served('/alias.css')->getBody());
    }

    /**
     * `..` inside a file name is not a traversal, and refusing on that substring alone made
     * those files unreachable.
     */
    public function testAFileNameHoldingTwoDotsIsServed()
    {
        $this->public->write('backup..2024.txt', 'last backup');

        $this->assertEquals('last backup', $this->served('/backup..2024.txt')->getBody());
    }

    public function testASiblingDirectorySharingThePrefixIsRefused()
    {
        $sibling = $this->storage->child('public-internal');
        $sibling->write('leak.txt', 'sibling');

        $this->assertNull($this->served('/../public-internal/leak.txt'));
    }

    public function testTheCheckCanBeTurnedOff()
    {
        // Documented as "only if you know why" : without it a symlink can lead out of the directory
        $this->symlinkOrSkip($this->storage->path('secret.txt'), $this->public->path('linked-secret.txt'));
        $server = new StaticServer($this->public, secure: false);

        $this->assertEquals('the secret', $server->handle(new Request('GET', '/linked-secret.txt'))?->getBody());
    }

    public function testTheFallbackRouteServesTheIndexFile()
    {
        $server = new StaticServer($this->public);
        $router = new Router(new RouterConfiguration(false, false, false, [], [], '/'));
        $server->registerFallbackRoute($router);

        $response = $router->route(new Request('GET', '/some/client/side/route'));

        $this->assertEquals(StatusCode::OK, $response->getStatusCode());
        $this->assertEquals('<h1>home</h1>', $response->getBody());
    }

    public function testADirectoryWithoutAnIndexRegistersNoFallback()
    {
        $server = new StaticServer($this->public->child('js'));
        $router = new Router(new RouterConfiguration(false, false, false, [], [], '/'));
        $server->registerFallbackRoute($router);

        $this->assertEquals([], $router->getRoutes());
    }

    /** An index.php was answered through readfile(), handing out its PHP source. */
    public function testAPhpIndexFileIsNotServedAsSource()
    {
        $site = $this->storage->child('php-site');
        $site->write('index.php', '<?php echo "rendered";');

        $response = (new StaticServer($site))->handle(new Request('GET', '/'));

        $this->assertStringNotContainsString('<?php', $response?->getBody() ?? '');
    }

    public function testAPhpFileIsNotServed()
    {
        $this->public->write('config.php', '<?php return ["password" => "x"];');

        $this->assertNull($this->served('/config.php'));
    }

    public function testADotFileIsNotServed()
    {
        $this->public->write('.env', 'DB_PASSWORD=x');
        $this->public->child('.git')->write('config', '[core]');

        $this->assertNull($this->served('/.env'));
        $this->assertNull($this->served('/.git/config'));
    }

    public function testTheWellKnownDirectoryIsServed()
    {
        $this->public->child('.well-known')->write('security.txt', 'Contact: security@example.com');

        $this->assertEquals('Contact: security@example.com', $this->served('/.well-known/security.txt')?->getBody());
    }

    protected function served(string $path): ?Response
    {
        return (new StaticServer($this->public))->handle(new Request('GET', $path));
    }

    protected function symlinkOrSkip(string $target, string $link): void
    {
        if (!@symlink($target, $link)) {
            $this->markTestSkipped('This filesystem does not allow symlinks');
        }
    }
}
