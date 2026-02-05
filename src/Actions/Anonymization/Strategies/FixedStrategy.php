<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Anonymization\Strategies;

use Xve\DbExport\Contracts\AnonymizationStrategyInterface;

class FixedStrategy implements AnonymizationStrategyInterface
{
    public function getName(): string
    {
        return 'fixed';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function anonymize(mixed $value, array $options = []): mixed
    {
        if ($value === null && ($options['preserve_null'] ?? true)) {
            return null;
        }

        return $options['value'] ?? '';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function supports(array $config): bool
    {
        return ($config['strategy'] ?? null) === 'fixed';
    }
}
