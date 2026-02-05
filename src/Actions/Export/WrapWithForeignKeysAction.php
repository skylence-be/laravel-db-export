<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Export;

use Xve\DbExport\Exceptions\ExportException;

class WrapWithForeignKeysAction
{
    protected string $disableStatement = "SET FOREIGN_KEY_CHECKS = 0;\n";

    protected string $enableStatement = "SET FOREIGN_KEY_CHECKS = 1;\n";

    /**
     * Wrap the SQL file content with FK disable/enable statements.
     * Uses streaming for large files to avoid memory issues.
     */
    public function execute(string $filePath): void
    {
        if (! file_exists($filePath)) {
            throw ExportException::fileNotFound($filePath);
        }

        $tempPath = $filePath.'.fk_wrap';

        $header = "-- Disable foreign key checks for import\n";
        $header .= $this->disableStatement;
        $header .= "\n";

        $footer = "\n";
        $footer .= "-- Re-enable foreign key checks\n";
        $footer .= $this->enableStatement;

        // Write header to temp file
        $tempHandle = fopen($tempPath, 'w');
        if ($tempHandle === false) {
            throw ExportException::fileNotWritable($tempPath);
        }

        fwrite($tempHandle, $header);

        // Stream copy original file content
        $sourceHandle = fopen($filePath, 'r');
        if ($sourceHandle === false) {
            fclose($tempHandle);
            unlink($tempPath);

            throw ExportException::fileNotReadable($filePath);
        }

        stream_copy_to_stream($sourceHandle, $tempHandle);
        fclose($sourceHandle);

        // Write footer
        fwrite($tempHandle, $footer);
        fclose($tempHandle);

        // Replace original with wrapped version
        if (! rename($tempPath, $filePath)) {
            unlink($tempPath);

            throw ExportException::fileNotWritable($filePath);
        }
    }

    /**
     * Wrap SQL content with FK statements.
     */
    public function wrap(string $sql): string
    {
        $header = "-- Disable foreign key checks for import\n";
        $header .= $this->disableStatement;
        $header .= "\n";

        $footer = "\n";
        $footer .= "-- Re-enable foreign key checks\n";
        $footer .= $this->enableStatement;

        return $header.$sql.$footer;
    }

    /**
     * Remove FK wrapper statements from SQL content.
     */
    public function unwrap(string $sql): string
    {
        /** @var string|null $result */
        $result = preg_replace(
            '/^--\s*Disable foreign key checks.*?\n/mi',
            '',
            $sql
        );
        $sql = $result ?? $sql;

        /** @var string|null $result */
        $result = preg_replace(
            '/^SET FOREIGN_KEY_CHECKS\s*=\s*0;\s*\n/mi',
            '',
            $sql
        );
        $sql = $result ?? $sql;

        /** @var string|null $result */
        $result = preg_replace(
            '/^--\s*Re-enable foreign key checks.*?\n/mi',
            '',
            $sql
        );
        $sql = $result ?? $sql;

        /** @var string|null $result */
        $result = preg_replace(
            '/^SET FOREIGN_KEY_CHECKS\s*=\s*1;\s*\n?/mi',
            '',
            $sql
        );
        $sql = $result ?? $sql;

        return trim($sql)."\n";
    }

    /**
     * Check if SQL content already has FK wrapper.
     */
    public function hasWrapper(string $sql): bool
    {
        return str_contains($sql, 'SET FOREIGN_KEY_CHECKS = 0')
            || str_contains($sql, 'SET FOREIGN_KEY_CHECKS=0');
    }

    /**
     * Set custom disable statement.
     */
    public function setDisableStatement(string $statement): self
    {
        $this->disableStatement = $statement;

        return $this;
    }

    /**
     * Set custom enable statement.
     */
    public function setEnableStatement(string $statement): self
    {
        $this->enableStatement = $statement;

        return $this;
    }

    /**
     * Get the FK statements for manual use.
     *
     * @return array<string, string>
     */
    public function getStatements(): array
    {
        return [
            'disable' => $this->disableStatement,
            'enable' => $this->enableStatement,
        ];
    }
}
