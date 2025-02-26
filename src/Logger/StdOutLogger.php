<?php

namespace Cube\Logger;

use Cube\Data\Bunch;
use Cube\Logger\Events\LoggedMessage;

class StdOutLogger extends Logger
{
    protected static ?self $logger = null;

    public function __construct()
    {
        if (self::$logger) {
            return self::$logger;
        }

        $this->stream = fopen('php://stdout', 'w');

        self::$logger = $this;

        return $this;
    }

    public function log($level, null|string|\Stringable $message, array $context = []): void
    {
        $message ??= 'null';

        if (!is_resource($this->stream)) {
            return;
        }

        $message = $this->interpolate($message, $context);

        Bunch::fromExplode("\n", $message)
            ->forEach(function ($line) {
                fwrite($this->stream, join(' ', [
                    date('[D M j G:i:s Y]'),
                    $line,
                ])."\n");
            })
        ;

        $this->dispatch(new LoggedMessage($level, $message, $context));
    }
}
