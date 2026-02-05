<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Anonymization\Strategies;

use Xve\DbExport\Contracts\AnonymizationStrategyInterface;
use Xve\DbExport\Exceptions\AnonymizationException;

class FakerStrategy implements AnonymizationStrategyInterface
{
    protected ?object $faker = null;

    protected bool $fakerAvailable;

    public function __construct(?object $faker = null)
    {
        $this->fakerAvailable = class_exists(\Faker\Factory::class);

        if ($faker !== null) {
            $this->faker = $faker;
        } elseif ($this->fakerAvailable) {
            $this->faker = \Faker\Factory::create();
        }
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

        // Use faker if available, otherwise use built-in fallbacks
        if ($this->fakerAvailable && $this->faker !== null) {
            return $this->callFakerMethod($method, $options['args'] ?? []);
        }

        return $this->fallbackAnonymize($method, $value);
    }

    /**
     * Built-in fallbacks when faker is not available.
     */
    protected function fallbackAnonymize(string $method, mixed $originalValue): mixed
    {
        $id = substr(md5($originalValue.random_bytes(8)), 0, 8);

        return match ($method) {
            'name', 'firstName', 'lastName' => 'User_'.$id,
            'email', 'safeEmail', 'freeEmail', 'companyEmail' => 'user_'.$id.'@example.com',
            'phoneNumber', 'phone', 'e164PhoneNumber' => '+1'.random_int(1000000000, 9999999999),
            'address', 'streetAddress' => $id.' Example Street',
            'city' => 'City_'.$id,
            'postcode', 'zipCode' => (string) random_int(10000, 99999),
            'country' => 'Country_'.$id,
            'company', 'companyName' => 'Company_'.$id,
            'userName', 'username' => 'user_'.$id,
            'url', 'domainName' => 'https://example-'.$id.'.com',
            'ipv4' => random_int(1, 255).'.'.random_int(0, 255).'.'.random_int(0, 255).'.'.random_int(1, 254),
            'text', 'sentence', 'paragraph' => 'Lorem ipsum '.$id,
            default => 'anon_'.$id,
        };
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
        if ($this->fakerAvailable) {
            $this->faker = \Faker\Factory::create($locale);
        }

        return $this;
    }

    public function getFaker(): ?object
    {
        return $this->faker;
    }

    public function isFakerAvailable(): bool
    {
        return $this->fakerAvailable;
    }
}
