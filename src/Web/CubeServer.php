<?php

namespace YonisSavary\Cube\Web;

use Symfony\Component\Process\Process;
use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Logger\NullLogger;
use YonisSavary\Cube\Utils\Path;

class CubeServer
{
    protected int $port;
    protected Process $process;

    protected Logger $logger;

    protected string $origin;

    protected Storage $publicStorage;

    public function getPublicStorage(): Storage
    {
        return $this->publicStorage;
    }

    public function path(string $path): string
    {
        return
            $this->origin . ":" . $this->port .
            Path::join("/" . $path);
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function __destruct()
    {
        if ($this->process->isRunning())
            $this->process->stop();
    }

    public function log()
    {
        $logger = $this->logger;

        $logger->info($this->process->getIncrementalOutput());
        $logger->info($this->process->getIncrementalErrorOutput());
    }

    public function __construct(
        ?int $port=null,
        ?string $path=null,
        ?Logger $logger=null,
        int $safeTimeout=500,
        string $origin="http://localhost"
    )
    {
        $this->origin = $origin;
        $this->logger = $logger = $logger ?? new NullLogger;
        $this->port = $port = $port ?? random_int(8000, 10000);

        $path ??= Path::relative("Public");
        $this->publicStorage = new Storage($path);

        $logger->info("Starting cube server on port {port} at {path}...", ["port" => $port, "path" => $path]);
        $this->process = new Process(["php", "-S", "0.0.0.0:$port", "index.php"], $path);
        $this->process->start(fn() => $this->log());

        usleep($safeTimeout * 1000);

        if ($this->process->isRunning())
        {
            $logger->info("Server successfuly started.");
            $this->log();
        }
    }
}