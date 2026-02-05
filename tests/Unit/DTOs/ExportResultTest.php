<?php

declare(strict_types=1);

use Xve\DbExport\DTOs\ExportResult;

test('creates successful result', function (): void {
    $result = ExportResult::success(
        path: '/path/to/export.sql.gz',
        fileSize: 1024 * 1024,
        duration: 5.5,
        tables: ['users', 'posts'],
        anonymizedTables: ['users'],
        compressed: true
    );

    expect($result->success)->toBeTrue()
        ->and($result->path)->toBe('/path/to/export.sql.gz')
        ->and($result->fileSize)->toBe(1024 * 1024)
        ->and($result->duration)->toBe(5.5)
        ->and($result->tableCount)->toBe(2)
        ->and($result->tables)->toContain('users', 'posts')
        ->and($result->anonymizedTables)->toContain('users')
        ->and($result->compressed)->toBeTrue()
        ->and($result->error)->toBeNull();
});

test('creates failure result', function (): void {
    $result = ExportResult::failure('Something went wrong', 1.5);

    expect($result->success)->toBeFalse()
        ->and($result->error)->toBe('Something went wrong')
        ->and($result->duration)->toBe(1.5)
        ->and($result->path)->toBe('')
        ->and($result->fileSize)->toBe(0)
        ->and($result->tableCount)->toBe(0);
});

test('formats file size in bytes', function (): void {
    $result = ExportResult::success('/path', 500, 1.0, []);

    expect($result->getHumanFileSize())->toBe('500 B');
});

test('formats file size in KB', function (): void {
    $result = ExportResult::success('/path', 1536, 1.0, []);

    expect($result->getHumanFileSize())->toBe('1.5 KB');
});

test('formats file size in MB', function (): void {
    $result = ExportResult::success('/path', 1024 * 1024 * 2, 1.0, []);

    expect($result->getHumanFileSize())->toBe('2 MB');
});

test('formats file size in GB', function (): void {
    $result = ExportResult::success('/path', 1024 * 1024 * 1024 * 3, 1.0, []);

    expect($result->getHumanFileSize())->toBe('3 GB');
});

test('formats duration in milliseconds', function (): void {
    $result = ExportResult::success('/path', 0, 0.5, []);

    expect($result->getHumanDuration())->toBe('500ms');
});

test('formats duration in seconds', function (): void {
    $result = ExportResult::success('/path', 0, 5.25, []);

    expect($result->getHumanDuration())->toBe('5.25s');
});

test('formats duration in minutes and seconds', function (): void {
    $result = ExportResult::success('/path', 0, 125, []);

    expect($result->getHumanDuration())->toBe('2m 5s');
});

test('converts to array', function (): void {
    $result = ExportResult::success(
        path: '/path/to/export.sql.gz',
        fileSize: 1024,
        duration: 2.5,
        tables: ['users'],
        anonymizedTables: [],
        compressed: true
    );

    $array = $result->toArray();

    expect($array)->toBeArray()
        ->and($array['success'])->toBeTrue()
        ->and($array['path'])->toBe('/path/to/export.sql.gz')
        ->and($array['file_size'])->toBe(1024)
        ->and($array['file_size_human'])->toBe('1 KB')
        ->and($array['duration'])->toBe(2.5)
        ->and($array['duration_human'])->toBe('2.5s')
        ->and($array['table_count'])->toBe(1)
        ->and($array['compressed'])->toBeTrue();
});
