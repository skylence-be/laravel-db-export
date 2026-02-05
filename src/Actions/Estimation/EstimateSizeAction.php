<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Estimation;

use Illuminate\Database\DatabaseManager;
use Xve\DbExport\Contracts\DiskCheckerInterface;
use Xve\DbExport\Contracts\SizeEstimatorInterface;
use Xve\DbExport\DTOs\SizeEstimate;
use Xve\DbExport\DTOs\TableInfo;

class EstimateSizeAction implements SizeEstimatorInterface
{
    protected const EXPORT_SIZE_RATIO = 0.7;

    protected const COMPRESSION_RATIO = 0.2;

    public function __construct(
        protected DatabaseManager $db
    ) {}

    /**
     * @param  array<int, TableInfo>  $tables
     */
    public function estimate(array $tables, bool $compressed = true): SizeEstimate
    {
        $totalData = 0;
        $totalIndex = 0;
        $totalRows = 0;

        foreach ($tables as $table) {
            if (! $table->structureOnly) {
                $totalData += $table->dataSize;
                $totalIndex += $table->indexSize;
                $totalRows += $table->rowCount;
            }
        }

        $totalBytes = $totalData + $totalIndex;
        $estimatedExportSize = $this->calculateExportSize($totalData);
        $estimatedCompressedSize = $compressed
            ? $this->calculateCompressedSize($estimatedExportSize)
            : $estimatedExportSize;

        $diskChecker = app(DiskCheckerInterface::class);
        /** @var string $exportPath */
        $exportPath = config('db-export.default_path', storage_path('app/db-exports'));
        $diskSpace = $diskChecker->check($exportPath, $compressed ? $estimatedCompressedSize : $estimatedExportSize);

        return new SizeEstimate(
            totalBytes: $totalBytes,
            dataBytes: $totalData,
            indexBytes: $totalIndex,
            tableCount: count($tables),
            rowCount: $totalRows,
            estimatedExportSize: $estimatedExportSize,
            estimatedCompressedSize: $estimatedCompressedSize,
            tables: $tables,
            diskSpace: $diskSpace
        );
    }

    public function getTableSize(string $table, ?string $connection = null): TableInfo
    {
        $conn = $this->db->connection($connection);
        $database = $conn->getDatabaseName();

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
                indexSize: 0
            );
        }

        /** @var array<string, mixed> $resultArray */
        $resultArray = (array) $result;

        return TableInfo::fromDatabaseRow($resultArray);
    }

    public function calculateExportSize(int $dataSize): int
    {
        return (int) round($dataSize * self::EXPORT_SIZE_RATIO);
    }

    public function calculateCompressedSize(int $exportSize): int
    {
        return (int) round($exportSize * self::COMPRESSION_RATIO);
    }
}
