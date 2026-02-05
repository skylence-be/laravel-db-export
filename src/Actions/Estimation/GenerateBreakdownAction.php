<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Estimation;

use Xve\DbExport\DTOs\SizeEstimate;
use Xve\DbExport\DTOs\TableInfo;

class GenerateBreakdownAction
{
    /**
     * Generate a detailed breakdown report of the size estimate.
     *
     * @return array<string, mixed>
     */
    public function execute(SizeEstimate $estimate): array
    {
        $tables = $this->sortTablesBySize($estimate->tables);
        $topTables = array_slice($tables, 0, 10);

        return [
            'summary' => $this->generateSummary($estimate),
            'disk_space' => $estimate->diskSpace->toArray(),
            'top_tables' => $this->formatTableList($topTables),
            'by_category' => $this->categorizeBySize($tables),
            'structure_only' => $this->getStructureOnlyTables($tables),
            'recommendations' => $this->generateRecommendations($estimate),
        ];
    }

    /**
     * Generate a summary section.
     *
     * @return array<string, mixed>
     */
    protected function generateSummary(SizeEstimate $estimate): array
    {
        return [
            'total_tables' => $estimate->tableCount,
            'total_rows' => $estimate->rowCount,
            'total_rows_formatted' => number_format($estimate->rowCount),
            'database_size' => $estimate->getHumanTotalSize(),
            'estimated_export_size' => $estimate->getHumanEstimatedSize(),
            'estimated_compressed_size' => $estimate->getHumanCompressedSize(),
            'compression_savings' => $this->formatPercentage($estimate->getCompressionRatio()),
        ];
    }

    /**
     * Sort tables by total size descending.
     *
     * @param  array<int, TableInfo>  $tables
     * @return array<int, TableInfo>
     */
    protected function sortTablesBySize(array $tables): array
    {
        usort($tables, fn (TableInfo $a, TableInfo $b): int => $b->getTotalSize() <=> $a->getTotalSize());

        return $tables;
    }

    /**
     * Format a list of tables for display.
     *
     * @param  array<int, TableInfo>  $tables
     * @return array<int, array<string, mixed>>
     */
    protected function formatTableList(array $tables): array
    {
        return array_map(fn (TableInfo $table): array => [
            'name' => $table->name,
            'rows' => number_format($table->rowCount),
            'size' => $table->getHumanSize(),
            'size_bytes' => $table->getTotalSize(),
            'structure_only' => $table->structureOnly,
            'is_view' => $table->isView,
        ], $tables);
    }

    /**
     * Categorize tables by size ranges.
     *
     * @param  array<int, TableInfo>  $tables
     * @return array<string, array<string, mixed>>
     */
    protected function categorizeBySize(array $tables): array
    {
        $labels = [
            'large' => '> 100 MB',
            'medium' => '10 - 100 MB',
            'small' => '1 - 10 MB',
            'tiny' => '< 1 MB',
        ];

        /** @var array<string, array<int, string>> $tablesByCategory */
        $tablesByCategory = [
            'large' => [],
            'medium' => [],
            'small' => [],
            'tiny' => [],
        ];

        foreach ($tables as $table) {
            $size = $table->getTotalSize();

            if ($size >= 100 * 1024 * 1024) {
                $tablesByCategory['large'][] = $table->name;
            } elseif ($size >= 10 * 1024 * 1024) {
                $tablesByCategory['medium'][] = $table->name;
            } elseif ($size >= 1024 * 1024) {
                $tablesByCategory['small'][] = $table->name;
            } else {
                $tablesByCategory['tiny'][] = $table->name;
            }
        }

        /** @var array<string, array<string, mixed>> $result */
        $result = [];
        foreach ($tablesByCategory as $category => $categoryTables) {
            $result[$category] = [
                'label' => $labels[$category],
                'count' => count($categoryTables),
                'tables' => $categoryTables,
            ];
        }

        return $result;
    }

    /**
     * Get structure-only tables.
     *
     * @param  array<int, TableInfo>  $tables
     * @return array<int, array<string, mixed>>
     */
    protected function getStructureOnlyTables(array $tables): array
    {
        $structureOnly = array_filter($tables, fn (TableInfo $t): bool => $t->structureOnly);

        return array_values(array_map(fn (TableInfo $t): array => [
            'name' => $t->name,
            'rows_skipped' => number_format($t->rowCount),
            'size_saved' => $t->getHumanSize(),
        ], $structureOnly));
    }

