<?php

namespace App\Actions\Import;

use Illuminate\Database\Connection;
use RuntimeException;

final class LocalImportGuard
{
    public function check(Connection $source, Connection $target): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Legacy import is restricted to isolated local/testing databases.');
        }
        foreach ([[$source, 'source'], [$target, 'target']] as [$connection, $kind]) {
            $database = $connection->getDatabaseName();
            if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
                || ! in_array($connection->getConfig('host'), ['127.0.0.1', '::1'], true)
                || $connection->getConfig('unix_socket')
                || ! in_array($database, config('legacy.allowed_'.$kind.'_databases'), true)
                || (app()->environment('testing') && ! str_ends_with($database, '_test'))) {
                throw new RuntimeException('Import database connection is outside the allowed local scope.');
            }
            $actual = $connection->selectOne('SELECT DATABASE() AS db_name, @@hostname AS server_name');
            if ($actual->db_name !== $database || strcasecmp($actual->server_name, gethostname()) !== 0) {
                throw new RuntimeException('Import database/server identity does not match the local host.');
            }
        }
        if (strcasecmp($source->getDatabaseName(), $target->getDatabaseName()) === 0) {
            throw new RuntimeException('Source and target databases must be separate.');
        }
    }
}
