<?php

declare(strict_types=1);

use Xve\DbExport\Config\ProfileManager;
use Xve\DbExport\Exceptions\InvalidProfileException;

beforeEach(function (): void {
    $this->profiles = [
        'default' => [
            'description' => 'Clean export with anonymized PII data',
            'exclude' => [],
            'structure_only' => ['audits', 'telescope_*', 'sessions'],
            'include_only' => null,
            'anonymize' => [
                'users' => [
                    'email' => ['strategy' => 'faker', 'method' => 'safeEmail'],
                ],
            ],
        ],
        'inspection' => [
            'description' => 'Export only telescope and audit data for debugging',
            'exclude' => [],
            'structure_only' => [],
            'include_only' => ['telescope_*', 'audits'],
            'anonymize' => [],
        ],
    ];

    $this->manager = new ProfileManager($this->profiles);
});

test('gets existing profile', function (): void {
    $profile = $this->manager->get('default');

    expect($profile['description'])->toBe('Clean export with anonymized PII data')
        ->and($profile['structure_only'])->toContain('audits', 'telescope_*', 'sessions');
});

test('throws exception for non-existent profile', function (): void {
    $this->manager->get('nonexistent');
})->throws(InvalidProfileException::class);

test('checks if profile exists', function (): void {
    expect($this->manager->exists('default'))->toBeTrue()
        ->and($this->manager->exists('inspection'))->toBeTrue()
        ->and($this->manager->exists('nonexistent'))->toBeFalse();
});

test('gets all profile names', function (): void {
    $names = $this->manager->getNames();

    expect($names)->toContain('default', 'inspection')
        ->and($names)->toHaveCount(2);
});

test('gets all profiles', function (): void {
    $all = $this->manager->all();

    expect($all)->toHaveCount(2)
        ->and($all)->toHaveKeys(['default', 'inspection']);
});

test('gets profiles with descriptions', function (): void {
    $profiles = $this->manager->getWithDescriptions();

    expect($profiles['default']['name'])->toBe('default')
        ->and($profiles['default']['description'])->toBe('Clean export with anonymized PII data')
        ->and($profiles['default']['has_anonymization'])->toBeTrue()
        ->and($profiles['inspection']['has_anonymization'])->toBeFalse();
});

test('merges profiles', function (): void {
    $merged = $this->manager->merge('default', [
        'exclude' => ['cache'],
        'structure_only' => ['activity_log'],
    ]);

    expect($merged['exclude'])->toContain('cache')
        ->and($merged['structure_only'])->toContain('audits', 'activity_log');
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
    $this->manager->extend('extended', 'default', [
        'exclude' => ['additional_table'],
    ]);

    $profile = $this->manager->get('extended');

    expect($profile['exclude'])->toContain('additional_table')
        ->and($profile['structure_only'])->toContain('audits', 'telescope_*', 'sessions');
});
