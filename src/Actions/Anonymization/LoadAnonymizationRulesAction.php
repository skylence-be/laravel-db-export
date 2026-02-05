<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Anonymization;

use Xve\DbExport\Config\AnonymizationConfig;
use Xve\DbExport\Config\ExportConfig;

class LoadAnonymizationRulesAction
{
    /**
     * Load and merge anonymization rules from config and export settings.
     */
    public function execute(ExportConfig $exportConfig): AnonymizationConfig
    {
        /** @var array<string, array<string, mixed>> $globalRules */
        $globalRules = config('db-export.global_anonymization', []);
        /** @var array<string, array{column: string, domains: array<int, string>}> $preserveRows */
        $preserveRows = config('db-export.preserve_rows', []);

        $profileRules = $exportConfig->anonymize;

        return AnonymizationConfig::fromConfig($profileRules, $globalRules, $preserveRows);
    }

    /**
     * Load rules for a specific profile.
     */
    public function forProfile(string $profileName): AnonymizationConfig
    {
        /** @var array<string, array<string, mixed>> $profiles */
        $profiles = config('db-export.profiles', []);
        /** @var array<string, mixed> $profile */
        $profile = $profiles[$profileName] ?? [];

        /** @var array<string, array<string, mixed>> $globalRules */
        $globalRules = config('db-export.global_anonymization', []);
        /** @var array<string, array<string, array<string, mixed>>> $profileAnonymize */
        $profileAnonymize = $profile['anonymize'] ?? [];
        /** @var array<string, array{column: string, domains: array<int, string>}> $preserveRows */
        $preserveRows = config('db-export.preserve_rows', []);

        return AnonymizationConfig::fromConfig($profileAnonymize, $globalRules, $preserveRows);
    }

    /**
     * Merge multiple anonymization configs.
     */
    public function merge(AnonymizationConfig ...$configs): AnonymizationConfig
    {
        /** @var array<string, array<string, array<string, mixed>>> $mergedTables */
        $mergedTables = [];
        /** @var array<string, array<string, mixed>> $mergedGlobal */
        $mergedGlobal = [];
        /** @var array<string, array{column: string, domains: array<int, string>}> $mergedPreserveRows */
        $mergedPreserveRows = [];

        foreach ($configs as $config) {
            $data = $config->toArray();

            /** @var array<string, array<string, array<string, mixed>>> $tables */
            $tables = $data['tables'];
            foreach ($tables as $table => $columns) {
                if (! isset($mergedTables[$table])) {
                    $mergedTables[$table] = [];
                }

                $mergedTables[$table] = array_merge($mergedTables[$table], $columns);
            }

            /** @var array<string, array<string, mixed>> $global */
            $global = $data['global'];
            $mergedGlobal = array_merge($mergedGlobal, $global);

            // Merge preserve rows from all configs
            /** @var array<string, array{column: string, domains: array<int, string>}> $preserveRows */
            $preserveRows = $data['preserve_rows'] ?? [];
            foreach ($preserveRows as $table => $tableConfig) {
                if (! isset($mergedPreserveRows[$table])) {
                    $mergedPreserveRows[$table] = $tableConfig;
                } else {
                    // Merge domains for same table
                    $mergedPreserveRows[$table]['domains'] = array_values(array_unique(
                        array_merge($mergedPreserveRows[$table]['domains'], $tableConfig['domains'])
                    ));
                }
            }
        }

        return new AnonymizationConfig($mergedTables, $mergedGlobal, $mergedPreserveRows);
    }

    /**
     * Validate anonymization rules.
     *
     * @return array<int, string>
     */
    public function validate(AnonymizationConfig $config): array
    {
        /** @var array<int, string> $errors */
        $errors = [];
        $validStrategies = ['faker', 'mask', 'null', 'hash', 'fixed'];

        $data = $config->toArray();
        /** @var array<string, array<string, array<string, mixed>>> $tables */
        $tables = $data['tables'];

        foreach ($tables as $table => $columns) {
            foreach ($columns as $column => $rule) {
                /** @var string|null $strategy */
                $strategy = $rule['strategy'] ?? null;

                if ($strategy === null) {
                    $errors[] = sprintf('Missing strategy for %s.%s', $table, $column);

                    continue;
                }

                if (! in_array($strategy, $validStrategies, true)) {
                    $errors[] = sprintf("Invalid strategy '%s' for %s.%s", $strategy, $table, $column);
                }

                if ($strategy === 'faker' && empty($rule['method'])) {
                    $errors[] = sprintf("Faker strategy requires 'method' for %s.%s", $table, $column);
                }
            }
        }

        return $errors;
    }
}
