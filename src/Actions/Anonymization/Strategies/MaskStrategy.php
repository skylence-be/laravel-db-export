<?php

declare(strict_types=1);

namespace Xve\DbExport\Actions\Anonymization\Strategies;

use Xve\DbExport\Contracts\AnonymizationStrategyInterface;

class MaskStrategy implements AnonymizationStrategyInterface
{
    public function getName(): string
    {
        return 'mask';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function anonymize(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = is_string($value) ? $value : (json_encode($value) ?: '');

        if ($value === '') {
            return '';
        }

        /** @var string $char */
        $char = $options['char'] ?? '*';
        /** @var int $keepFirst */
        $keepFirst = $options['keep_first'] ?? 0;
        /** @var int $keepLast */
        $keepLast = $options['keep_last'] ?? 0;
        /** @var bool $preserveFormat */
        $preserveFormat = $options['preserve_format'] ?? false;

        if ($preserveFormat) {
            return $this->maskPreservingFormat($value, $char);
        }

        return $this->maskWithEnds($value, $char, $keepFirst, $keepLast);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function supports(array $config): bool
    {
        return ($config['strategy'] ?? null) === 'mask';
    }

    protected function maskWithEnds(string $value, string $char, int $keepFirst, int $keepLast): string
    {
        $length = mb_strlen($value);

        if ($keepFirst + $keepLast >= $length) {
            return $value;
        }

        $first = $keepFirst > 0 ? mb_substr($value, 0, $keepFirst) : '';
        $last = $keepLast > 0 ? mb_substr($value, -$keepLast) : '';
        $maskLength = $length - $keepFirst - $keepLast;

        return $first.str_repeat($char, $maskLength).$last;
    }

    protected function maskPreservingFormat(string $value, string $char): string
    {
        $result = '';
        $length = mb_strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $currentChar = mb_substr($value, $i, 1);

            if (ctype_alpha($currentChar)) {
                $result .= $char;
            } elseif (ctype_digit($currentChar)) {
                $result .= $char;
            } else {
                $result .= $currentChar;
            }
        }

        return $result;
    }

    /**
     * Mask an email while preserving the domain.
     */
    public function maskEmail(string $email, string $char = '*'): string
    {
        $parts = explode('@', $email);

        if (count($parts) !== 2) {
            return $this->maskWithEnds($email, $char, 1, 1);
        }

        $localPart = $this->maskWithEnds($parts[0], $char, 1, 0);

        return $localPart.'@'.$parts[1];
    }

    /**
     * Mask a phone number keeping last digits.
     */
    public function maskPhone(string $phone, string $char = '*', int $keepLast = 4): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === null || (string) $digits === '') {
            return $phone;
        }

        $masked = $this->maskWithEnds($digits, $char, 0, $keepLast);

        return $this->restoreFormat($phone, $masked);
    }

    /**
     * Restore the original format to masked content.
     */
    protected function restoreFormat(string $original, string $masked): string
    {
        $result = '';
        $maskedIndex = 0;
        $length = strlen($original);

        for ($i = 0; $i < $length; $i++) {
            if (ctype_digit($original[$i])) {
                $result .= $masked[$maskedIndex] ?? '*';
                $maskedIndex++;
            } else {
                $result .= $original[$i];
            }
        }

        return $result;
    }
}
