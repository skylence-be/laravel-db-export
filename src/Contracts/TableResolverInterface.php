<?php

declare(strict_types=1);

namespace Xve\DbExport\Contracts;

use Xve\DbExport\Config\ExportConfig;
use Xve\DbExport\DTOs\TableInfo;

interface TableResolverInterface
{
    /**
     * Resolve tables based on export configuration.
     *
     * @return array<TableInfo>
     */
    public function resolve(ExportConfig $config): array;

    /**
     * Get all tables from the database.
     *
     * @return array<string>
     */
    public function getAllTables(?string $connection = null): array;

    /**
     * Get all views from the database.
     *
     * @return array<string>
     */
    public function getAllViews(?string $connection = null): array;
}
