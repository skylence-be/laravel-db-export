<?php

declare(strict_types=1);

use Xve\DbExport\Config\ProfileManager;
use Xve\DbExport\Exceptions\InvalidProfileException;

beforeEach(function (): void {
    $this->profiles = [
        'default' => [
            'description' => 'Default profile',
            'exclude' => [],
            'structure_only' => [],
            'include_only' => null,
            'anonymize' => [],
        ],
        'clean' => [
            'description' => 'Clean export',
            'exclude' => ['telescope_*', 'sessions'],
            'structure_only' => [],
            'include_only' => null,
            'anonymize' => [],
        ],
        'anonymized' => [
            'description' => 'Anonymized export',
            'exclude' => ['telescope_*'],
            'structure_only' => [],
            'include_only' => null,
            'anonymize' => [
                'users' => [
                    'email' => ['strategy' => 'faker', 'method' => 'email'],
                ],
            ],
        ],
    ];

    $this->manager = new ProfileManager($this->profiles);
});

test('gets existing profile', function (): void {
    $profile = $this->manager->get('clean');

    expect($profile['description'])->toBe('Clean export')
        ->and($profile['exclude'])->toContain('telescope_*', 'sessions');
});

test('throws exception for non-existent profile', function (): void {
    $this->manager->get('nonexistent');
})->throws(InvalidProfileException::class);

test('checks if profile exists', function (): void {
    expect($this->manager->exists('clean'))->toBeTrue()
        ->and($this->manager->exists('nonexistent'))->toBeFalse();
});

test('gets all profile names', function (): void {
    $names = $this->manager->getNames();

    expect($names)->toContain('default', 'clean', 'anonymized')
        ->and($names)->toHaveCount(3);
});

test('gets all profiles', function (): void {
    $all = $this->manager->all();

    expect($all)->toHaveCount(3)
        ->and($all)->toHaveKeys(['default', 'clean', 'anonymized']);
});

test('gets profiles with descriptions', function (): void {
    $profiles = $this->manager->getWithDescriptions();

    expect($profiles['clean']['name'])->toBe('clean')
        ->and($profiles['clean']['description'])->toBe('Clean export')
        ->and($profiles['clean']['exclude_count'])->toBe(2)
        ->and($profiles['anonymized']['has_anonymization'])->toBeTrue()
        ->and($profiles['clean']['has_anonymization'])->toBeFalse();
});

test('merges profiles', function (): void {
    $merged = $this->manager->merge('clean', [
        'exclude' => ['cache'],
        'structure_only' => ['activity_log'],
    ]);

    expect($merged['exclude'])->toContain('telescope_*', 'sessions', 'cache')
        ->and($merged['structure_only'])->toContain('activity_log');
});

test('registers new profile', function (): void {
    $this->manager->register('custom', [
        'description' => 'Custom profile',
        'exclude' => ['custom_table'],
    ]);

    expect($this->manager->exists('custom'))->toBeTrue()
        ->and($this->manager->get('custom')['exclude'])->toContain('custom_table');
});

test('extends existing profile', function (): void {
    $this->manager->extend('extended', 'clean', [
        'exclude' => ['additional_table'],
    ]);

    $profile = $this->manager->get('extended');

    expect($profile['exclude'])->toContain('telescope_*', 'sessions', 'additional_table');
});
