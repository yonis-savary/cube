<?php

namespace Cube\Env\Logger;

use Cube\Data\Bunch;
use Cube\Utils\Text;
use Cube\Env\Logger\Events\LoggedMessage;
use Exception;

class StdOutLogger extends Logger
{
    protected static $stdOutStream = null;

    public function __construct()
    {
        if (self::$stdOutStream) {
            $this->stream = self::$stdOutStream;
        } else {
            if (! $this->stream = fopen('php://stdout', 'w'))
                throw new Exception("Could not open stdout");

            self::$stdOutStream = $this->stream;
        }

        return $this;
    }

    public function log($level, null|string|\Stringable $message, array $context = []): void
    {
        $message ??= 'null';

        if (!is_resource($this->stream)) {
            return;
        }

        $message = Text::interpolate($message, $context);

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
