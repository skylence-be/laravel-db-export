<?php

declare(strict_types=1);

namespace Xve\DbExport\Contracts;

use Xve\DbExport\Config\ExportConfig;
use Xve\DbExport\DTOs\ExportResult;

interface ExporterInterface
{
    /**
     * @param  array<int, \Xve\DbExport\DTOs\TableInfo>  $tables
     */
    public function export(ExportConfig $config, array $tables): ExportResult;
}
