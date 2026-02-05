<?php

declare(strict_types=1);

namespace Xve\DbExport\DTOs;

readonly class DiskSpaceResult
{
    public function __construct(
        public bool $sufficient,
        public int $availableBytes,
        public int $requiredBytes,
        public int $estimatedExportSize,
        public float $safetyMargin,
        public ?string $warning = null,
    ) {}

    public function getAvailableMB(): float
    {
        return round($this->availableBytes / 1024 / 1024, 2);
    }

    public function getRequiredMB(): float
    {
        return round($this->requiredBytes / 1024 / 1024, 2);
    }

    public function getShortfall(): int
    {
        return max(0, $this->requiredBytes - $this->availableBytes);
    }

    public function getShortfallMB(): float
    {
        return round($this->getShortfall() / 1024 / 1024, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sufficient' => $this->sufficient,
            'available_bytes' => $this->availableBytes,
            'available_mb' => $this->getAvailableMB(),
            'required_bytes' => $this->requiredBytes,
            'required_mb' => $this->getRequiredMB(),
            'estimated_export_size' => $this->estimatedExportSize,
            'safety_margin' => $this->safetyMargin,
            'shortfall_mb' => $this->getShortfallMB(),
            'warning' => $this->warning,
        ];
    }

    public static function sufficient(int $availableBytes, int $requiredBytes, int $estimatedSize, float $margin): self
    {
        return new self(
            sufficient: true,
            availableBytes: $availableBytes,
            requiredBytes: $requiredBytes,
            estimatedExportSize: $estimatedSize,
            safetyMargin: $margin,
        );
    }

    public static function insufficient(
        int $availableBytes,
        int $requiredBytes,
        int $estimatedSize,
        float $margin,
        string $warning
    ): self {
        return new self(
            sufficient: false,
            availableBytes: $availableBytes,
            requiredBytes: $requiredBytes,
            estimatedExportSize: $estimatedSize,
            safetyMargin: $margin,
            warning: $warning,
        );
    }
}
