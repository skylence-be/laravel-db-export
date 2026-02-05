<?php

declare(strict_types=1);

namespace Xve\DbExport\Exceptions;

use Exception;

class InvalidProfileException extends Exception
{
    /**
     * @param  array<int, string>  $availableProfiles
     */
    public function __construct(
        string $message,
        protected string $profileName = '',
        protected array $availableProfiles = []
    ) {
        parent::__construct($message);
    }

    public function getProfileName(): string
    {
        return $this->profileName;
    }

    /**
     * @return array<int, string>
     */
    public function getAvailableProfiles(): array
    {
        return $this->availableProfiles;
    }

    /**
     * @param  array<int, string>  $available
     */
    public static function notFound(string $name, array $available): self
    {
        $availableList = implode(', ', $available);
        $message = sprintf("Profile '%s' not found. Available profiles: %s", $name, $availableList);

        return new self($message, $name, $available);
    }

    public static function invalidConfiguration(string $name, string $reason): self
    {
        return new self(
            sprintf("Profile '%s' has invalid configuration: %s", $name, $reason),
            $name
        );
    }

    /**
     * @param  array<int, string>  $chain
     */
    public static function circularDependency(string $name, array $chain): self
    {
        $chainString = implode(' -> ', $chain);

        return new self(
            sprintf("Circular dependency detected in profile '%s': %s", $name, $chainString),
            $name
        );
    }
}
