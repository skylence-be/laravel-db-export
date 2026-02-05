<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Anonymization\Strategies;

use Xve\DbExport\Contracts\AnonymizationStrategyInterface;

class NullStrategy implements AnonymizationStrategyInterface
{
    public function getName(): string
    {
        return 'null';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function anonymize(mixed $value, array $options = []): mixed
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function supports(array $config): bool
    {
        return ($config['strategy'] ?? null) === 'null';
    }
}
