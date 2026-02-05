<?php

declare(strict_types=1);

namespace Xve\DbExport\Config;

use Xve\DbExport\Exceptions\InvalidProfileException;

class ProfileManager
{
    /**
     * @param  array<string, array<string, mixed>>  $profiles
     */
    public function __construct(
        protected array $profiles = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(string $name): array
    {
        if (! $this->exists($name)) {
            throw InvalidProfileException::notFound($name, $this->getNames());
        }

        return $this->profiles[$name];
    }

    public function exists(string $name): bool
    {
        return isset($this->profiles[$name]);
    }

    /**
     * @return array<int, string>
     */
    public function getNames(): array
    {
        return array_keys($this->profiles);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->profiles;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getWithDescriptions(): array
    {
        /** @var array<string, array<string, mixed>> $result */
        $result = [];

        foreach ($this->profiles as $name => $config) {
            /** @var array<int, string> $exclude */
            $exclude = $config['exclude'] ?? [];
            /** @var array<int, string> $structureOnly */
            $structureOnly = $config['structure_only'] ?? [];

            $result[$name] = [
                'name' => $name,
                'description' => $config['description'] ?? 'No description',
                'exclude_count' => count($exclude),
                'structure_only_count' => count($structureOnly),
                'has_anonymization' => ! empty($config['anonymize']),
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function merge(string $base, array $overrides): array
    {
        $baseConfig = $this->get($base);

        /** @var array<int, string> $baseExclude */
        $baseExclude = $baseConfig['exclude'] ?? [];
        /** @var array<int, string> $overrideExclude */
        $overrideExclude = $overrides['exclude'] ?? [];
        /** @var array<int, string> $baseStructureOnly */
        $baseStructureOnly = $baseConfig['structure_only'] ?? [];
        /** @var array<int, string> $overrideStructureOnly */
        $overrideStructureOnly = $overrides['structure_only'] ?? [];
        /** @var array<string, array<string, mixed>> $baseAnonymize */
        $baseAnonymize = $baseConfig['anonymize'] ?? [];
        /** @var array<string, array<string, mixed>> $overrideAnonymize */
        $overrideAnonymize = $overrides['anonymize'] ?? [];

        return [
            'description' => $overrides['description'] ?? $baseConfig['description'] ?? '',
            'exclude' => array_values(array_unique(array_merge($baseExclude, $overrideExclude))),
            'structure_only' => array_values(array_unique(array_merge($baseStructureOnly, $overrideStructureOnly))),
            'include_only' => $overrides['include_only'] ?? $baseConfig['include_only'] ?? null,
            'anonymize' => array_merge($baseAnonymize, $overrideAnonymize),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function register(string $name, array $config): void
    {
        $this->profiles[$name] = $config;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function extend(string $name, string $base, array $overrides): void
    {
        $this->profiles[$name] = $this->merge($base, $overrides);
    }
}
