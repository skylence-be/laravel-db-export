<?php

declare(strict_types=1);

namespace Xve\DbExport\Config;

class AnonymizationConfig
{
    /**
     * @param  array<string, array<string, array<string, mixed>>>  $rules
     * @param  array<string, array<string, mixed>>  $globalRules
     * @param  array<string, array{column: string, domains: array<int, string>}>  $preserveRows
     */
    public function __construct(
        protected array $rules = [],
        protected array $globalRules = [],
        protected array $preserveRows = []
    ) {}

    public function hasRulesForTable(string $table): bool
    {
        return isset($this->rules[$table]) || $this->globalRules !== [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getRulesForTable(string $table): array
    {
        $tableRules = $this->rules[$table] ?? [];

        return array_merge($this->globalRules, $tableRules);
    }

    /**
     * @return array<int, string>
     */
    public function getTablesWithRules(): array
    {
        return array_keys($this->rules);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function addTableRule(string $table, string $column, array $config): void
    {
        if (! isset($this->rules[$table])) {
            $this->rules[$table] = [];
        }

        $this->rules[$table][$column] = $config;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function addGlobalRule(string $columnPattern, array $config): void
    {
        $this->globalRules[$columnPattern] = $config;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRuleForColumn(string $table, string $column): ?array
    {
        if (isset($this->rules[$table][$column])) {
            return $this->rules[$table][$column];
        }

        foreach ($this->globalRules as $pattern => $config) {
            if ($this->matchesPattern($column, $pattern)) {
                return $config;
            }
        }

        return null;
    }

    protected function matchesPattern(string $column, string $pattern): bool
    {
        if ($column === $pattern) {
            return true;
        }

        if (str_contains($pattern, '*')) {
            $regex = '/^'.str_replace(['*', '/'], ['.*', '\/'], $pattern).'$/i';

            return (bool) preg_match($regex, $column);
        }

        return false;
    }

    /**
     * Check if a row should be preserved (not anonymized) based on domain.
     *
     * @param  array<string, mixed>  $row
     */
    public function shouldPreserveRow(string $table, array $row): bool
    {
        if (! isset($this->preserveRows[$table])) {
            return false;
        }

        $config = $this->preserveRows[$table];
        $column = $config['column'] ?? 'email';
        $domains = $config['domains'] ?? [];

        if ($domains === []) {
            return false;
        }

        if (! isset($row[$column])) {
            return false;
        }

        $value = $row[$column];
        if (! is_string($value) || ! str_contains($value, '@')) {
            return false;
        }

        $domain = strtolower(substr($value, (int) strrpos($value, '@') + 1));

        foreach ($domains as $preserveDomain) {
            if (strtolower($preserveDomain) === $domain) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array{column: string, domains: array<int, string>}>
     */
    public function getPreserveRows(): array
    {
        return $this->preserveRows;
    }

    /**
     * @return array{tables: array<string, array<string, array<string, mixed>>>, global: array<string, array<string, mixed>>, preserve_rows: array<string, array{column: string, domains: array<int, string>}>}
     */
    public function toArray(): array
    {
        return [
            'tables' => $this->rules,
            'global' => $this->globalRules,
            'preserve_rows' => $this->preserveRows,
        ];
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $profileAnonymize
     * @param  array<string, array<string, mixed>>  $globalAnonymize
     * @param  array<string, array{column: string, domains: array<int, string>}>  $preserveRows
     */
    public static function fromConfig(
        array $profileAnonymize,
        array $globalAnonymize = [],
        array $preserveRows = []
    ): self {
        return new self($profileAnonymize, $globalAnonymize, $preserveRows);
    }
}
