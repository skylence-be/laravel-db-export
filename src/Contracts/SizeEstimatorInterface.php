<?php

declare(strict_types=1);

namespace Xve\DbExport\Contracts;

use Xve\DbExport\DTOs\SizeEstimate;
use Xve\DbExport\DTOs\TableInfo;

interface SizeEstimatorInterface
{
    /**
     * Estimate the export size for given tables.
     *
     * @param  array<TableInfo>  $tables
     */
    public function estimate(array $tables, bool $compressed = true): SizeEstimate;

    /**
     * Get size information for a specific table.
     */
    public function getTableSize(string $table, ?string $connection = null): TableInfo;

    /**
     * Calculate estimated export size (SQL dump is typically 60-80% of data size).
     */
    public function calculateExportSize(int $dataSize): int;

    /**
     * Calculate estimated compressed size (gzip typically achieves 70-90% compression on SQL).
     */
    public function calculateCompressedSize(int $exportSize): int;
}
