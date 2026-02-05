<?php

declare(strict_types=1);

use function Pest\Laravel\artisan;

test('lists available profiles', function (): void {
    artisan('db:export:list-profiles')
        ->assertSuccessful()
        ->expectsOutputToContain('default')
        ->expectsOutputToContain('inspection');
});

test('shows detailed profile information', function (): void {
    artisan('db:export:list-profiles', ['--detailed' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('default')
        ->expectsOutputToContain('telescope_*');
});
