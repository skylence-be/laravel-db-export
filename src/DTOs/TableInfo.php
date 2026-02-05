<?php

declare(strict_types=1);

namespace Xve\DbExport\DTOs;

readonly class TableInfo
{
    /**
     * @param  array<int, string>  $excludedColumns
     */
    public function __construct(
        public string $name,
        public int $rowCount,
        public int $dataSize,
        public int $indexSize,
        public bool $isView = false,
        public bool $structureOnly = false,
        public array $excludedColumns = [],
    ) {}

    public function getTotalSize(): int
    {
        return $this->dataSize + $this->indexSize;
    }

    public function getHumanSize(): string
    {
        $bytes = $this->getTotalSize();
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = (int) floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'row_count' => $this->rowCount,
            'data_size' => $this->dataSize,
            'index_size' => $this->indexSize,
            'total_size' => $this->getTotalSize(),
            'human_size' => $this->getHumanSize(),
            'is_view' => $this->isView,
            'structure_only' => $this->structureOnly,
            'excluded_columns' => $this->excludedColumns,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $excludedColumns
     */
    public static function fromDatabaseRow(array $row, bool $structureOnly = false, array $excludedColumns = []): self
    {
        /** @var string $name */
        $name = $row['TABLE_NAME'] ?? $row['table_name'] ?? '';
        /** @var int $rowCount */
        $rowCount = $row['TABLE_ROWS'] ?? $row['table_rows'] ?? 0;
        /** @var int $dataLength */
        $dataLength = $row['DATA_LENGTH'] ?? $row['data_length'] ?? 0;
        /** @var int $indexLength */
        $indexLength = $row['INDEX_LENGTH'] ?? $row['index_length'] ?? 0;
        /** @var string $tableType */
        $tableType = $row['TABLE_TYPE'] ?? $row['table_type'] ?? 'BASE TABLE';

        return new self(
            name: (string) $name,
            rowCount: (int) $rowCount,
            dataSize: (int) $dataLength,
            indexSize: (int) $indexLength,
            isView: $tableType === 'VIEW',
            structureOnly: $structureOnly,
            excludedColumns: $excludedColumns,
        );
    }
}