    /**
     * Generate recommendations based on the estimate.
     *
     * @return array<int, array{type: string, message: string}>
     */
    protected function generateRecommendations(SizeEstimate $estimate): array
    {
        /** @var array<int, array{type: string, message: string}> $recommendations */
        $recommendations = [];

        if (! $estimate->diskSpace->sufficient) {
            $recommendations[] = [
                'type' => 'error',
                'message' => 'Insufficient disk space. Consider using compression or excluding large tables.',
            ];
        }

        $largeTables = array_filter(
            $estimate->tables,
            fn (TableInfo $t): bool => $t->getTotalSize() > 100 * 1024 * 1024 && ! $t->structureOnly
        );

        if ($largeTables !== []) {
            $names = array_map(fn (TableInfo $t): string => $t->name, $largeTables);
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Large tables detected: '.implode(', ', $names).
                    '. Consider using structure_only for these tables.',
            ];
        }

        if ($estimate->getCompressionRatio() > 0.7) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'High compression ratio expected. Compression is recommended.',
            ];
        }

        return $recommendations;
    }

    protected function formatPercentage(float $ratio): string
    {
        return round($ratio * 100).'%';
    }

    /**
     * Generate a text-based report.
     */
    public function generateTextReport(SizeEstimate $estimate): string
    {
        $breakdown = $this->execute($estimate);
        /** @var array<int, string> $lines */
        $lines = [];

        $lines[] = '=== Database Export Size Estimate ===';
        $lines[] = '';
        $lines[] = 'Summary:';
        /** @var array<string, mixed> $summary */
        $summary = $breakdown['summary'];
        /** @var string $totalTables */
        $totalTables = $summary['total_tables'] ?? '';
        /** @var string $totalRowsFormatted */
        $totalRowsFormatted = $summary['total_rows_formatted'] ?? '';
        /** @var string $databaseSize */
        $databaseSize = $summary['database_size'] ?? '';
        /** @var string $estimatedExportSize */
        $estimatedExportSize = $summary['estimated_export_size'] ?? '';
        /** @var string $estimatedCompressedSize */
        $estimatedCompressedSize = $summary['estimated_compressed_size'] ?? '';
        /** @var string $compressionSavings */
        $compressionSavings = $summary['compression_savings'] ?? '';
        $lines[] = '  Tables: '.$totalTables;
        $lines[] = '  Rows: '.$totalRowsFormatted;
        $lines[] = '  Database Size: '.$databaseSize;
        $lines[] = '  Estimated Export: '.$estimatedExportSize;
        $lines[] = '  Compressed: '.$estimatedCompressedSize.' ('.$compressionSavings.' savings)';
        $lines[] = '';

        $lines[] = 'Disk Space:';
        /** @var array<string, mixed> $diskSpace */
        $diskSpace = $breakdown['disk_space'];
        $status = $diskSpace['sufficient'] ? 'OK' : 'INSUFFICIENT';
        $lines[] = '  Status: '.$status;
        /** @var string $availableMb */
        $availableMb = $diskSpace['available_mb'] ?? '';
        /** @var string $requiredMb */
        $requiredMb = $diskSpace['required_mb'] ?? '';
        $lines[] = '  Available: '.$availableMb.' MB';
        $lines[] = '  Required: '.$requiredMb.' MB';

        if (! empty($diskSpace['warning'])) {
            /** @var string $warning */
            $warning = $diskSpace['warning'];
            $lines[] = '  Warning: '.$warning;
        }

        $lines[] = '';
        $lines[] = 'Top 10 Largest Tables:';
        /** @var array<int, array<string, mixed>> $topTables */
        $topTables = $breakdown['top_tables'];
        foreach ($topTables as $table) {
            $suffix = $table['structure_only'] ? ' (structure only)' : '';
            /** @var string $tableName */
            $tableName = $table['name'] ?? '';
            /** @var string $tableSize */
            $tableSize = $table['size'] ?? '';
            /** @var string $tableRows */
            $tableRows = $table['rows'] ?? '';
            $lines[] = '  - '.$tableName.': '.$tableSize.' ('.$tableRows.' rows)'.$suffix;
        }

        /** @var array<int, array{type: string, message: string}> $recommendations */
        $recommendations = $breakdown['recommendations'];
        if (! empty($recommendations)) {
            $lines[] = '';
            $lines[] = 'Recommendations:';
            foreach ($recommendations as $rec) {
                $lines[] = sprintf('  [%s] %s', $rec['type'], $rec['message']);
            }
        }

        return implode("\n", $lines);
    }
}
