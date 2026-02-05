<?php

declare(strict_types=1);

namespace Xve\DbExport;

use Illuminate\Contracts\Foundation\Application;
use Xve\DbExport\Actions\Estimation\GenerateBreakdownAction;
use Xve\DbExport\Config\ExportConfig;
use Xve\DbExport\Config\ProfileManager;
use Xve\DbExport\Contracts\DiskCheckerInterface;
use Xve\DbExport\Contracts\ExporterInterface;
use Xve\DbExport\Contracts\SizeEstimatorInterface;
use Xve\DbExport\Contracts\TableResolverInterface;
use Xve\DbExport\DTOs\ExportResult;
use Xve\DbExport\DTOs\SizeEstimate;
use Xve\DbExport\DTOs\TableInfo;
use Xve\DbExport\Exceptions\InsufficientDiskSpaceException;
use Xve\DbExport\Exceptions\InvalidProfileException;

class DbExportManager
{
    protected ?string $connection = null;

    protected ?string $profile = null;

    public function __construct(
        protected Application $app
    ) {}

    /**
     * Execute a database export.
     */
    public function export(?ExportConfig $config = null): ExportResult
    {
        $config = $this->prepareConfig($config);

        if ($config->dryRun) {
            $dryRunResult = $this->dryRun($config);

            /** @var array<int, string> $tables */
            $tables = $dryRunResult['tables'];
            /** @var array<int, string> $anonymizedTables */
            $anonymizedTables = $dryRunResult['anonymized_tables'];

            return ExportResult::success(
                path: $config->getFullPath(),
                fileSize: 0,
                duration: 0,
                tables: $tables,
                anonymizedTables: $anonymizedTables,
                compressed: $config->compress
            );
        }

        $tables = $this->resolveTables($config);

        $estimate = $this->estimateSize($tables, $config->compress);
        if (! $estimate->diskSpace->sufficient) {
            throw InsufficientDiskSpaceException::fromDiskSpaceResult($estimate->diskSpace);
        }

        $exporter = $this->app->make(ExporterInterface::class);

        return $exporter->export($config, $tables);
    }

    /**
     * Estimate export size without performing the export.
     */
    public function estimate(?ExportConfig $config = null): SizeEstimate
    {
        $config = $this->prepareConfig($config);
        $tables = $this->resolveTables($config);

        return $this->estimateSize($tables, $config->compress);
    }

    /**
     * Generate a detailed size breakdown.
     *
     * @return array<string, mixed>
     */
    public function breakdown(?ExportConfig $config = null): array
    {
        $estimate = $this->estimate($config);

        $generator = $this->app->make(GenerateBreakdownAction::class);

        return $generator->execute($estimate);
    }

    /**
     * Perform a dry run to see what would be exported.
     *
     * @return array<string, mixed>
     */
    public function dryRun(?ExportConfig $config = null): array
    {
        $config = $this->prepareConfig($config);
        $tables = $this->resolveTables($config);

        $tableData = array_map(fn (TableInfo $t): array => [
            'name' => $t->name,
            'rows' => $t->rowCount,
            'size' => $t->getHumanSize(),
            'structure_only' => $t->structureOnly,
            'is_view' => $t->isView,
        ], $tables);

        $anonymizedTables = array_keys($config->anonymize);

        return [
            'config' => $config->toArray(),
            'tables' => array_map(fn (TableInfo $t): string => $t->name, $tables),
            'table_details' => $tableData,
            'anonymized_tables' => $anonymizedTables,
            'table_count' => count($tables),
            'output_path' => $config->getFullPath(),
        ];
    }

    /**
     * Get all available profiles.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getProfiles(): array
    {
        return $this->app->make(ProfileManager::class)->getWithDescriptions();
    }

    /**
     * Get profile names.
     *
     * @return array<int, string>
     */
    public function getProfileNames(): array
    {
        return $this->app->make(ProfileManager::class)->getNames();
    }

    /**
     * Get a specific profile configuration.
     *
     * @return array<string, mixed>
     */
    public function getProfile(string $name): array
    {
        $manager = $this->app->make(ProfileManager::class);

        if (! $manager->exists($name)) {
            throw InvalidProfileException::notFound($name, $manager->getNames());
        }

        return $manager->get($name);
    }

    /**
     * Set the database connection to use.
     */
    public function forConnection(string $connection): self
    {
        $clone = clone $this;
        $clone->connection = $connection;

        return $clone;
    }

    /**
     * Set the profile to use.
     */
    public function withProfile(string $profile): self
    {
        $clone = clone $this;
        $clone->profile = $profile;

        return $clone;
    }

    /**
     * Prepare the export configuration.
     */
    protected function prepareConfig(?ExportConfig $config): ExportConfig
    {
        if (! $config instanceof \Xve\DbExport\Config\ExportConfig) {
            $config = new ExportConfig(
                connection: $this->connection,
                profile: $this->profile
            );
        }

        if ($config->profile !== null) {
            $profileConfig = $this->getProfile($config->profile);
            $config = $config->withProfile($profileConfig);
        }

        return $config;
    }

    /**
     * Resolve tables based on configuration.
     *
     * @return array<TableInfo>
     */
    protected function resolveTables(ExportConfig $config): array
    {
        $resolver = $this->app->make(TableResolverInterface::class);

        return $resolver->resolve($config);
    }

    /**
     * Estimate the size of the export.
     *
     * @param  array<TableInfo>  $tables
     */
    protected function estimateSize(array $tables, bool $compressed): SizeEstimate
    {
        $estimator = $this->app->make(SizeEstimatorInterface::class);

        return $estimator->estimate($tables, $compressed);
    }

    /**
     * Check available disk space.
     */
    public function checkDiskSpace(string $path, int $estimatedSize): bool
    {
        $checker = $this->app->make(DiskCheckerInterface::class);
        $result = $checker->check($path, $estimatedSize);

        return $result->sufficient;
    }
}
