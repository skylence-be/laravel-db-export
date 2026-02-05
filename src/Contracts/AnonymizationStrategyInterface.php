<?php

declare(strict_types=1);

namespace Dwb\DbExport\Contracts;

interface AnonymizationStrategyInterface
{
    /**
     * Get the strategy name.
     */
    public function getName(): string;

    /**
     * Anonymize a value.
     *
     * @param  array<string, mixed>  $options
     */
    public function anonymize(mixed $value, array $options = []): mixed;

    /**
     * Check if this strategy supports the given configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public function supports(array $config): bool;
}
