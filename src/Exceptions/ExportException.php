<?php

declare(strict_types=1);

namespace Xve\DbExport\Exceptions;

use Exception;
use Throwable;

class ExportException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        protected float $duration = 0.0
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public static function failed(string $reason, ?Throwable $previous = null, float $duration = 0.0): self
    {
        return new self(
            'Export failed: '.$reason,
            0,
            $previous,
            $duration
        );
    }

    public static function directoryNotCreatable(string $path): self
    {
        return new self('Could not create directory: '.$path);
    }

    public static function directoryNotWritable(string $path): self
    {
        return new self('Directory is not writable: '.$path);
    }

    public static function fileNotFound(string $path): self
    {
        return new self('File not found: '.$path);
    }

    public static function fileNotReadable(string $path): self
    {
        return new self('File is not readable: '.$path);
    }

    public static function fileNotWritable(string $path): self
    {
        return new self('File is not writable: '.$path);
    }

    public static function readError(string $path): self
    {
        return new self('Error reading file: '.$path);
    }

    public static function writeError(string $path): self
    {
        return new self('Error writing file: '.$path);
    }

    public static function compressionFailed(string $reason): self
    {
        return new self('Compression failed: '.$reason);
    }

    public static function mysqldumpFailed(string $output): self
    {
        return new self('mysqldump failed: '.$output);
    }
}
