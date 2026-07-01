<?php

namespace App\Databases\Drivers;

use App\Databases\EngineCapabilities;

class MariaDbDriver extends MysqlDriver
{
    public function connectionName(): string
    {
        return 'mariadb_admin';
    }

    public function capabilities(): EngineCapabilities
    {
        return new EngineCapabilities(
            label: 'MariaDB',
            hasUsers: true,
            optionFields: ['charset', 'collation'],
        );
    }
}
