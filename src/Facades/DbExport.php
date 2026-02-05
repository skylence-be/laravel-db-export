<?php

declare(strict_types=1);

namespace Dwb\DbExport\Facades;

use Dwb\DbExport\Config\ExportConfig;
use Dwb\DbExport\DTOs\ExportResult;
use Dwb\DbExport\DTOs\SizeEstimate;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ExportResult export(?ExportConfig $config = null)
 * @method static SizeEstimate estimate(?ExportConfig $config = null)
 * @method static array<string, mixed> dryRun(?ExportConfig $config = null)
 * @method static array<string, array<string, mixed>> getProfiles()
 * @method static array<int, string> getProfileNames()
 * @method static \Dwb\DbExport\DbExportManager forConnection(string $connection)
 * @method static \Dwb\DbExport\DbExportManager withProfile(string $profile)
 *
 * @see \Dwb\DbExport\DbExportManager
 */
class DbExport extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'db-export';
    }
}
