<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Tables;

use Illuminate\Database\DatabaseManager;

class ExportViewsAction
{
    public function __construct(
        protected DatabaseManager $db
    ) {}

    /**
     * Generate CREATE VIEW statements for all views in the database.
     *
     * @param  array<int, string>  $viewNames
     * @return array<string, string> View name => CREATE statement
     */
    public function execute(?string $connection = null, array $viewNames = []): array
    {
        $views = [];
        $viewList = $viewNames === [] ? $this->getAllViewNames($connection) : $viewNames;

        foreach ($viewList as $viewName) {
            $definition = $this->getViewDefinition($viewName, $connection);
            if ($definition !== null) {
                $views[$viewName] = $definition;
            }
        }

        return $views;
    }

    /**
     * Get the CREATE VIEW statement for a specific view.
     */
    public function getViewDefinition(string $viewName, ?string $connection = null): ?string
    {
        $conn = $this->db->connection($connection);

        /** @var object|null $result */
        $result = $conn->selectOne(sprintf('SHOW CREATE VIEW `%s`', $viewName));

        if (! $result) {
            return null;
        }

        /** @var string|null $createStatement */
        $createStatement = $result->{'Create View'} ?? null;

        if ($createStatement === null) {
            return null;
        }

        return $this->processViewDefinition($createStatement, $viewName);
    }

    /**
     * Get all view names from the database.
     *
     * @return array<string>
     */
    public function getAllViewNames(?string $connection = null): array
    {
        $conn = $this->db->connection($connection);
        $database = $conn->getDatabaseName();

        $results = $conn->select(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'VIEW'",
            [$database]
        );

        return array_map(fn ($row) => $row->TABLE_NAME, $results);
    }

    /**
     * Process a view definition based on configuration.
     */
    protected function processViewDefinition(string $statement, string $viewName): string
    {
        $definerHandling = config('db-export.views.definer', 'strip');

        $statement = match ($definerHandling) {
            'strip' => $this->stripDefiner($statement),
            'replace' => $this->replaceDefiner($statement),
            default => $statement,
        };

        return "DROP VIEW IF EXISTS `{$viewName}`;\n".$statement;
    }

    /**
     * Strip the DEFINER clause from a view definition.
     */
    protected function stripDefiner(string $statement): string
    {
        /** @var string|null $result */
        $result = preg_replace(
            '/DEFINER\s*=\s*`[^`]+`@`[^`]+`\s*/i',
            '',
            $statement
        );

        return $result ?? $statement;
    }

    /**
     * Replace the DEFINER clause with the configured replacement.
     */
    protected function replaceDefiner(string $statement): string
    {
        /** @var string $replacement */
        $replacement = config('db-export.views.replace_with', 'CURRENT_USER');

        /** @var string|null $result */
        $result = preg_replace(
            '/DEFINER\s*=\s*`[^`]+`@`[^`]+`/i',
            'DEFINER = '.$replacement,
            $statement
        );

        return $result ?? $statement;
    }

    /**
     * Generate DROP VIEW IF EXISTS statements.
     *
     * @param  array<int, string>  $viewNames
     * @return array<int, string>
     */
    public function generateDropStatements(array $viewNames): array
    {
        return array_map(
            fn (string $name): string => sprintf('DROP VIEW IF EXISTS `%s`;', $name),
            $viewNames
        );
    }
}
