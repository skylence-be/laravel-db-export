<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Anonymization;

use Xve\DbExport\Actions\Anonymization\Strategies\FakerStrategy;
use Xve\DbExport\Actions\Anonymization\Strategies\FixedStrategy;
use Xve\DbExport\Actions\Anonymization\Strategies\HashStrategy;
use Xve\DbExport\Actions\Anonymization\Strategies\MaskStrategy;
use Xve\DbExport\Actions\Anonymization\Strategies\NullStrategy;
use Xve\DbExport\Config\AnonymizationConfig;
use Xve\DbExport\Contracts\AnonymizationStrategyInterface;
use Xve\DbExport\Contracts\AnonymizerInterface;
use Xve\DbExport\Exceptions\AnonymizationException;

class AnonymizeTableAction implements AnonymizerInterface
{
    /** @var array<string, AnonymizationStrategyInterface> */
    protected array $strategies = [];

    public function __construct(
        protected LoadAnonymizationRulesAction $loadRules
    ) {
        $this->registerDefaultStrategies();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function anonymize(string $table, array $rows, AnonymizationConfig $config): array
    {
        if (! $this->requiresAnonymization($table, $config)) {
            return $rows;
        }

        $rules = $config->getRulesForTable($table);

        return array_map(
            fn (array $row): array => $this->anonymizeRow($table, $row, $rules, $config),
            $rows
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function generateInsertStatement(string $table, array $rows, AnonymizationConfig $config): string
    {
        if ($rows === []) {
            return '';
        }

        $anonymizedRows = $this->anonymize($table, $rows, $config);
        /** @var array<string, mixed> $firstRow */
        $firstRow = $anonymizedRows[0];
        $columns = array_keys($firstRow);

        $columnList = implode('`, `', $columns);
        $values = [];

        foreach ($anonymizedRows as $row) {
            $rowValues = array_map(
                $this->escapeValue(...),
                $row
            );
            $values[] = '('.implode(', ', $rowValues).')';
        }

        return "INSERT INTO `{$table}` (`{$columnList}`) VALUES\n".implode(",\n", $values).';';
    }

    public function requiresAnonymization(string $table, AnonymizationConfig $config): bool
    {
        return $config->hasRulesForTable($table);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $rules
     * @return array<string, mixed>
     */
    protected function anonymizeRow(string $table, array $row, array $rules, ?AnonymizationConfig $config = null): array
    {
        // Check if this specific row should be preserved (e.g., admin emails)
        if ($config instanceof \Xve\DbExport\Config\AnonymizationConfig && $config->shouldPreserveRow($table, $row)) {
            return $row;
        }

        foreach ($rules as $column => $rule) {
            if (! array_key_exists($column, $row)) {
                continue;
            }

            $row[$column] = $this->anonymizeValue($row[$column], $rule);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function anonymizeValue(mixed $value, array $rule): mixed
    {
        /** @var string $strategyName */
        $strategyName = $rule['strategy'] ?? 'null';

        if (! isset($this->strategies[$strategyName])) {
            throw AnonymizationException::unknownStrategy($strategyName);
        }

        return $this->strategies[$strategyName]->anonymize($value, $rule);
    }

    protected function escapeValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $stringValue = is_string($value) ? $value : (json_encode($value) ?: '');

        $escaped = str_replace(
            ['\\', "\x00", "\n", "\r", "'", '"', "\x1a"],
            ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'],
            $stringValue
        );

        return sprintf("'%s'", $escaped);
    }

    protected function registerDefaultStrategies(): void
    {
        $this->registerStrategy(new FakerStrategy);
        $this->registerStrategy(new MaskStrategy);
        $this->registerStrategy(new NullStrategy);
        $this->registerStrategy(new HashStrategy);
        $this->registerStrategy(new FixedStrategy);
    }

    public function registerStrategy(AnonymizationStrategyInterface $strategy): self
    {
        $this->strategies[$strategy->getName()] = $strategy;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getAvailableStrategies(): array
    {
        return array_keys($this->strategies);
    }
}
