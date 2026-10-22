<?php

use App\Routines\Heartbeat;
use App\Routines\SlowReport;

Cube\everyMinute(SlowReport::class);
Cube\everyMinute([Heartbeat::class, 'ping']);
