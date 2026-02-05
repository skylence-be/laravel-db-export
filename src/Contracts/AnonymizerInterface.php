<?php

declare(strict_types=1);

namespace Xve\DbExport\Contracts;

use Xve\DbExport\Config\AnonymizationConfig;

interface AnonymizerInterface
{
    /**
     * Anonymize data for a table based on configuration.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function anonymize(string $table, array $rows, AnonymizationConfig $config): array;

    /**
     * Generate an anonymized INSERT statement.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function generateInsertStatement(string $table, array $rows, AnonymizationConfig $config): string;

    /**
     * Check if a table requires anonymization.
     */
    public function requiresAnonymization(string $table, AnonymizationConfig $config): bool;
}
