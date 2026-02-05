<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Export;

use Xve\DbExport\Exceptions\ExportException;

class CompressExportAction
{
    protected int $level;

    protected int $chunkSize;

    public function __construct()
    {
        /** @var int $configLevel */
        $configLevel = config('db-export.compression.level', 6);
        $this->level = (int) $configLevel;
        $this->chunkSize = 1024 * 1024; // 1MB chunks
    }

    /**
     * Compress a file using gzip.
     */
    public function execute(string $sourcePath, string $destinationPath): void
    {
        if (! file_exists($sourcePath)) {
            throw ExportException::fileNotFound($sourcePath);
        }

        $this->compressWithGzopen($sourcePath, $destinationPath);
    }

    /**
     * Compress using gzopen for streaming compression.
     */
    protected function compressWithGzopen(string $sourcePath, string $destinationPath): void
    {
        $source = fopen($sourcePath, 'rb');
        if ($source === false) {
            throw ExportException::fileNotReadable($sourcePath);
        }

        $destination = gzopen($destinationPath, 'wb'.$this->level);
        if ($destination === false) {
            fclose($source);

            throw ExportException::fileNotWritable($destinationPath);
        }

        try {
            while (! feof($source)) {
                /** @var int<1, max> $readSize */
                $readSize = max(1, $this->chunkSize);
                $chunk = fread($source, $readSize);
                if ($chunk === false) {
                    throw ExportException::readError($sourcePath);
                }

                gzwrite($destination, $chunk);
            }
        } finally {
            fclose($source);
            gzclose($destination);
        }
    }

    /**
     * Decompress a gzipped file.
     */
    public function decompress(string $sourcePath, string $destinationPath): void
    {
        if (! file_exists($sourcePath)) {
            throw ExportException::fileNotFound($sourcePath);
        }

        $source = gzopen($sourcePath, 'rb');
        if ($source === false) {
            throw ExportException::fileNotReadable($sourcePath);
        }

        $destination = fopen($destinationPath, 'wb');
        if ($destination === false) {
            gzclose($source);

            throw ExportException::fileNotWritable($destinationPath);
        }

        try {
            while (! gzeof($source)) {
                $chunk = gzread($source, $this->chunkSize);
                if ($chunk === false) {
                    throw ExportException::readError($sourcePath);
                }

                fwrite($destination, $chunk);
            }
        } finally {
            gzclose($source);
            fclose($destination);
        }
    }

    /**
     * Set compression level.
     */
    public function setLevel(int $level): self
    {
        $this->level = max(1, min(9, $level));

        return $this;
    }

    /**
     * Set chunk size for streaming.
     */
    public function setChunkSize(int $bytes): self
    {
        $this->chunkSize = $bytes;

        return $this;
    }

    /**
     * Check if gzip extension is available.
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('zlib');
    }
}
