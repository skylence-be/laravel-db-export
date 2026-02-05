<?php

declare(strict_types=1);

namespace Xve\DbExport\Commands;

use Illuminate\Console\Command;
use Xve\DbExport\Actions\Estimation\GenerateBreakdownAction;
use Xve\DbExport\Config\ExportConfig;
use Xve\DbExport\DbExportManager;

class EstimateCommand extends Command
{
    protected $signature = 'db:export:estimate
        {--profile= : The export profile to use}
        {--connection= : The database connection to use}
        {--exclude=* : Tables to exclude (can be used multiple times)}
        {--structure-only=* : Tables to export structure only (can be used multiple times)}
        {--include-only=* : Only include these tables (can be used multiple times)}
        {--no-views : Exclude database views}
        {--no-compress : Show uncompressed size estimate}
        {--detailed : Show detailed breakdown}
        {--text : Output as text report}';

    protected $description = 'Estimate the size of a database export';

    public function handle(DbExportManager $manager, GenerateBreakdownAction $breakdownAction): int
    {
        $config = $this->buildConfig();

        try {
            $estimate = $manager->estimate($config);

            if ($this->option('text')) {
                $this->line($breakdownAction->generateTextReport($estimate));

                return self::SUCCESS;
            }

            $this->displaySummary($estimate);

            if ($this->option('detailed')) {
                $breakdown = $breakdownAction->execute($estimate);
                $this->displayDetailedBreakdown($breakdown);
            }

            return self::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->error('Error: '.$throwable->getMessage());

            return self::FAILURE;
        }
    }

    protected function buildConfig(): ExportConfig
    {
        /** @var string|null $connection */
        $connection = $this->option('connection');
        /** @var string|null $profile */
        $profile = $this->option('profile');
        /** @var array<int, string> $exclude */
        $exclude = $this->option('exclude') ?: [];
        /** @var array<int, string> $structureOnly */
        $structureOnly = $this->option('structure-only') ?: [];
        /** @var array<int, string>|null $includeOnly */
        $includeOnly = $this->option('include-only');

        return new ExportConfig(
            connection: $connection,
            profile: $profile,
            compress: ! $this->option('no-compress'),
            exclude: $exclude,
            structureOnly: $structureOnly,
            includeOnly: empty($includeOnly) ? null : $includeOnly,
            includeViews: ! $this->option('no-views'),
        );
    }

    /**
     * @param  \Xve\DbExport\DTOs\SizeEstimate  $estimate
     */
    protected function displaySummary($estimate): void
    {
        $this->info('Export Size Estimate');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Tables', $estimate->tableCount],
                ['Total Rows', number_format($estimate->rowCount)],
                ['Database Size', $estimate->getHumanTotalSize()],
                ['Estimated Export Size', $estimate->getHumanEstimatedSize()],
                ['Estimated Compressed Size', $estimate->getHumanCompressedSize()],
                ['Compression Ratio', ($estimate->getCompressionRatio() * 100).'%'],
            ]
        );

        $this->newLine();
        $this->info('Disk Space Check');

        $diskStatus = $estimate->diskSpace->sufficient ? '<fg=green>OK</>' : '<fg=red>INSUFFICIENT</>';

        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $diskStatus],
                ['Available', $estimate->diskSpace->getAvailableMB().' MB'],
                ['Required', $estimate->diskSpace->getRequiredMB().' MB'],
            ]
        );

        if (! $estimate->diskSpace->sufficient && $estimate->diskSpace->warning !== null) {
            $this->error($estimate->diskSpace->warning);
        }
    }

    /**
     * @param  array<string, mixed>  $breakdown
     */
    protected function displayDetailedBreakdown(array $breakdown): void
    {
        $this->newLine();
        $this->info('Top 10 Largest Tables');

        /** @var array<int, array<string, mixed>> $topTables */
        $topTables = $breakdown['top_tables'] ?? [];
        if (! empty($topTables)) {
            $rows = array_map(fn (array $t): array => [
                $t['name'] ?? '',
                $t['rows'] ?? '',
                $t['size'] ?? '',
                ($t['structure_only'] ?? false) ? 'Yes' : 'No',
            ], $topTables);

            $this->table(
                ['Table', 'Rows', 'Size', 'Structure Only'],
                $rows
            );
        }

        $this->newLine();
        $this->info('Tables by Size Category');

        /** @var array<string, array<string, mixed>> $byCategory */
        $byCategory = $breakdown['by_category'] ?? [];
        foreach ($byCategory as $data) {
            /** @var string $label */
            $label = $data['label'] ?? '';
            /** @var int|string $count */
            $count = $data['count'] ?? 0;
            $this->line(sprintf('  %s: %s tables', $label, $count));
        }

        /** @var array<int, array<string, mixed>> $structureOnly */
        $structureOnly = $breakdown['structure_only'] ?? [];
        if (! empty($structureOnly)) {
            $this->newLine();
            $this->info('Structure-Only Tables');

            $rows = array_map(fn (array $t): array => [
                $t['name'] ?? '',
                $t['rows_skipped'] ?? '',
                $t['size_saved'] ?? '',
            ], $structureOnly);

            $this->table(
                ['Table', 'Rows Skipped', 'Size Saved'],
                $rows
            );
        }

        /** @var array<int, array{type: string, message: string}> $recommendations */
        $recommendations = $breakdown['recommendations'] ?? [];
        if (! empty($recommendations)) {
            $this->newLine();
            $this->info('Recommendations');

            foreach ($recommendations as $rec) {
                $type = $rec['type'];
                $message = $rec['message'];
                $color = match ($type) {
                    'error' => 'red',
                    'warning' => 'yellow',
                    default => 'blue',
                };
                $this->line(sprintf('  <fg=%s>[%s]</> %s', $color, $type, $message));
            }
        }
    }
}
