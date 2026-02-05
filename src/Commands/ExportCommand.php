<?php

declare(strict_types=1);

namespace Xve\DbExport\Commands;

use Illuminate\Console\Command;
use Xve\DbExport\Config\ExportConfig;
use Xve\DbExport\DbExportManager;
use Xve\DbExport\Exceptions\ExportException;
use Xve\DbExport\Exceptions\InsufficientDiskSpaceException;

class ExportCommand extends Command
{
    protected $signature = 'db:export
        {--profile= : The export profile to use}
        {--connection= : The database connection to use}
        {--path= : The output directory path}
        {--filename= : The output filename}
        {--no-compress : Disable compression}
        {--exclude=* : Tables to exclude (can be used multiple times)}
        {--structure-only=* : Tables to export structure only (can be used multiple times)}
        {--include-data=* : Override structure-only and include data for these tables}
        {--include-only=* : Only include these tables (can be used multiple times)}
        {--no-views : Exclude database views}
        {--no-fk-wrapper : Disable foreign key wrapper}
        {--dry-run : Show what would be exported without exporting}
        {--force : Force export without confirmation}';

    protected $description = 'Export the database with profile-based exclusions and anonymization';

    public function handle(DbExportManager $manager): int
    {
        $config = $this->buildConfig();

        if ($config->dryRun) {
            return $this->handleDryRun($manager, $config);
        }

        if (! $this->option('force') && ! $this->confirmExport($manager, $config)) {
            $this->info('Export cancelled.');

            return self::SUCCESS;
        }

        return $this->executeExport($manager, $config);
    }

    protected function buildConfig(): ExportConfig
    {
        /** @var string|null $connection */
        $connection = $this->option('connection');
        /** @var string|null $profile */
        $profile = $this->option('profile');
        /** @var string|null $outputPath */
        $outputPath = $this->option('path');
        /** @var string|null $filename */
        $filename = $this->option('filename');
        /** @var array<int, string> $exclude */
        $exclude = $this->option('exclude') ?: [];
        /** @var array<int, string> $structureOnly */
        $structureOnly = $this->option('structure-only') ?: [];
        /** @var array<int, string> $includeData */
        $includeData = $this->option('include-data') ?: [];
        /** @var array<int, string>|null $includeOnly */
        $includeOnly = $this->option('include-only');

        return new ExportConfig(
            connection: $connection,
            profile: $profile,
            outputPath: $outputPath,
            filename: $filename,
            compress: ! $this->option('no-compress'),
            exclude: $exclude,
            structureOnly: $structureOnly,
            includeData: $includeData,
            includeOnly: empty($includeOnly) ? null : $includeOnly,
            anonymize: [],
            includeViews: ! $this->option('no-views'),
            disableForeignKeys: ! $this->option('no-fk-wrapper'),
            dryRun: (bool) $this->option('dry-run'),
        );
    }

    protected function handleDryRun(DbExportManager $manager, ExportConfig $config): int
    {
        $this->info('Dry run mode - no export will be performed');
        $this->newLine();

        try {
            $result = $manager->dryRun($config);

            $this->displayDryRunResult($result);

            return self::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->error('Error: '.$throwable->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function displayDryRunResult(array $result): void
    {
        /** @var array<string, mixed> $config */
        $config = $result['config'];

        $this->info('Export Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Profile', $config['profile'] ?? 'default'],
                ['Connection', $config['connection'] ?? 'default'],
                ['Output Path', $result['output_path']],
                ['Compression', $config['compress'] ? 'Yes' : 'No'],
                ['Include Views', $config['include_views'] ? 'Yes' : 'No'],
                ['FK Wrapper', $config['disable_foreign_keys'] ? 'Yes' : 'No'],
            ]
        );

        $this->newLine();
        /** @var int $tableCount */
        $tableCount = $result['table_count'] ?? 0;
        $this->info('Tables to export: '.$tableCount);

        /** @var array<int, array<string, mixed>> $tableDetails */
        $tableDetails = $result['table_details'] ?? [];
        if (! empty($tableDetails)) {
            $tableRows = array_map(function (array $t): array {
                $rows = $t['rows'] ?? 0;
                $rowsFloat = is_numeric($rows) ? (float) $rows : 0.0;

                return [
                    $t['name'] ?? '',
                    number_format($rowsFloat),
                    $t['size'] ?? '',
                    ($t['structure_only'] ?? false) ? 'Yes' : 'No',
                    ($t['is_view'] ?? false) ? 'Yes' : 'No',
                ];
            }, $tableDetails);

            $this->table(
                ['Table', 'Rows', 'Size', 'Structure Only', 'View'],
                $tableRows
            );
        }

        /** @var array<int, string> $anonymizedTables */
        $anonymizedTables = $result['anonymized_tables'] ?? [];
        if (! empty($anonymizedTables)) {
            $this->newLine();
            $this->info('Tables with anonymization: '.implode(', ', $anonymizedTables));
        }
    }

    protected function confirmExport(DbExportManager $manager, ExportConfig $config): bool
    {
        try {
            $estimate = $manager->estimate($config);

            $this->info('Export Estimate:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Tables', $estimate->tableCount],
                    ['Rows', number_format($estimate->rowCount)],
                    ['Estimated Size', $estimate->getHumanEstimatedSize()],
                    ['Compressed Size', $estimate->getHumanCompressedSize()],
                    ['Disk Space', $estimate->diskSpace->sufficient ? 'OK' : 'INSUFFICIENT'],
                ]
            );

            if (! $estimate->diskSpace->sufficient) {
                if ($estimate->diskSpace->warning !== null) {
                    $this->error($estimate->diskSpace->warning);
                }

                return false;
            }

            return $this->confirm('Proceed with export?', true);
        } catch (\Throwable $throwable) {
            $this->warn('Could not estimate size: '.$throwable->getMessage());

            return $this->confirm('Proceed anyway?', false);
        }
    }

    protected function executeExport(DbExportManager $manager, ExportConfig $config): int
    {
        $this->info('Starting export...');

        try {
            $result = $manager->export($config);

            if ($result->success) {
                $this->newLine();
                $this->info('Export completed successfully!');
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Path', $result->path],
                        ['Size', $result->getHumanFileSize()],
                        ['Duration', $result->getHumanDuration()],
                        ['Tables', $result->tableCount],
                        ['Compressed', $result->compressed ? 'Yes' : 'No'],
                    ]
                );

                return self::SUCCESS;
            }

            $this->error('Export failed: '.$result->error);

            return self::FAILURE;
        } catch (InsufficientDiskSpaceException $e) {
            $this->error('Insufficient disk space: '.$e->getMessage());

            return self::FAILURE;
        } catch (ExportException $e) {
            $this->error('Export error: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
