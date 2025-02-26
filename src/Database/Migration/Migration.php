<?php

namespace Cube\Database\Migration;

class Migration
{
    public readonly mixed $mustInstallChecker;

    public function __construct(
        public readonly ?string $install = null,
        public readonly ?string $uninstall = null
    ) {}
}
