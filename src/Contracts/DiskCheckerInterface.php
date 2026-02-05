<?php

declare(strict_types=1);

namespace Xve\DbExport\Contracts;

use Xve\DbExport\DTOs\DiskSpaceResult;

interface DiskCheckerInterface
{
    /**
     * Check if there is sufficient disk space for the export.
     */
    public function check(string $path, int $estimatedSize): DiskSpaceResult;

    /**
     * Get available disk space at the given path.
     */
    public function getAvailableSpace(string $path): int;

    /**
     * Check if the path is writable.
     */
    public function isWritable(string $path): bool;
}
