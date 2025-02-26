<?php

namespace Cube\Models\Events;

use Cube\Database\Database;
use Cube\Event\Event;
use Cube\Models\Model;

class SavedModel extends Event
{
    public function __construct(
        public Model $created,
        public ?Database $database = null
    ) {}
}
