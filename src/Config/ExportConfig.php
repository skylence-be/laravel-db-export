<?php

declare(strict_types=1);

namespace Xve\DbExport\Config;

readonly class ExportConfig
{
    /**
     * @param  array<int, string>  $exclude
     * @param  array<int, string>  $structureOnly
     * @param  array<int, string>  $includeData
     * @param  array<int, string>|null  $includeOnly
     * @param  array<string, array<string, array<string, mixed>>>  $anonymize
     */
    public function __construct(
        public ?string $connection = null,
        public ?string $profile = null,
        public ?string $outputPath = null,
        public ?string $filename = null,
        public bool $compress = true,
        public array $exclude = [],
        public array $structureOnly = [],
        public array $includeData = [],
        public ?array $includeOnly = null,
        public array $anonymize = [],
        public bool $includeViews = true,
        public bool $disableForeignKeys = true,
        public bool $dryRun = false,
    ) {}

    /**
     * Get effective structure-only tables (excluding those in includeData).
     *
     * @return array<int, string>
     */
    public function getEffectiveStructureOnly(): array
    {
        if ($this->includeData === []) {
            return $this->structureOnly;
        }

        return array_values(array_filter(
            $this->structureOnly,
            fn (string $table): bool => ! in_array($table, $this->includeData, true)
        ));
    }

    public function getOutputPath(): string
    {
        if ($this->outputPath !== null) {
            return $this->outputPath;
        }

        /** @var string $path */
        $path = config('db-export.default_path', storage_path('app/db-exports'));

        return $path;
    }

    public function getFilename(): string
    {
        if ($this->filename !== null) {
            return $this->filename;
        }

        /** @var string $defaultConnection */
        $defaultConnection = config('database.default');
        /** @var string $connection */
        $connection = $this->connection ?? $defaultConnection;
        /** @var string $database */
        $database = config(sprintf('database.connections.%s.database', $connection), 'database');
        $timestamp = date('Y-m-d_His');
        $extension = $this->compress ? '.sql.gz' : '.sql';

        return sprintf('%s_%s%s', $database, $timestamp, $extension);
    }

    public function getFullPath(): string
    {
        return rtrim($this->getOutputPath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$this->getFilename();
    }

    /**
     * @param  array<string, mixed>  $profileConfig
     */
    public function withProfile(array $profileConfig): self
    {
        /** @var array<int, string> $profileExclude */
        $profileExclude = $profileConfig['exclude'] ?? [];
        /** @var array<int, string> $profileStructureOnly */
        $profileStructureOnly = $profileConfig['structure_only'] ?? [];
        /** @var array<int, string>|null $profileIncludeOnly */
        $profileIncludeOnly = $profileConfig['include_only'] ?? null;
        /** @var array<string, array<string, array<string, mixed>>> $profileAnonymize */
        $profileAnonymize = $profileConfig['anonymize'] ?? [];

        return new self(
            connection: $this->connection,
            profile: $this->profile,
            outputPath: $this->outputPath,
            filename: $this->filename,
            compress: $this->compress,
            exclude: array_merge($profileExclude, $this->exclude),
            structureOnly: array_merge($profileStructureOnly, $this->structureOnly),
            includeData: $this->includeData,
            includeOnly: $this->includeOnly ?? $profileIncludeOnly,
            anonymize: array_merge($profileAnonymize, $this->anonymize),
            includeViews: $this->includeViews,
            disableForeignKeys: $this->disableForeignKeys,
            dryRun: $this->dryRun,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'connection' => $this->connection,
            'profile' => $this->profile,
            'output_path' => $this->getOutputPath(),
            'filename' => $this->getFilename(),
            'full_path' => $this->getFullPath(),
            'compress' => $this->compress,
            'exclude' => $this->exclude,
            'structure_only' => $this->structureOnly,
            'include_data' => $this->includeData,
            'include_only' => $this->includeOnly,
            'anonymize' => $this->anonymize,
            'include_views' => $this->includeViews,
            'disable_foreign_keys' => $this->disableForeignKeys,
            'dry_run' => $this->dryRun,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<int, string> $exclude */
        $exclude = $data['exclude'] ?? [];
        /** @var array<int, string> $structureOnly */
        $structureOnly = $data['structure_only'] ?? [];
        /** @var array<int, string> $includeData */
        $includeData = $data['include_data'] ?? [];
        /** @var array<int, string>|null $includeOnly */
        $includeOnly = $data['include_only'] ?? null;
        /** @var array<string, array<string, array<string, mixed>>> $anonymize */
        $anonymize = $data['anonymize'] ?? [];

        /** @var string|null $connection */
        $connection = $data['connection'] ?? null;
        /** @var string|null $profile */
        $profile = $data['profile'] ?? null;
        /** @var string|null $outputPath */
        $outputPath = $data['output_path'] ?? null;
        /** @var string|null $filename */
        $filename = $data['filename'] ?? null;

        return new self(
            connection: $connection !== null ? (string) $connection : null,
            profile: $profile !== null ? (string) $profile : null,
            outputPath: $outputPath !== null ? (string) $outputPath : null,
            filename: $filename !== null ? (string) $filename : null,
            compress: (bool) ($data['compress'] ?? true),
            exclude: $exclude,
            structureOnly: $structureOnly,
            includeData: $includeData,
            includeOnly: $includeOnly,
            anonymize: $anonymize,
            includeViews: (bool) ($data['include_views'] ?? true),
            disableForeignKeys: (bool) ($data['disable_foreign_keys'] ?? true),
            dryRun: (bool) ($data['dry_run'] ?? false),
        );
    }
}
