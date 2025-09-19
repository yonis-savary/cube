<?php 

namespace Cube\Tests\Units\Web;

use Cube\Env\Storage;
use Cube\Utils\Path;
use Cube\Web\Html\Renderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RendererTest extends TestCase
{
    protected function getRenderer(): Renderer
    {
        $viewPath = Path::join(__DIR__, "../../root/App/Views");
        if (!is_dir($viewPath))
            throw new RuntimeException("Could not find directory : " . $viewPath);

        $views = new Storage($viewPath);
        return new Renderer($views->exploreFiles());
    }

    public function test_findView()
    {
        $renderer = $this->getRenderer();

        $this->assertIsString($renderer->findView("details"));
        $this->assertIsString($renderer->findView("client/details"));
        $this->assertIsString($renderer->findView("order/details"));
        $this->assertNull($renderer->findView("order/inexistent"));
    }

    public function test_render_simple()
    {
        $renderer = $this->getRenderer();


        $html = $renderer->render("client/details");
        $this->assertStringContainsString("client details", $html);
    }

    public function test_render_with_context()
    {
        $renderer = $this->getRenderer();

        $html = $renderer->render('order/details', [
            'rows' => [
                ['id' => 1, 'label' => 'computer'],
                ['id' => 2, 'label' => 'mouse'],
                ['id' => 3, 'label' => 'keyboard'],
                ['id' => 4, 'label' => 'screen'],
            ]
        ]);

        $this->assertStringContainsString("id=1", $html);
        $this->assertStringContainsString("id=2", $html);
        $this->assertStringContainsString("id=3", $html);
    }

    public function test_render_with_variable_scope()
    {
        $renderer = $this->getRenderer();

        $html = $renderer->render('test_variable_scope', ['a' => 5]);

        $this->assertStringContainsString("A=5", $html);
        $this->assertStringContainsString("AA=6", $html);
    }
}