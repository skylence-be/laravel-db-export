<?php

declare(strict_types=1);

namespace Dwb\DbExport\Config;

class AnonymizationConfig
{
    /**
     * @param  array<string, array<string, array<string, mixed>>>  $rules
     * @param  array<string, array<string, mixed>>  $globalRules
     */
    public function __construct(protected array $rules = [], protected array $globalRules = []) {}

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
     * @return array{tables: array<string, array<string, array<string, mixed>>>, global: array<string, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'tables' => $this->rules,
            'global' => $this->globalRules,
        ];
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $profileAnonymize
     * @param  array<string, array<string, mixed>>  $globalAnonymize
     */
    public static function fromConfig(array $profileAnonymize, array $globalAnonymize = []): self
    {
        return new self($profileAnonymize, $globalAnonymize);
    }
}
