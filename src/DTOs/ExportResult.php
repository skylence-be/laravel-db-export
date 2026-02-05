<?php

declare(strict_types=1);

namespace Xve\DbExport\DTOs;

readonly class ExportResult
{
    /**
     * @param  array<int, string>  $tables
     * @param  array<int, string>  $anonymizedTables
     */
    public function __construct(
        public bool $success,
        public string $path,
        public int $fileSize,
        public float $duration,
        public int $tableCount,
        public array $tables,
        public array $anonymizedTables,
        public bool $compressed,
        public ?string $error = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'path' => $this->path,
            'file_size' => $this->fileSize,
            'file_size_human' => $this->getHumanFileSize(),
            'duration' => $this->duration,
            'duration_human' => $this->getHumanDuration(),
            'table_count' => $this->tableCount,
            'tables' => $this->tables,
            'anonymized_tables' => $this->anonymizedTables,
            'compressed' => $this->compressed,
            'error' => $this->error,
        ];
    }

    public function getHumanFileSize(): string
    {
        $bytes = $this->fileSize;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = (int) floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }

    public function getHumanDuration(): string
    {
        if ($this->duration < 1) {
            return round($this->duration * 1000).'ms';
        }

        if ($this->duration < 60) {
            return round($this->duration, 2).'s';
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return $minutes.'m '.round($seconds).'s';
    }

    /**
     * @param  array<int, string>  $tables
     * @param  array<int, string>  $anonymizedTables
     */
    public static function success(
        string $path,
        int $fileSize,
        float $duration,
        array $tables,
        array $anonymizedTables = [],
        bool $compressed = false,
    ): self {
        return new self(
            success: true,
            path: $path,
            fileSize: $fileSize,
            duration: $duration,
            tableCount: count($tables),
            tables: $tables,
            anonymizedTables: $anonymizedTables,
            compressed: $compressed,
        );
    }

    public static function failure(string $error, float $duration = 0.0): self
    {
        return new self(
            success: false,
            path: '',
            fileSize: 0,
            duration: $duration,
            tableCount: 0,
            tables: [],
            anonymizedTables: [],
            compressed: false,
            error: $error,
        );
    }
}
