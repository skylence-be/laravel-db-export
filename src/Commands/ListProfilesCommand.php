<?php

declare(strict_types=1);

namespace Xve\DbExport\Commands;

use Illuminate\Console\Command;
use Xve\DbExport\Config\ProfileManager;

class ListProfilesCommand extends Command
{
    protected $signature = 'db:export:list-profiles
        {--detailed : Show detailed profile configuration}';

    protected $description = 'List all available export profiles';

    public function handle(ProfileManager $profileManager): int
    {
        $profiles = $profileManager->getWithDescriptions();

        if ($profiles === []) {
            $this->warn('No profiles configured.');

            return self::SUCCESS;
        }

        $this->info('Available Export Profiles');
        $this->newLine();

        if ($this->option('detailed')) {
            $this->displayDetailedProfiles($profileManager);
        } else {
            $this->displayProfileSummary($profiles);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<string, mixed>>  $profiles
     */
    protected function displayProfileSummary(array $profiles): void
    {
        $rows = [];

        foreach ($profiles as $name => $info) {
            $features = [];

            /** @var int $excludeCount */
            $excludeCount = $info['exclude_count'];
            if ($excludeCount > 0) {
                $features[] = $excludeCount.' exclusions';
            }

            /** @var int $structureOnlyCount */
            $structureOnlyCount = $info['structure_only_count'];
            if ($structureOnlyCount > 0) {
                $features[] = $structureOnlyCount.' structure-only';
            }

            if ($info['has_anonymization']) {
                $features[] = 'anonymization';
            }

            $rows[] = [
                $name,
                $info['description'],
                implode(', ', $features) ?: '-',
            ];
        }

        $this->table(
            ['Profile', 'Description', 'Features'],
            $rows
        );

        $this->newLine();
        $this->line('Use <info>--detailed</info> to see full configuration for each profile.');
    }

    protected function displayDetailedProfiles(ProfileManager $profileManager): void
    {
        foreach ($profileManager->all() as $name => $config) {
            $this->line(sprintf('<fg=cyan;options=bold>%s</>', $name));
            /** @var string $description */
            $description = $config['description'] ?? 'No description';
            $this->line('  '.$description);
            $this->newLine();

            /** @var array<int, string> $exclude */
            $exclude = $config['exclude'] ?? [];
            if (! empty($exclude)) {
                $this->line('  <fg=yellow>Excluded Tables:</>');
                foreach ($exclude as $pattern) {
                    $this->line('    - '.$pattern);
                }

                $this->newLine();
            }

            /** @var array<int, string> $structureOnly */
            $structureOnly = $config['structure_only'] ?? [];
            if (! empty($structureOnly)) {
                $this->line('  <fg=yellow>Structure Only:</>');
                foreach ($structureOnly as $pattern) {
                    $this->line('    - '.$pattern);
                }

                $this->newLine();
            }

            /** @var array<int, string>|null $includeOnly */
            $includeOnly = $config['include_only'] ?? null;
            if ($includeOnly !== null) {
                $this->line('  <fg=yellow>Include Only:</>');
                foreach ($includeOnly as $pattern) {
                    $this->line('    - '.$pattern);
                }

                $this->newLine();
            }

            /** @var array<string, array<string, array<string, mixed>>> $anonymize */
            $anonymize = $config['anonymize'] ?? [];
            if (! empty($anonymize)) {
                $this->line('  <fg=yellow>Anonymization:</>');
                foreach ($anonymize as $table => $columns) {
                    $this->line('    '.$table.':');
                    /** @var array<string, array<string, mixed>> $columns */
                    foreach ($columns as $column => $rule) {
                        /** @var string $strategy */
                        $strategy = $rule['strategy'] ?? 'unknown';
                        /** @var string|null $method */
                        $method = $rule['method'] ?? null;
                        $methodStr = $method !== null ? ' ('.$method.')' : '';
                        $this->line('      - '.$column.': '.$strategy.$methodStr);
                    }
                }

                $this->newLine();
            }

            $this->line(str_repeat('-', 50));
            $this->newLine();
        }
    }
}
