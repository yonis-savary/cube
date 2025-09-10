<?php

namespace Cube\Data\Models\Events;

use Cube\Data\Database\Database;
use Cube\Event\Event;
use Cube\Data\Models\Model;

class SavedModel extends Event
{
    public function __construct(
        public Model $created,
        public ?Database $database = null
    ) {}
}
