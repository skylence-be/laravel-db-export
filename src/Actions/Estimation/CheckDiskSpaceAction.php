<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Estimation;

use Xve\DbExport\Contracts\DiskCheckerInterface;
use Xve\DbExport\DTOs\DiskSpaceResult;

class CheckDiskSpaceAction implements DiskCheckerInterface
{
    protected float $safetyMargin;

    protected int $minimumFreeMb;

    protected bool $enabled;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        /** @var bool $enabled */
        $enabled = $config['enabled'] ?? true;
        /** @var float $safetyMargin */
        $safetyMargin = $config['safety_margin'] ?? 1.5;
        /** @var int $minimumFreeMb */
        $minimumFreeMb = $config['minimum_free_mb'] ?? 100;

        $this->enabled = (bool) $enabled;
        $this->safetyMargin = (float) $safetyMargin;
        $this->minimumFreeMb = (int) $minimumFreeMb;
    }

    public function check(string $path, int $estimatedSize): DiskSpaceResult
    {
        if (! $this->enabled) {
            return DiskSpaceResult::sufficient(
                availableBytes: PHP_INT_MAX,
                requiredBytes: $estimatedSize,
                estimatedSize: $estimatedSize,
                margin: $this->safetyMargin
            );
        }

        $directory = $this->resolveDirectory($path);
        $availableBytes = $this->getAvailableSpace($directory);
        $requiredBytes = (int) ceil($estimatedSize * $this->safetyMargin);
        $minimumBytes = $this->minimumFreeMb * 1024 * 1024;

        $effectiveRequired = max($requiredBytes, $minimumBytes);

        if ($availableBytes < $effectiveRequired) {
            $shortfall = $effectiveRequired - $availableBytes;

            return DiskSpaceResult::insufficient(
                availableBytes: $availableBytes,
                requiredBytes: $effectiveRequired,
                estimatedSize: $estimatedSize,
                margin: $this->safetyMargin,
                warning: sprintf(
                    'Insufficient disk space. Need %s MB but only %s MB available (shortfall: %s MB)',
                    round($effectiveRequired / 1024 / 1024, 2),
                    round($availableBytes / 1024 / 1024, 2),
                    round($shortfall / 1024 / 1024, 2)
                )
            );
        }

        return DiskSpaceResult::sufficient(
            availableBytes: $availableBytes,
            requiredBytes: $effectiveRequired,
            estimatedSize: $estimatedSize,
            margin: $this->safetyMargin
        );
    }

    public function getAvailableSpace(string $path): int
    {
        $directory = $this->resolveDirectory($path);

        if (! is_dir($directory)) {
            $parent = dirname($directory);
            if (is_dir($parent)) {
                $directory = $parent;
            } else {
                return 0;
            }
        }

        $freeSpace = disk_free_space($directory);

        return $freeSpace !== false ? (int) $freeSpace : 0;
    }

    public function isWritable(string $path): bool
    {
        $directory = $this->resolveDirectory($path);

        if (is_dir($directory)) {
            return is_writable($directory);
        }

        $parent = dirname($directory);

        return is_dir($parent) && is_writable($parent);
    }

    protected function resolveDirectory(string $path): string
    {
        if (is_dir($path)) {
            return $path;
        }

        if (pathinfo($path, PATHINFO_EXTENSION) !== '') {
            return dirname($path);
        }

        return $path;
    }

    public function setSafetyMargin(float $margin): self
    {
        $this->safetyMargin = $margin;

        return $this;
    }

    public function setMinimumFreeMb(int $mb): self
    {
        $this->minimumFreeMb = $mb;

        return $this;
    }

    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    public function enable(): self
    {
        $this->enabled = true;

        return $this;
    }
}
