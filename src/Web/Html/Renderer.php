<?php 

namespace Cube\Web\Html;

use Cube\Core\Autoloader;
use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Env\Logger\Logger;
use Exception;
use InvalidArgumentException;
use Throwable;

class Renderer 
{
    use Component;

    /** @var Bunch<string> $refinedAssetsFiles */
    protected Bunch $refinedAssetsFiles;

    public static function getDefaultInstance(): static
    {
        return new self(Autoloader::getViewFiles());
    }

    public function __construct(array $viewFiles=[])
    {
        $this->refinedAssetsFiles = Bunch::of($viewFiles);
    }

    public function findView(string $viewName): ?string {
        return $this->refinedAssetsFiles->first(fn($file) => str_ends_with(preg_replace("~\.[^/]+$~", "", $file), $viewName));
    }

    public function render(string $viewName, array $context=[]): string {
        $renderedHtml = "";

        foreach ($context as $key => $_) {
            if (!preg_match("~^[a-z_][a-z0-9_]*$~i", $key)) {
                Logger::getInstance()->warning("Cannot set $key variable for context (invalid PHP variable name)");
                unset($context[$key]);
            }
        }

        Renderer::withInstance($this, function() use ($viewName, $context, &$renderedHtml) {
            $path = $this->findView($viewName);
            if (!$path)
                throw new InvalidArgumentException("View [$viewName] not found");

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