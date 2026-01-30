<?php 

namespace Cube\Web\Html;

use Cube\Core\Autoloader;
use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Utils\Path;
use Exception;
use InvalidArgumentException;
use Throwable;

class Renderer 
{
    use Component;

    /** @var Bunch<string> $viewFiles */
    protected Bunch $viewFiles;

    public static function getDefaultInstance(): static
    {
        return new self(Autoloader::getViewFiles());
    }

    public function __construct(array $viewFiles=[])
    {
        $this->viewFiles = Bunch::of($viewFiles);
    }

    public function findView(string $viewName): ?string {
        return $this->viewFiles->first(fn($file) => str_ends_with(preg_replace("~\.[^/]+$~", "", $file), $viewName));
    }

    public function render(string $viewName, array $context=[]): string {
        $renderedHtml = "";

        foreach ($context as $key => $_) {
            if (!preg_match("~^[a-z_][a-z0-9_]*$~i", $key)) {
                unset($context[$key]);
            }
        }

        Renderer::withInstance($this, function() use ($viewName, $context, &$renderedHtml) {
            $path = $this->findView($viewName);
            if (!$path)
                throw new InvalidArgumentException("View [$viewName] not found");

            $path = Path::relative($path);

            if (!ob_start())
                throw new Exception('Could not start a new output buffering');

            try
            {
                foreach ($context as $key => $value)
                    $$key = $value;

                require $path;
            }
            catch (Throwable $err)
            {
                ob_end_clean();
                throw $err;
            }

            $renderedHtml = ob_get_clean();
        });
        return $renderedHtml;
    }
}