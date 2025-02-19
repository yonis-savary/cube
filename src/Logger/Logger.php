<?php

namespace Cube\Logger;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Stringable;
use Throwable;
use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Env\Storage;
use Cube\Event\EventDispatcher;
use Cube\Logger\Events\LoggedMessage;
use Cube\Utils\Text;

class Logger extends EventDispatcher implements LoggerInterface
{
    use Component;

    protected static $loggers = [];

    protected string $file;
    protected $stream;

    public static function getDefaultInstance(): static
    {
        return new self("cube.csv");
    }

    public function __construct(string $file, ?Storage $storage=null)
    {
        $storage ??= Storage::getInstance()->child("Logs");
        $this->file = $file = $storage->path($file);
        if ($existing = self::$loggers[$file] ?? false)
            return $existing;

        self::$loggers[$file] = &$this;
        $newFile = !is_file($file);

        if (! $stream = fopen($file, "a"))
            throw new RuntimeException("Could not open file [$file] in append mode !");

        $this->stream = $stream;

        if ($newFile)
            fputcsv($this->stream, ["Datetime", "Level", "Message"], separator: "\t", enclosure:"'", escape:"\\");
    }

    public function __destruct()
    {
        if ($this->stream)
            fclose($this->stream);
    }

    public function log($level, null|string|Stringable $message, array $context=[]): void
    {
        if (!is_resource($this->stream))
            return;

        $message = Text::interpolate($message, $context);

        Bunch::fromExplode("\n", $message)
        ->forEach(function($line) use ($level) {
            fwrite($this->stream, join("\t", [
                date("Y-m-d H:i:s.B"),
                strtoupper($level),
                $line
            ]) . "\n");
        });

        $this->dispatch(new LoggedMessage($level, $message, $context));
    }

    public function attach(LoggerInterface $logger): self
    {
        $this->on(LoggedMessage::class, function(LoggedMessage $event) use ($logger) {
            $logger->log($event->level, $event->message, $event->context);
        });

        return $this;
    }

    public function logThrowable(Throwable $thrown): void
    {
        $this->error("Got {type} : {message}", ["type"=>$thrown::class, "message"=>$thrown->getMessage()]);
        $this->error("## {file}({line})", ["file" => $thrown->getFile(), "line" => $thrown->getLine()]);
        $trace = explode("\n", $thrown->getTraceAsString());
        foreach ($trace as $line)
            $this->error($line);
    }

    public function emergency(string|\Stringable $message, array $context = []): void {
        $this->log("emergency", $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void {
        $this->log("alert", $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void {
        $this->log("critical", $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void {
        $this->log("error", $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void {
        $this->log("warning", $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void {
        $this->log("notice", $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void {
        $this->log("info", $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void {
        $this->log("debug", $message, $context);
    }
}