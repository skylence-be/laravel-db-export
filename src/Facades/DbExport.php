<?php

declare(strict_types=1);

namespace Xve\DbExport\Facades;

use Illuminate\Support\Facades\Facade;
use Xve\DbExport\Config\ExportConfig;
use Xve\DbExport\DTOs\ExportResult;
use Xve\DbExport\DTOs\SizeEstimate;

/**
 * @method static ExportResult export(?ExportConfig $config = null)
 * @method static SizeEstimate estimate(?ExportConfig $config = null)
 * @method static array<string, mixed> dryRun(?ExportConfig $config = null)
 * @method static array<string, array<string, mixed>> getProfiles()
 * @method static array<int, string> getProfileNames()
 * @method static \Xve\DbExport\DbExportManager forConnection(string $connection)
 * @method static \Xve\DbExport\DbExportManager withProfile(string $profile)
 *
 * @see \Xve\DbExport\DbExportManager
 */
class DbExport extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'db-export';
    }
}
