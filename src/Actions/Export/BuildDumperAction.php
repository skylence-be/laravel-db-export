<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Export;

use Spatie\DbDumper\Databases\MySql;
use Xve\DbExport\Config\ExportConfig;
use Xve\DbExport\DTOs\TableInfo;

class BuildDumperAction
{
    /**
     * Build and configure a MySql dumper instance.
     *
     * @param  array<TableInfo>  $tables
     */
    public function execute(ExportConfig $config, array $tables): MySql
    {
        /** @var string $defaultConnection */
        $defaultConnection = config('database.default');
        $connection = $config->connection ?? $defaultConnection;
        /** @var array<string, mixed> $dbConfig */
        $dbConfig = config('database.connections.'.$connection);

        /** @var string $host */
        $host = $dbConfig['host'] ?? '127.0.0.1';
        /** @var int $port */
        $port = $dbConfig['port'] ?? 3306;
        /** @var string $database */
        $database = $dbConfig['database'] ?? '';
        /** @var string $username */
        $username = $dbConfig['username'] ?? '';
        /** @var string $password */
        $password = $dbConfig['password'] ?? '';

        /** @var MySql $dumper */
        $dumper = MySql::create()
            ->setHost($host)
            ->setPort((int) $port)
            ->setDbName($database)
            ->setUserName($username)
            ->setPassword($password);

        /** @var string|null $socket */
        $socket = $dbConfig['unix_socket'] ?? null;
        if (! empty($socket)) {
            $dumper->setSocket($socket);
        }

        /** @var string|null $dumpBinaryPath */
        $dumpBinaryPath = config('db-export.mysql_options.dump_binary_path');
        if (! empty($dumpBinaryPath)) {
            $dumper->setDumpBinaryPath($dumpBinaryPath);
        }

        $this->applyMysqlOptions($dumper);

        $this->applyTableConfiguration($dumper, $tables);

        return $dumper;
    }

    /**
     * Apply MySQL dump options from configuration.
     */
    protected function applyMysqlOptions(MySql $dumper): void
    {
        /** @var array<string, mixed> $options */
        $options = config('db-export.mysql_options', []);

        if ($options['single_transaction'] ?? true) {
            $dumper->useSingleTransaction();
        }

        if ($options['quick'] ?? true) {
            $dumper->useQuick();
        }

        if ($options['skip_lock_tables'] ?? true) {
            $dumper->skipLockTables();
        }

        $extraOptions = [];

        // Only add GTID option if explicitly set (MySQL-specific, not for MariaDB)
        /** @var string|null $gtidPurged */
        $gtidPurged = $options['set_gtid_purged'] ?? null;
        if ($gtidPurged !== null) {
            $extraOptions[] = '--set-gtid-purged='.$gtidPurged;
        }

        // Only add column_statistics if explicitly set to false (MySQL 8+ specific)
        $columnStats = $options['column_statistics'] ?? null;
        if ($columnStats === false) {
            $extraOptions[] = '--column-statistics=0';
        }

        if ($options['routines'] ?? true) {
            $extraOptions[] = '--routines';
        }

        if ($options['triggers'] ?? true) {
            $extraOptions[] = '--triggers';
        }

        if ($extraOptions !== []) {
            $dumper->addExtraOption(implode(' ', $extraOptions));
        }
    }

    /**
     * Apply table-specific configuration to the dumper.
     *
     * @param  array<TableInfo>  $tables
     */
    protected function applyTableConfiguration(MySql $dumper, array $tables): void
    {
        $includeTables = [];
        $structureOnly = [];

        foreach ($tables as $table) {
            if ($table->isView) {
                continue;
            }

            $includeTables[] = $table->name;

            if ($table->structureOnly) {
                $structureOnly[] = $table->name;
            }
        }

        if ($includeTables !== []) {
            $dumper->includeTables($includeTables);
        }

        if ($structureOnly !== []) {
            $dumper->excludeTables($structureOnly);
        }
    }

    /**
     * Build a separate dumper for structure-only tables.
     *
     * @param  array<TableInfo>  $tables
     */
    public function buildStructureOnlyDumper(ExportConfig $config, array $tables): ?MySql
    {
        $structureOnlyTables = array_filter(
            $tables,
            fn (TableInfo $t): bool => $t->structureOnly && ! $t->isView
        );

        if ($structureOnlyTables === []) {
            return null;
        }

        /** @var string $defaultConnection */
        $defaultConnection = config('database.default');
        $connection = $config->connection ?? $defaultConnection;
        /** @var array<string, mixed> $dbConfig */
        $dbConfig = config('database.connections.'.$connection);

        /** @var string $host */
        $host = $dbConfig['host'] ?? '127.0.0.1';
        /** @var int $port */
        $port = $dbConfig['port'] ?? 3306;
        /** @var string $database */
        $database = $dbConfig['database'] ?? '';
        /** @var string $username */
        $username = $dbConfig['username'] ?? '';
        /** @var string $password */
        $password = $dbConfig['password'] ?? '';

        /** @var MySql $dumper */
        $dumper = MySql::create()
            ->setHost($host)
            ->setPort((int) $port)
            ->setDbName($database)
            ->setUserName($username)
            ->setPassword($password);

        /** @var string|null $socket */
        $socket = $dbConfig['unix_socket'] ?? null;
        if (! empty($socket)) {
            $dumper->setSocket($socket);
        }

        /** @var string|null $dumpBinaryPath */
        $dumpBinaryPath = config('db-export.mysql_options.dump_binary_path');
        if (! empty($dumpBinaryPath)) {
            $dumper->setDumpBinaryPath($dumpBinaryPath);
        }

        $tableNames = array_map(fn (TableInfo $t): string => $t->name, $structureOnlyTables);
        $dumper->includeTables($tableNames);
        $dumper->doNotCreateTables();
        $dumper->addExtraOption('--no-data');

        return $dumper;
    }
}
