<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Tables;

class ExpandWildcardsAction
{
    /**
     * Expand wildcard patterns against a list of tables.
     *
     * @param  array<string>  $patterns  Patterns like ['telescope_*', '*_logs', 'users']
     * @param  array<string>  $allTables  All available table names
     * @return array<string> Matched table names
     */
    public function execute(array $patterns, array $allTables): array
    {
        $matched = [];

        foreach ($patterns as $pattern) {
            if (! str_contains($pattern, '*')) {
                if (in_array($pattern, $allTables, true)) {
                    $matched[] = $pattern;
                }

                continue;
            }

            $regex = $this->patternToRegex($pattern);

            foreach ($allTables as $table) {
                if (preg_match($regex, $table)) {
                    $matched[] = $table;
                }
            }
        }

        return array_unique($matched);
    }

    /**
     * Check if a table matches any of the patterns.
     *
     * @param  array<int, string>  $patterns
     */
    public function matches(string $table, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $table) {
                return true;
            }

            if (str_contains($pattern, '*')) {
                $regex = $this->patternToRegex($pattern);
                if (preg_match($regex, $table)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Convert a glob-like pattern to a regex.
     */
    protected function patternToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');
        $regex = str_replace('\*', '.*', $escaped);

        return '/^'.$regex.'$/i';
    }
}
