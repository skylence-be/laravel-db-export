<?php

declare(strict_types=1);

namespace Xve\DbExport\DTOs;

readonly class SizeEstimate
{
    public function __construct(
        public int $totalBytes,
        public int $dataBytes,
        public int $indexBytes,
        public int $tableCount,
        public int $rowCount,
        public int $estimatedExportSize,
        public int $estimatedCompressedSize,
        /** @var array<TableInfo> */
        public array $tables,
        public DiskSpaceResult $diskSpace,
    ) {}

    public function getHumanTotalSize(): string
    {
        return $this->formatBytes($this->totalBytes);
    }

    public function getHumanEstimatedSize(): string
    {
        return $this->formatBytes($this->estimatedExportSize);
    }

    public function getHumanCompressedSize(): string
    {
        return $this->formatBytes($this->estimatedCompressedSize);
    }

    public function getCompressionRatio(): float
    {
        if ($this->estimatedExportSize === 0) {
            return 0;
        }

        return round(1 - ($this->estimatedCompressedSize / $this->estimatedExportSize), 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_bytes' => $this->totalBytes,
            'total_human' => $this->getHumanTotalSize(),
            'data_bytes' => $this->dataBytes,
            'index_bytes' => $this->indexBytes,
            'table_count' => $this->tableCount,
            'row_count' => $this->rowCount,
            'estimated_export_size' => $this->estimatedExportSize,
            'estimated_export_human' => $this->getHumanEstimatedSize(),
            'estimated_compressed_size' => $this->estimatedCompressedSize,
            'estimated_compressed_human' => $this->getHumanCompressedSize(),
            'compression_ratio' => $this->getCompressionRatio(),
            'tables' => array_map(fn (TableInfo $t): array => $t->toArray(), $this->tables),
            'disk_space' => $this->diskSpace->toArray(),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = (int) floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }
}
