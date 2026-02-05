<?php

declare(strict_types=1);

namespace Dwb\DbExport\Contracts;

use Dwb\DbExport\Config\ExportConfig;
use Dwb\DbExport\DTOs\ExportResult;

interface ExporterInterface
{
    /**
     * @param  array<int, \Dwb\DbExport\DTOs\TableInfo>  $tables
     */
    public function export(ExportConfig $config, array $tables): ExportResult;
}
