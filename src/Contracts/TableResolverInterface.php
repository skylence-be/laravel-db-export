<?php

declare(strict_types=1);

namespace Dwb\DbExport\Contracts;

use Dwb\DbExport\Config\ExportConfig;
use Dwb\DbExport\DTOs\TableInfo;

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
