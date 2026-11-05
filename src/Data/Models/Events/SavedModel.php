<?php

namespace Cube\Data\Models\Events;

use Cube\Data\Database\Database;
use Cube\Data\Models\Model;
use Cube\Event\Event;

class SavedModel extends Event
{
    public function __construct(
        public Model $created,
        public Database $database
    ) {}
}
