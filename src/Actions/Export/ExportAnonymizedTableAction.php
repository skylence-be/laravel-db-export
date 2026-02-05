<?php

declare(strict_types=1);

namespace Dwb\DbExport\Actions\Export;

use Dwb\DbExport\Actions\Anonymization\AnonymizeTableAction;
use Dwb\DbExport\Actions\Anonymization\LoadAnonymizationRulesAction;
use Dwb\DbExport\Config\ExportConfig;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class ExportAnonymizedTableAction
{
    protected const BATCH_SIZE = 1000;

    public function __construct(
        protected LoadAnonymizationRulesAction $loadRules,
        protected AnonymizeTableAction $anonymizer
    ) {}

    /**
     * Export a table with anonymization applied.
     *
     * @param  resource  $handle  File handle to write to
     */
    public function execute(string $table, ExportConfig $config, $handle): void
    {
        $anonymizationConfig = $this->loadRules->execute($config);

        if (! $anonymizationConfig->hasRulesForTable($table)) {
            return;
        }

        $connection = $this->getConnection($config);
        $totalRows = $connection->table($table)->count();

        if ($totalRows === 0) {
            return;
        }

        fwrite($handle, "\n-- Anonymized data for table `{$table}`\n");
        fwrite($handle, "TRUNCATE TABLE `{$table}`;\n");

        $offset = 0;
        $isFirstBatch = true;

        while ($offset < $totalRows) {
            /** @var array<int, object> $rows */
            $rows = $connection->table($table)
                ->offset($offset)
                ->limit(self::BATCH_SIZE)
                ->get()
                ->toArray();

            if ($rows === []) {
                break;
            }

            // Convert objects to arrays
            /** @var array<int, array<string, mixed>> $rowArrays */
            $rowArrays = array_map(fn (object $row): array => (array) $row, $rows);

            // Anonymize the data
            $anonymizedRows = $this->anonymizer->anonymize($table, $rowArrays, $anonymizationConfig);

            // Generate INSERT statements
            $this->writeInsertStatements($handle, $table, $anonymizedRows, $isFirstBatch);

            $offset += self::BATCH_SIZE;
            $isFirstBatch = false;
        }

        fwrite($handle, "\n");
    }

    /**
     * Write INSERT statements to the file handle.
     *
     * @param  resource  $handle
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function writeInsertStatements($handle, string $table, array $rows, bool $includeHeader = true): void
    {
        if ($rows === []) {
            return;
        }

        /** @var array<string, mixed> $firstRow */
        $firstRow = $rows[0];
        $columns = array_keys($firstRow);
        $columnList = '`'.implode('`, `', $columns).'`';

        if ($includeHeader) {
            fwrite($handle, "INSERT INTO `{$table}` ({$columnList}) VALUES\n");
        }

        $valueStrings = [];
        foreach ($rows as $row) {
            $values = array_map([$this, 'escapeValue'], $row);
            $valueStrings[] = '('.implode(', ', $values).')';
        }

        fwrite($handle, implode(",\n", $valueStrings).";\n");
    }

    protected function escapeValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $stringValue = is_string($value) ? $value : (json_encode($value) ?: '');

        $escaped = str_replace(
            ['\\', "\x00", "\n", "\r", "'", '"', "\x1a"],
            ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'],
            $stringValue
        );

        return sprintf("'%s'", $escaped);
    }

    protected function getConnection(ExportConfig $config): ConnectionInterface
    {
        /** @var string $defaultConnection */
        $defaultConnection = config('database.default');
        $connectionName = $config->connection ?? $defaultConnection;

        return DB::connection($connectionName);
    }
}
