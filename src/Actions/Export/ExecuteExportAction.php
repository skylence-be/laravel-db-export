<?php

declare(strict_types=1);

namespace Dwb\DbExport\Actions\Export;

use Dwb\DbExport\Config\ExportConfig;
use Dwb\DbExport\Contracts\ExporterInterface;
use Dwb\DbExport\DTOs\ExportResult;
use Dwb\DbExport\DTOs\TableInfo;
use Dwb\DbExport\Exceptions\ExportException;

class ExecuteExportAction implements ExporterInterface
{
    public function __construct(
        protected BuildDumperAction $buildDumper,
        protected CompressExportAction $compress,
        protected WrapWithForeignKeysAction $wrapForeignKeys,
        protected ?ExportAnonymizedTableAction $exportAnonymized = null
    ) {}

    /**
     * @param  array<TableInfo>  $tables
     */
    public function export(ExportConfig $config, array $tables): ExportResult
    {
        $startTime = microtime(true);

        try {
            $this->ensureOutputDirectory($config->getOutputPath());
            $this->cleanupOldExports($config->getOutputPath());

            $outputPath = $config->getFullPath();
            $tempPath = $this->getTempPath($outputPath);

            $this->executeDump($config, $tables, $tempPath);

            if ($config->disableForeignKeys) {
                $this->wrapForeignKeys->execute($tempPath);
            }

            if ($config->compress) {
                $this->compress->execute($tempPath, $outputPath);
                @unlink($tempPath);
            } else {
                rename($tempPath, $outputPath);
            }

            $duration = microtime(true) - $startTime;
            $fileSize = file_exists($outputPath) ? (int) filesize($outputPath) : 0;

            $tableNames = array_map(fn (TableInfo $t): string => $t->name, $tables);
            $anonymizedTables = array_keys($config->anonymize);

            return ExportResult::success(
                path: $outputPath,
                fileSize: $fileSize,
                duration: $duration,
                tables: $tableNames,
                anonymizedTables: $anonymizedTables,
                compressed: $config->compress
            );
        } catch (\Throwable $throwable) {
            $duration = microtime(true) - $startTime;

            throw ExportException::failed($throwable->getMessage(), $throwable, $duration);
        }
    }

    /**
     * Execute the database dump.
     *
     * @param  array<TableInfo>  $tables
     */
    protected function executeDump(ExportConfig $config, array $tables, string $outputPath): void
    {
        $anonymizedTableNames = array_keys($config->anonymize);

        // Separate tables into anonymized and non-anonymized
        $dataTables = array_filter(
            $tables,
            fn (TableInfo $t): bool => ! $t->structureOnly && ! $t->isView && ! in_array($t->name, $anonymizedTableNames, true)
        );

        $anonymizedTables = array_filter(
            $tables,
            fn (TableInfo $t): bool => ! $t->structureOnly && ! $t->isView && in_array($t->name, $anonymizedTableNames, true)
        );

        // Export non-anonymized tables with mysqldump
        if ($dataTables !== []) {
            $dumper = $this->buildDumper->execute($config, $dataTables);
            $dumper->dumpToFile($outputPath);
        } else {
            file_put_contents($outputPath, "-- Database export\n");
        }

        // Export anonymized tables via PHP
        if ($anonymizedTables !== [] && $this->exportAnonymized !== null) {
            $handle = fopen($outputPath, 'a');
            if ($handle !== false) {
                fwrite($handle, "\n-- Anonymized tables\n");

                foreach ($anonymizedTables as $table) {
                    $this->exportAnonymized->execute($table->name, $config, $handle);
                }

                fclose($handle);
            }
        }

        // Export structure-only tables
        $structureDumper = $this->buildDumper->buildStructureOnlyDumper($config, $tables);

        if ($structureDumper instanceof \Spatie\DbDumper\Databases\MySql) {
            $structureTempPath = $outputPath.'.structure';
            $structureDumper->dumpToFile($structureTempPath);

            $structureContent = file_get_contents($structureTempPath);
            file_put_contents($outputPath, "\n\n-- Structure-only tables\n".$structureContent, FILE_APPEND);
            @unlink($structureTempPath);
        }
    }

    protected function ensureOutputDirectory(string $path): void
    {
        if (! is_dir($path) && (! mkdir($path, 0755, true) && ! is_dir($path))) {
            throw ExportException::directoryNotCreatable($path);
        }

        if (! is_writable($path)) {
            throw ExportException::directoryNotWritable($path);
        }
    }

    protected function getTempPath(string $outputPath): string
    {
        $basePath = str_ends_with($outputPath, '.gz')
            ? substr($outputPath, 0, -3)
            : $outputPath;

        return $basePath.'.tmp';
    }

    /**
     * Clean up old export files before creating a new one.
     */
    protected function cleanupOldExports(string $directory): void
    {
        /** @var bool $cleanupEnabled */
        $cleanupEnabled = config('db-export.cleanup.enabled', false);

        if (! $cleanupEnabled) {
            return;
        }

        /** @var int $keepRecent */
        $keepRecent = config('db-export.cleanup.keep_recent', 0);

        $files = glob($directory.'/*.sql*') ?: [];

        if ($keepRecent > 0 && count($files) <= $keepRecent) {
            return;
        }

        // Sort by modification time, newest first
        usort($files, fn (string $a, string $b): int => (int) filemtime($b) - (int) filemtime($a));

        // Remove files beyond the keep limit
        $filesToRemove = $keepRecent > 0 ? array_slice($files, $keepRecent) : $files;

        foreach ($filesToRemove as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
