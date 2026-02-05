<?php

declare(strict_types=1);

use Xve\DbExport\Actions\Anonymization\Strategies\MaskStrategy;

beforeEach(function (): void {
    $this->strategy = new MaskStrategy;
});

test('masks entire value by default', function (): void {
    $result = $this->strategy->anonymize('secret123', []);

    expect($result)->toBe('*********');
});

test('keeps first characters when specified', function (): void {
    $result = $this->strategy->anonymize('secret123', ['keep_first' => 2]);

    expect($result)->toBe('se*******');
});

test('keeps last characters when specified', function (): void {
    $result = $this->strategy->anonymize('secret123', ['keep_last' => 4]);

    expect($result)->toBe('*****t123');
});

test('keeps both first and last characters', function (): void {
    $result = $this->strategy->anonymize('secret123', [
        'keep_first' => 2,
        'keep_last' => 2,
    ]);

    expect($result)->toBe('se*****23');
});

test('uses custom mask character', function (): void {
    $result = $this->strategy->anonymize('secret', ['char' => 'X']);

    expect($result)->toBe('XXXXXX');
});

test('returns null for null value', function (): void {
    $result = $this->strategy->anonymize(null, []);

    expect($result)->toBeNull();
});

test('returns empty string for empty value', function (): void {
    $result = $this->strategy->anonymize('', []);

    expect($result)->toBe('');
});

test('preserves format when specified', function (): void {
    $result = $this->strategy->anonymize('555-123-4567', ['preserve_format' => true]);

    expect($result)->toBe('***-***-****');
});

test('masks email preserving domain', function (): void {
    $result = $this->strategy->maskEmail('john.doe@example.com');

    expect($result)->toBe('j*******@example.com');
});

test('masks phone keeping last digits', function (): void {
    $result = $this->strategy->maskPhone('555-123-4567', '*', 4);

    expect($result)->toBe('***-***-4567');
});

test('returns original value when keep_first + keep_last >= length', function (): void {
    $result = $this->strategy->anonymize('abc', [
        'keep_first' => 2,
        'keep_last' => 2,
    ]);

    expect($result)->toBe('abc');
});

test('supports method returns true for mask strategy', function (): void {
    expect($this->strategy->supports(['strategy' => 'mask']))->toBeTrue();
});

test('supports method returns false for other strategies', function (): void {
    expect($this->strategy->supports(['strategy' => 'faker']))->toBeFalse()
        ->and($this->strategy->supports(['strategy' => 'null']))->toBeFalse();
});

test('getName returns mask', function (): void {
    expect($this->strategy->getName())->toBe('mask');
});
