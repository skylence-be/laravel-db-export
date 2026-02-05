<?php

declare(strict_types=1);

use Xve\DbExport\Actions\Tables\ExpandWildcardsAction;

beforeEach(function (): void {
    $this->action = new ExpandWildcardsAction;
    $this->tables = [
        'users',
        'posts',
        'comments',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
        'activity_log',
        'error_logs',
        'audit_logs',
        'sessions',
        'cache',
    ];
});

test('matches exact table names', function (): void {
    $result = $this->action->execute(['users', 'posts'], $this->tables);

    expect($result)->toContain('users', 'posts')
        ->and($result)->toHaveCount(2);
});

test('expands prefix wildcard patterns', function (): void {
    $result = $this->action->execute(['telescope_*'], $this->tables);

    expect($result)->toContain(
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring'
    )->and($result)->toHaveCount(3);
});

test('expands suffix wildcard patterns', function (): void {
    $result = $this->action->execute(['*_logs'], $this->tables);

    expect($result)->toContain('error_logs', 'audit_logs')
        ->and($result)->toHaveCount(2);
});

test('handles mixed patterns and exact names', function (): void {
    $result = $this->action->execute(['users', 'telescope_*'], $this->tables);

    expect($result)->toContain(
        'users',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring'
    )->and($result)->toHaveCount(4);
});

test('returns unique results', function (): void {
    $result = $this->action->execute(['users', 'user*'], $this->tables);

    expect($result)->toContain('users')
        ->and($result)->toHaveCount(1);
});

test('ignores non-matching patterns', function (): void {
    $result = $this->action->execute(['nonexistent', 'fake_*'], $this->tables);

    expect($result)->toBeEmpty();
});

test('matches method returns true for matching pattern', function (): void {
    expect($this->action->matches('telescope_entries', ['telescope_*']))->toBeTrue()
        ->and($this->action->matches('users', ['users']))->toBeTrue()
        ->and($this->action->matches('error_logs', ['*_logs']))->toBeTrue();
});

test('matches method returns false for non-matching pattern', function (): void {
    expect($this->action->matches('users', ['posts']))->toBeFalse()
        ->and($this->action->matches('users', ['telescope_*']))->toBeFalse();
});
