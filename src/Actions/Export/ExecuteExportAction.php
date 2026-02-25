<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Export;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Xve\DbExport\Config\ExportConfig;
use Xve\DbExport\Contracts\ExporterInterface;
use Xve\DbExport\DTOs\ExportResult;
use Xve\DbExport\DTOs\TableInfo;
use Xve\DbExport\Exceptions\ExportException;

class ExecuteExportAction implements ExporterInterface
{
    protected ?ProgressBar $progressBar = null;

    public function __construct(
        protected BuildDumperAction $buildDumper,
        protected CompressExportAction $compress,
        protected WrapWithForeignKeysAction $wrapForeignKeys,
        protected ?ExportAnonymizedTableAction $exportAnonymized = null
    ) {}

    protected function createOutput(): OutputInterface
    {
        if (app()->runningInConsole()) {
            return new ConsoleOutput;
        }

        return new NullOutput;
    }

    protected function startProgress(int $totalSteps): void
    {
        $output = $this->createOutput();
        $this->progressBar = new ProgressBar($output, $totalSteps);
        $this->progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $this->progressBar->setMessage('Starting export...');
        $this->progressBar->start();
    }

    protected function advanceProgress(string $message): void
    {
        if ($this->progressBar instanceof ProgressBar) {
            $this->progressBar->setMessage($message);
            $this->progressBar->advance();
        }
    }

    protected function finishProgress(): void
    {
        if ($this->progressBar instanceof ProgressBar) {
            $this->progressBar->finish();
            $this->createOutput()->writeln('');
            $this->progressBar = null;
        }
    }

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

            $totalSteps = $this->calculateTotalSteps($config, $tables);
            $this->startProgress($totalSteps);

            $this->executeDump($config, $tables, $tempPath);

            if ($config->disableForeignKeys) {
                $this->advanceProgress('Wrapping with foreign key checks...');
                $this->wrapForeignKeys->execute($tempPath);
            }

            if ($config->compress) {
                $this->advanceProgress('Compressing export...');
                $this->compress->execute($tempPath, $outputPath);
                @unlink($tempPath);
            } else {
                rename($tempPath, $outputPath);
            }

            $this->finishProgress();

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
            $this->finishProgress();
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
            $this->advanceProgress('Dumping '.count($dataTables).' tables...');
            $dumper = $this->buildDumper->execute($config, $dataTables);
            $dumper->dumpToFile($outputPath);
        } else {
            file_put_contents($outputPath, "-- Database export\n");
        }

        // Export anonymized tables via PHP
        $anonymizer = $this->exportAnonymized;
        if ($anonymizedTables !== [] && $anonymizer instanceof ExportAnonymizedTableAction) {
            $handle = fopen($outputPath, 'a');
            if ($handle !== false) {
                fwrite($handle, "\n-- Anonymized tables\n");

                foreach ($anonymizedTables as $table) {
                    $this->advanceProgress('Anonymizing '.$table->name.'...');
                    $anonymizer->execute($table->name, $config, $handle);
                }

                fclose($handle);
            }
        }

        // Export structure-only tables
        $structureDumper = $this->buildDumper->buildStructureOnlyDumper($config, $tables);

        if ($structureDumper instanceof \Spatie\DbDumper\Databases\MySql) {
            $structureOnlyCount = count(array_filter($tables, fn (TableInfo $t): bool => $t->structureOnly));
            $this->advanceProgress('Exporting '.$structureOnlyCount.' structure-only tables...');

            $structureTempPath = $outputPath.'.structure';
            $structureDumper->dumpToFile($structureTempPath);

            $structureContent = file_get_contents($structureTempPath);
            file_put_contents($outputPath, "\n\n-- Structure-only tables\n".$structureContent, FILE_APPEND);
            @unlink($structureTempPath);
        }
    }

    /**
     * Calculate the total number of progress steps.
     *
     * @param  array<TableInfo>  $tables
     */
    protected function calculateTotalSteps(ExportConfig $config, array $tables): int
    {
        $anonymizedTableNames = array_keys($config->anonymize);
        $steps = 0;

        $dataTables = array_filter(
            $tables,
            fn (TableInfo $t): bool => ! $t->structureOnly && ! $t->isView && ! in_array($t->name, $anonymizedTableNames, true)
        );

        if ($dataTables !== []) {
            $steps++; // mysqldump
        }

        // One step per anonymized table
        $steps += count(array_filter(
            $tables,
            fn (TableInfo $t): bool => ! $t->structureOnly && ! $t->isView && in_array($t->name, $anonymizedTableNames, true)
        ));

        $hasStructureOnly = array_filter($tables, fn (TableInfo $t): bool => $t->structureOnly) !== [];
        if ($hasStructureOnly) {
            $steps++;
        }

        if ($config->disableForeignKeys) {
            $steps++;
        }

        if ($config->compress) {
            $steps++;
        }

        return $steps;
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
