<?php

declare(strict_types=1);

namespace Xve\DbExport\Exceptions;

use Exception;
use Xve\DbExport\DTOs\DiskSpaceResult;

class InsufficientDiskSpaceException extends Exception
{
    public function __construct(
        string $message,
        protected int $availableBytes = 0,
        protected int $requiredBytes = 0
    ) {
        parent::__construct($message);
    }

    public function getAvailableBytes(): int
    {
        return $this->availableBytes;
    }

    public function getRequiredBytes(): int
    {
        return $this->requiredBytes;
    }

    public function getShortfall(): int
    {
        return max(0, $this->requiredBytes - $this->availableBytes);
    }

    public function getAvailableMB(): float
    {
        return round($this->availableBytes / 1024 / 1024, 2);
    }

    public function getRequiredMB(): float
    {
        return round($this->requiredBytes / 1024 / 1024, 2);
    }

    public static function fromDiskSpaceResult(DiskSpaceResult $result): self
    {
        return new self(
            $result->warning ?? 'Insufficient disk space',
            $result->availableBytes,
            $result->requiredBytes
        );
    }

    public static function create(int $available, int $required): self
    {
        $availableMB = round($available / 1024 / 1024, 2);
        $requiredMB = round($required / 1024 / 1024, 2);
        $shortfallMB = round(($required - $available) / 1024 / 1024, 2);

        return new self(
            sprintf('Insufficient disk space. Need %s MB but only %s MB available (shortfall: %s MB)', $requiredMB, $availableMB, $shortfallMB),
            $available,
            $required
        );
    }
}
