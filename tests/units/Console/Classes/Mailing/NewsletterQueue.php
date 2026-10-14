<?php

namespace Cube\Tests\Units\Console\Classes\Mailing;

use Cube\Queue\Queue;

class NewsletterQueue extends Queue
{
    public function __invoke(string $recipient): void {}
}
