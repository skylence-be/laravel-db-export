<?php

declare(strict_types=1);

use function Pest\Laravel\artisan;

test('lists available profiles', function (): void {
    artisan('db:export:list-profiles')
        ->assertSuccessful()
        ->expectsOutputToContain('default')
        ->expectsOutputToContain('clean')
        ->expectsOutputToContain('minimal')
        ->expectsOutputToContain('schema')
        ->expectsOutputToContain('anonymized');
});

test('shows detailed profile information', function (): void {
    artisan('db:export:list-profiles', ['--detailed' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('clean')
        ->expectsOutputToContain('telescope_*');
});
