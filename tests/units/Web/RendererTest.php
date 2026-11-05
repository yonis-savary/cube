<?php

namespace Cube\Tests\Units\Web;

use Cube\Env\Storage;
use Cube\Utils\Path;
use Cube\Web\Html\AssetsInserter;
use Cube\Web\Html\Renderer;
use PHPUnit\Framework\TestCase;

use function Cube\e;

/**
 * @internal
 */
class RendererTest extends TestCase
{
    protected function getRenderer(): Renderer
    {
        $viewPath = Path::join(__DIR__, '../../integration-root/App/Views');
        if (!is_dir($viewPath))
            throw new \RuntimeException('Could not find directory : '.$viewPath);

        $views = new Storage($viewPath);
        return new Renderer($views->exploreFiles());
    }

    protected function setUp(): void
    {
        AssetsInserter::setInstance($this->getAssetsInserter());
    }

    protected function getAssetsInserter(): AssetsInserter
    {
        $assetsPath = Path::join(__DIR__, '../../integration-root/App/Assets');
        if (!is_dir($assetsPath))
            throw new \RuntimeException('Could not find directory : '.$assetsPath);

        $assets = new Storage($assetsPath);
        return new AssetsInserter($assets->exploreFiles());
    }

    public function testFindView()
    {
        $renderer = $this->getRenderer();

        $this->assertIsString($renderer->findView('details'));
        $this->assertIsString($renderer->findView('client/details'));
        $this->assertIsString($renderer->findView('order/details'));
        $this->assertNull($renderer->findView('order/inexistent'));
    }

    /** Names were matched as plain suffixes : the view `ails` found `details.php`. */
    public function testANameMatchesWholePathSegmentsOnly()
    {
        $renderer = $this->getRenderer();

        $this->assertNull($renderer->findView('ails'));
        $this->assertNull($renderer->findView('t/details'));
    }

    public function testEscapingAValueForHtml()
    {
        $this->assertEquals('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; &#039;quotes&#039;', e('<script>alert("x")</script> & \'quotes\''));
        $this->assertEquals('42', e(42));
    }

    public function testRenderSimple()
    {
        $renderer = $this->getRenderer();

        $html = $renderer->render('client/details');
        $this->assertStringContainsString('client details', $html);
    }

    public function testRenderWithContext()
    {
        $renderer = $this->getRenderer();

        $html = $renderer->render('order/details', [
            'rows' => [
                ['id' => 1, 'label' => 'computer'],
                ['id' => 2, 'label' => 'mouse'],
                ['id' => 3, 'label' => 'keyboard'],
                ['id' => 4, 'label' => 'screen'],
            ],
        ]);

        $this->assertStringContainsString('id=1', $html);
        $this->assertStringContainsString('id=2', $html);
        $this->assertStringContainsString('id=3', $html);
    }

    public function testRenderWithVariableScope()
    {
        $renderer = $this->getRenderer();

        $html = $renderer->render('test_variable_scope', ['a' => 5]);

        $this->assertStringContainsString('A=5', $html);
        $this->assertStringContainsString('AA=6', $html);
    }

    /**
     * The view used to be included in the scope of render() itself, where a context key named
     * `path` overwrote the very variable holding the file about to be required.
     */
    public function testRenderWithAContextKeyNamedLikeALocalVariable()
    {
        $renderer = new Renderer((new Storage(Path::join(__DIR__, 'Views')))->exploreFiles());

        $html = $renderer->render('context_scope', ['path' => 'from the context', 'context' => 'also from the context']);

        $this->assertStringContainsString('path=from the context', $html);
        $this->assertStringContainsString('context=also from the context', $html);
    }

    public function testRenderWithoutContext()
    {
        $renderer = new Renderer((new Storage(Path::join(__DIR__, 'Views')))->exploreFiles());

        $this->assertStringContainsString('plain view', $renderer->render('plain'));
    }

    public function testRenderOfAnUnknownView()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->getRenderer()->render('there/is/no/such/view');
    }

    public function testRenderAssets()
    {
        $renderer = $this->getRenderer();

        // Renders a script
        $html = $renderer->render('client/details');
        $this->assertStringContainsString('console.log', $html);

        // Renders some css
        $html = $renderer->render('order/details', ['rows' => []]);
        $this->assertStringContainsString('color: red', $html);
    }
}
