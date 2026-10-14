<?php

namespace Cube\Web\Servers;

use Cube\Core\RequestContext;
use Cube\Env\Configuration;
use Cube\Env\Logger\Logger;
use Cube\Event\Events;
use Cube\Utils\Console;
use Cube\Utils\Shell;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use Cube\Web\Servers\Events\SocketServerSetup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use RuntimeException;

/**
 * Serves the application over a unix socket!
 * **Routes are registered by the listeners of `SocketServerSetup` event**
 */
class UnixSocketServer
{
    protected Router $router;
    protected RequestContext $context;
    protected Logger $errorLogger;

    /**
     * @param class-string[] $persistentComponents Components kept between requests, along with the Router
     */
    public function __construct(
        protected string $socketPath,
        ?Router $router = null,
        array $persistentComponents = [Events::class, Configuration::class, Logger::class],
    ) {
        $persistentComponents = [Router::class, ...$persistentComponents];
        $this->context = new RequestContext($persistentComponents);
        $this->errorLogger = Logger::forFile("socket-fatal.csv");

        $this->router = $router ?? self::createRouter();
    }

    public static function createRouter(): Router
    {
        return new Router(new RouterConfiguration(false, false, false));
    }

    public function setup(): void
    {
        (new SocketServerSetup($this->router))->dispatch();
    }

    public function serve(): void
    {
        $this->removeStaleSocketFile();

        Router::setInstance($this->router);
        Logger::setInstance(Logger::forFile("socket.csv"));

        $this->setup();

        $socket = new SocketServer("unix://{$this->socketPath}");

        try {
            (new HttpServer(fn (ServerRequestInterface $request) => $this->handle($request)))->listen($socket);
            $this->stopOnSignals($socket);

            Console::log("Listening on unix://{$this->socketPath}");
            Loop::run();
        } finally {
            $socket->close();

            if (file_exists($this->socketPath))
                unlink($this->socketPath);
        }
    }

    protected function stopOnSignals(SocketServer $socket): void
    {
        if (!function_exists('pcntl_signal'))
            return;

        $stop = function () use ($socket) {
            $socket->close();
            Loop::stop();
        };

        Loop::addSignal(SIGINT, $stop);
        Loop::addSignal(SIGTERM, $stop);
    }

    public function handle(ServerRequestInterface $psrRequest): ResponseInterface
    {
        return $this->context->run(function () use ($psrRequest) {
            $request = Request::fromPsrRequest($psrRequest);
            $request->logSelf();

            try {
                $response = $this->router->route($request);
            } catch (\Throwable $thrown) {
                $this->errorLogger->logThrowable($thrown);
                $response = Response::fromThrowable($thrown);
            } finally {
                $this->removeUnmovedUploads($request);
            }

            $response->logSelf();
            Shell::logRequestAndResponseToStdOut($request, $response);

            return $response->toPsrResponse();
        });
    }

    protected function removeStaleSocketFile(): void
    {
        if (!file_exists($this->socketPath))
            return;

        // A refused connection is the expected outcome for a file left behind by a killed server
        if ($connection = @stream_socket_client("unix://{$this->socketPath}")) {
            fclose($connection);
            throw new RuntimeException("Another server already listens on [{$this->socketPath}]");
        }

        unlink($this->socketPath);
    }

    protected function removeUnmovedUploads(Request $request): void
    {
        foreach ($request->getUploads() as $upload) {
            if ($upload->tempName && is_file($upload->tempName))
                unlink($upload->tempName);
        }
    }
}
