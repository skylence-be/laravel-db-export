<?php

declare(strict_types=1);

namespace Xve\DbExport\Exceptions;

use Exception;

class AnonymizationException extends Exception
{
    public function __construct(
        string $message,
        protected ?string $table = null,
        protected ?string $column = null,
        protected ?string $strategy = null
    ) {
        parent::__construct($message);
    }

    public function getTable(): ?string
    {
        return $this->table;
    }

    public function getColumn(): ?string
    {
        return $this->column;
    }

    public function getStrategy(): ?string
    {
        return $this->strategy;
    }

    public static function unknownStrategy(string $strategy): self
    {
        return new self(
            'Unknown anonymization strategy: '.$strategy,
            null,
            null,
            $strategy
        );
    }

    public static function missingOption(string $option, string $strategy): self
    {
        return new self(
            sprintf("Missing required option '%s' for strategy '%s'", $option, $strategy),
            null,
            null,
            $strategy
        );
    }

    public static function invalidFakerMethod(string $method): self
    {
        return new self(
            'Invalid Faker method: '.$method,
            null,
            null,
            'faker'
        );
    }

    public static function fakerError(string $method, string $error): self
    {
        return new self(
            sprintf("Faker error calling '%s': %s", $method, $error),
            null,
            null,
            'faker'
        );
    }

    public static function columnNotFound(string $table, string $column): self
    {
        return new self(
            sprintf("Column '%s' not found in table '%s'", $column, $table),
            $table,
            $column
        );
    }

    public static function processingError(string $table, string $column, string $error): self
    {
        return new self(
            sprintf('Error anonymizing %s.%s: %s', $table, $column, $error),
            $table,
            $column
        );
    }
}
