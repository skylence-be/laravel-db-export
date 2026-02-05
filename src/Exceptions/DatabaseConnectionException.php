<?php

declare(strict_types=1);

namespace Xve\DbExport\Exceptions;

use Exception;
use Throwable;

class DatabaseConnectionException extends Exception
{
    public function __construct(
        string $message,
        protected ?string $connectionName = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    public static function connectionFailed(string $connection, ?Throwable $previous = null): self
    {
        return new self(
            sprintf("Failed to connect to database '%s'", $connection),
            $connection,
            $previous
        );
    }

    public static function connectionNotFound(string $connection): self
    {
        return new self(
            sprintf("Database connection '%s' is not configured", $connection),
            $connection
        );
    }

    public static function unsupportedDriver(string $driver, string $connection): self
    {
        return new self(
            sprintf("Database driver '%s' is not supported for exports. Connection: %s", $driver, $connection),
            $connection
        );
    }

    public static function queryFailed(string $query, string $error, ?string $connection = null): self
    {
        $message = 'Query failed: '.$error;
        if ($connection !== null) {
            $message .= sprintf(' (connection: %s)', $connection);
        }

        return new self($message, $connection);
    }

    public static function accessDenied(string $connection, string $operation): self
    {
        return new self(
            sprintf("Access denied for operation '%s' on connection '%s'", $operation, $connection),
            $connection
        );
    }
}
