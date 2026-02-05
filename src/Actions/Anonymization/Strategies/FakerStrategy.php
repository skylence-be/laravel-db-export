<?php

declare(strict_types=1);

namespace Dwb\DbExport\Actions\Anonymization\Strategies;

use Dwb\DbExport\Contracts\AnonymizationStrategyInterface;
use Dwb\DbExport\Exceptions\AnonymizationException;
use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;

class FakerStrategy implements AnonymizationStrategyInterface
{
    protected Faker $faker;

    public function __construct(?Faker $faker = null)
    {
        $this->faker = $faker ?? FakerFactory::create();
    }

    public function getName(): string
    {
        return 'faker';
    }

    public function anonymize(mixed $value, array $options = []): mixed
    {
        if ($value === null && ($options['preserve_null'] ?? true)) {
            return null;
        }

        $method = $options['method'] ?? null;

        if ($method === null) {
            throw AnonymizationException::missingOption('method', 'faker');
        }

        return $this->callFakerMethod($method, $options['args'] ?? []);
    }

    public function supports(array $config): bool
    {
        return ($config['strategy'] ?? null) === 'faker'
            && isset($config['method']);
    }

    protected function callFakerMethod(string $method, array $args = []): mixed
    {
        if (str_contains($method, '->')) {
            return $this->callChainedMethod($method, $args);
        }

        if (! method_exists($this->faker, $method) && ! $this->faker->getProviders()) {
            throw AnonymizationException::invalidFakerMethod($method);
        }

        try {
            if ($args === []) {
                return $this->faker->{$method};
            }

            return $this->faker->{$method}(...$args);
        } catch (\Throwable $throwable) {
            throw AnonymizationException::fakerError($method, $throwable->getMessage());
        }
    }

    protected function callChainedMethod(string $chain, array $args = []): mixed
    {
        $parts = explode('->', $chain);
        $result = $this->faker;

        foreach ($parts as $i => $part) {
            $methodArgs = ($i === count($parts) - 1) ? $args : [];

            if (preg_match('/^(\w+)\((.*)\)$/', $part, $matches)) {
                $methodName = $matches[1];
                $inlineArgs = $this->parseInlineArgs($matches[2]);
                $result = $result->{$methodName}(...$inlineArgs);
            } elseif ($methodArgs === []) {
                $result = $result->{$part};
            } else {
                $result = $result->{$part}(...$methodArgs);
            }
        }

        return $result;
    }

    protected function parseInlineArgs(string $argsString): array
    {
        if (trim($argsString) === '') {
            return [];
        }

        $args = [];
        $parts = preg_split('/,\s*(?=(?:[^"\']*["\'][^"\']*["\'])*[^"\']*$)/', $argsString);

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === 'true') {
                $args[] = true;
            } elseif ($part === 'false') {
                $args[] = false;
            } elseif ($part === 'null') {
                $args[] = null;
            } elseif (is_numeric($part)) {
                $args[] = str_contains($part, '.') ? (float) $part : (int) $part;
            } elseif (preg_match('/^["\'](.*)["\']/s', $part, $matches)) {
                $args[] = $matches[1];
            } else {
                $args[] = $part;
            }
        }

        return $args;
    }

    public function setLocale(string $locale): self
    {
        $this->faker = FakerFactory::create($locale);

        return $this;
    }

    public function getFaker(): Faker
    {
        return $this->faker;
    }
}
