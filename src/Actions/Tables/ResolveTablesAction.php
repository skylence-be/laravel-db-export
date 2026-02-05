<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Tables;

use Illuminate\Database\DatabaseManager;
use Xve\DbExport\Config\ExportConfig;
use Xve\DbExport\Contracts\TableResolverInterface;
use Xve\DbExport\DTOs\TableInfo;

class ResolveTablesAction implements TableResolverInterface
{
    public function __construct(
        protected DatabaseManager $db,
        protected ExpandWildcardsAction $expandWildcards
    ) {}

    public function resolve(ExportConfig $config): array
    {
        $connection = $config->connection;
        $allTables = $this->getAllTables($connection);
        $allViews = $config->includeViews ? $this->getAllViews($connection) : [];

        $tables = $this->filterTables($allTables, $config);

        $views = $config->includeViews ? $this->filterTables($allViews, $config) : [];

        $structureOnlyTables = $this->expandWildcards->execute(
            $config->getEffectiveStructureOnly(),
            array_merge($tables, $views)
        );

        /** @var array<string, array<int, string>> $excludedColumns */
        $excludedColumns = config('db-export.exclude_columns', []);

        $tableInfos = [];

        foreach ($tables as $table) {
            /** @var array<int, string> $tableExcludedColumns */
            $tableExcludedColumns = $excludedColumns[$table] ?? [];
            $tableInfos[] = $this->getTableInfo(
                $table,
                $connection,
                in_array($table, $structureOnlyTables, true),
                $tableExcludedColumns
            );
        }

        foreach ($views as $view) {
            $tableInfos[] = new TableInfo(
                name: $view,
                rowCount: 0,
                dataSize: 0,
                indexSize: 0,
                isView: true,
                structureOnly: true,
                excludedColumns: []
            );
        }

        return $tableInfos;
    }

    /**
     * @return array<int, string>
     */
    public function getAllTables(?string $connection = null): array
    {
        $conn = $this->db->connection($connection);
        $database = $conn->getDatabaseName();

        $results = $conn->select(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'",
            [$database]
        );

        return array_map(fn ($row) => $row->TABLE_NAME, $results);
    }

    /**
     * @return array<int, string>
     */
    public function getAllViews(?string $connection = null): array
    {
        $conn = $this->db->connection($connection);
        $database = $conn->getDatabaseName();

        $results = $conn->select(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'VIEW'",
            [$database]
        );

        return array_map(fn ($row) => $row->TABLE_NAME, $results);
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    protected function filterTables(array $tables, ExportConfig $config): array
    {
        if ($config->includeOnly !== null) {
            $tables = $this->expandWildcards->execute($config->includeOnly, $tables);
        }

        $excluded = $this->expandWildcards->execute($config->exclude, $tables);

        return array_values(array_diff($tables, $excluded));
    }

    /**
     * @param  array<int, string>  $excludedColumns
     */
    protected function getTableInfo(
        string $table,
        ?string $connection,
        bool $structureOnly,
        array $excludedColumns
    ): TableInfo {
        $conn = $this->db->connection($connection);
        $database = $conn->getDatabaseName();

        /** @var object|null $result */
        $result = $conn->selectOne(
            'SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, TABLE_TYPE
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$database, $table]
        );

        if (! $result) {
            return new TableInfo(
                name: $table,
                rowCount: 0,
                dataSize: 0,
                indexSize: 0,
                isView: false,
                structureOnly: $structureOnly,
                excludedColumns: $excludedColumns
            );
        }

        /** @var string $tableName */
        $tableName = $result->TABLE_NAME ?? '';
        /** @var int $tableRows */
        $tableRows = $result->TABLE_ROWS ?? 0;
        /** @var int $dataLength */
        $dataLength = $result->DATA_LENGTH ?? 0;
        /** @var int $indexLength */
        $indexLength = $result->INDEX_LENGTH ?? 0;
        /** @var string $tableType */
        $tableType = $result->TABLE_TYPE ?? 'BASE TABLE';

        return new TableInfo(
            name: $tableName,
            rowCount: (int) $tableRows,
            dataSize: (int) $dataLength,
            indexSize: (int) $indexLength,
            isView: $tableType === 'VIEW',
            structureOnly: $structureOnly,
            excludedColumns: $excludedColumns
        );
    }
}
