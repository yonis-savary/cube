<?php

namespace Cube\Data\Database\Migration;

use Cube\Env\Configuration\ConfigurationElement;

class MigrationManagerConfiguration extends ConfigurationElement
{
    /**
     * @param string $tableName     Name of your database's table responsible of holding migration informations
     * @param string $directoryName Name of the subdirectory in your application directory that holds migrations
     */
    public function __construct(
        public readonly string $tableName = '__cube_migration',
        public readonly string $directoryName = 'Migrations'
    ) {}
}
