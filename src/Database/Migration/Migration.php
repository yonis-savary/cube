<?php

namespace YonisSavary\Cube\Database\Migration;

use YonisSavary\Cube\Database\Database;

class Migration
{
    public readonly mixed $mustInstallChecker;

    public function __construct(
        public readonly ?string $install=null,
        public readonly ?string $uninstall=null
    ){}
}