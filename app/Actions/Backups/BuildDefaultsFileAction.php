<?php

namespace App\Actions\Backups;

use RuntimeException;

class BuildDefaultsFileAction
{
    public function execute(): string
    {
        $connection = config('database.connections.mysql');
        $path = tempnam(sys_get_temp_dir(), 'laranode-mysql-');
        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary MySQL credentials file.');
        }

        $quote = fn ($value) => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value).'"';
        $contents = "[client]\n"
            .'user='.$quote($connection['username'] ?? '')."\n"
            .'password='.$quote($connection['password'] ?? '')."\n"
            .'host='.$quote($connection['host'] ?? '127.0.0.1')."\n"
            .'port='.(int) ($connection['port'] ?? 3306)."\n";

        file_put_contents($path, $contents);
        chmod($path, 0600);

        return $path;
    }
}
