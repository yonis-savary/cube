<?php

namespace YonisSavary\Cube\Logger;

use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Throwable;
use YonisSavary\Cube\Core\Component;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Env\Storage;

class Logger extends AbstractLogger
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

    protected function interpolate(string|Stringable $message, array $context=[]): string
    {
        $message = (string) $message;
        foreach ($context as $key => $value)
            $message = str_replace("{".$key."}", $value, $message);

        return $message;
    }

    public function log($level, string|Stringable $message, array $context=[]): void
    {
        $message = $this->interpolate($message, $context);
        fputcsv($this->stream, [
            date("Y-m-d H:i:s"),
            $level,
            $message
        ], separator: "\t", enclosure:"'", escape:"\\");
    }

    public function logThrowable(Throwable $thrown): void
    {
        $this->error("Got {type} : {message}", ["type"=>$thrown::class, "message"=>$thrown->getMessage()]);
        $this->error("## {file}({line})", ["file" => $thrown->getFile(), "line" => $thrown->getLine()]);
        $trace = explode("\n", $thrown->getTraceAsString());
        foreach ($trace as $line)
            $this->error($line);
    }

}