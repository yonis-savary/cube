<?php

namespace YonisSavary\Cube\Models\Events;

use YonisSavary\Cube\Event\AbstractEvent;
use YonisSavary\Cube\Models\Model;

class SavedModel extends AbstractEvent
{
    public function __construct(
        public Model $created
    ){}
}