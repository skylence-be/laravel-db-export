<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Tables;

use Illuminate\Database\DatabaseManager;

class FilterColumnsAction
{
    /** @var array<int, string> */
    protected array $excludeTypes = [
        'blob',
        'mediumblob',
        'longblob',
        'tinyblob',
    ];

    public function __construct(
        protected DatabaseManager $db
    ) {}

    /**
     * Get columns to include for a table, excluding specified columns and types.
     *
     * @param  array<string>  $excludeColumns
     * @return array<array{name: string, type: string}>
     */
    public function execute(string $table, array $excludeColumns = [], ?string $connection = null): array
    {
        $allColumns = $this->getTableColumns($table, $connection);

        return array_values(array_filter(
            $allColumns,
            fn (array $column): bool => ! in_array($column['name'], $excludeColumns, true)
        ));
    }

    /**
     * Get columns that should be excluded based on type (BLOB, LONGTEXT, etc.).
     *
     * @return array<string> Column names to exclude
     */
    public function getLargeColumnNames(string $table, ?string $connection = null): array
    {
        $columns = $this->getTableColumns($table, $connection);

        return array_values(array_map(
            fn (array $col): string => $col['name'],
            array_filter(
                $columns,
                fn (array $col): bool => in_array(strtolower((string) $col['type']), $this->excludeTypes, true)
            )
        ));
    }

    /**
     * Get all columns for a table with their types.
     *
     * @return array<array{name: string, type: string}>
     */
    public function getTableColumns(string $table, ?string $connection = null): array
    {
        $conn = $this->db->connection($connection);
        $database = $conn->getDatabaseName();

        $results = $conn->select(
            'SELECT COLUMN_NAME, DATA_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$database, $table]
        );

        return array_map(
            fn ($row): array => [
                'name' => $row->COLUMN_NAME,
                'type' => $row->DATA_TYPE,
            ],
            $results
        );
    }

    /**
     * Build a SELECT statement with specific columns.
     *
     * @param  array<int, array{name: string, type: string}>  $columns
     */
    public function buildSelectStatement(string $table, array $columns): string
    {
        if ($columns === []) {
            return sprintf('SELECT * FROM `%s`', $table);
        }

        $columnList = implode(', ', array_map(
            fn (array $col): string => sprintf('`%s`', $col['name']),
            $columns
        ));

        return sprintf('SELECT %s FROM `%s`', $columnList, $table);
    }

    /**
     * Set the types to exclude.
     *
     * @param  array<int, string>  $types
     */
    public function setExcludeTypes(array $types): self
    {
        $this->excludeTypes = array_map(strtolower(...), $types);

        return $this;
    }
}
