<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Anonymization\Strategies;

use Xve\DbExport\Contracts\AnonymizationStrategyInterface;

class HashStrategy implements AnonymizationStrategyInterface
{
    public function getName(): string
    {
        return 'hash';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function anonymize(mixed $value, array $options = []): mixed
    {
        if ($value === null && ($options['preserve_null'] ?? true)) {
            return null;
        }

        $valueToHash = $options['value'] ?? $value;

        /** @var string $algorithm */
        $algorithm = $options['algorithm'] ?? 'bcrypt';

        $stringValue = is_string($valueToHash) ? $valueToHash : (json_encode($valueToHash) ?: '');

        return match ($algorithm) {
            'bcrypt' => $this->hashBcrypt($stringValue, $options),
            'md5' => md5($stringValue),
            'sha256' => hash('sha256', $stringValue),
            'sha512' => hash('sha512', $stringValue),
            default => $this->hashBcrypt($stringValue, $options),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function supports(array $config): bool
    {
        return ($config['strategy'] ?? null) === 'hash';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function hashBcrypt(string $value, array $options): string
    {
        /** @var int $cost */
        $cost = $options['cost'] ?? 10;

        return password_hash($value, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    /**
     * Create a deterministic hash that's the same for the same input.
     * Useful when you need consistent anonymization across exports.
     */
    public function deterministicHash(string $value, string $salt, string $algorithm = 'sha256'): string
    {
        return hash($algorithm, $salt.$value);
    }

    /**
     * Verify a value against a hash.
     */
    public function verify(string $value, string $hash): bool
    {
        if (str_starts_with($hash, '$2')) {
            return password_verify($value, $hash);
        }

        return false;
    }
}
