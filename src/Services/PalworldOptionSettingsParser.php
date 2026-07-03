<?php

namespace JanPauw\PalworldSettingsEditor\Services;

use RuntimeException;

class PalworldOptionSettingsParser
{
    public function parse(string $contents): array
    {
        $line = $this->extractOptionSettingsLine($contents);

        if ($line === null) {
            throw new RuntimeException('OptionSettings line could not be found.');
        }

        return $this->parseOptionSettingsLine($line);
    }

    public function write(string $contents, array $updatedValues): string
    {
        $line = $this->extractOptionSettingsLine($contents);

        if ($line === null) {
            throw new RuntimeException('OptionSettings line could not be found.');
        }

        $entries = $this->parseLineEntries($line);
        $updatedLookup = array_change_key_case($updatedValues, CASE_LOWER);

        foreach ($entries as &$entry) {
            $normalizedKey = strtolower($entry['key']);
            if (!array_key_exists($normalizedKey, $updatedLookup)) {
                continue;
            }

            $entry['raw_value'] = $this->serializeValue($updatedLookup[$normalizedKey]);
        }
        unset($entry);

        foreach ($updatedValues as $key => $value) {
            $exists = false;
            foreach ($entries as $entry) {
                if (strcasecmp($entry['key'], $key) === 0) {
                    $exists = true;
                    break;
                }
            }

            if (! $exists) {
                $entries[] = [
                    'key' => $key,
                    'raw_value' => $this->serializeValue($value),
                ];
            }
        }

        $rebuiltLine = 'OptionSettings=(' . implode(',', array_map(
            fn (array $entry): string => $entry['key'] . '=' . $entry['raw_value'],
            $entries
        )) . ')';

        return preg_replace('/^\s*OptionSettings=.*$/m', $rebuiltLine, $contents, 1) ?? $contents;
    }

    public function parseOptionSettingsLine(string $line): array
    {
        $prefix = 'OptionSettings=';

        if (!str_starts_with(trim($line), $prefix)) {
            throw new RuntimeException('The provided line is not an OptionSettings line.');
        }

        $raw = trim(substr(trim($line), strlen($prefix)));

        if (!str_starts_with($raw, '(') || !str_ends_with($raw, ')')) {
            throw new RuntimeException('The OptionSettings payload is malformed.');
        }

        $payload = substr($raw, 1, -1);
        $tokens = $this->splitTopLevel($payload);
        $parsed = [];

        foreach ($tokens as $token) {
            $separator = $this->findFirstTopLevelEquals($token);

            if ($separator === null) {
                continue;
            }

            $key = trim(substr($token, 0, $separator));
            $value = trim(substr($token, $separator + 1));

            if ($key === '') {
                continue;
            }

            $parsed[$key] = $this->normalizeValue($value);
        }

        return $parsed;
    }

    private function extractOptionSettingsLine(string $contents): ?string
    {
        foreach (preg_split("/\\r\\n|\\n|\\r/", $contents) ?: [] as $line) {
            if (str_starts_with(trim($line), 'OptionSettings=')) {
                return trim($line);
            }
        }

        return null;
    }

    /**
     * @return array<int, array{key: string, raw_value: string}>
     */
    private function parseLineEntries(string $line): array
    {
        $prefix = 'OptionSettings=';
        $raw = trim(substr(trim($line), strlen($prefix)));
        $payload = substr($raw, 1, -1);
        $tokens = $this->splitTopLevel($payload);
        $entries = [];

        foreach ($tokens as $token) {
            $separator = $this->findFirstTopLevelEquals($token);
            if ($separator === null) {
                continue;
            }

            $key = trim(substr($token, 0, $separator));
            $rawValue = trim(substr($token, $separator + 1));

            if ($key === '') {
                continue;
            }

            $entries[] = [
                'key' => $key,
                'raw_value' => $rawValue,
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, string>
     */
    private function splitTopLevel(string $payload): array
    {
        $tokens = [];
        $buffer = '';
        $depth = 0;
        $inQuotes = false;
        $length = strlen($payload);

        for ($index = 0; $index < $length; $index++) {
            $character = $payload[$index];
            $previous = $index > 0 ? $payload[$index - 1] : null;

            if ($character === '"' && $previous !== '\\') {
                $inQuotes = ! $inQuotes;
                $buffer .= $character;
                continue;
            }

            if (! $inQuotes) {
                if ($character === '(') {
                    $depth++;
                } elseif ($character === ')') {
                    $depth = max(0, $depth - 1);
                } elseif ($character === ',' && $depth === 0) {
                    $tokens[] = $buffer;
                    $buffer = '';
                    continue;
                }
            }

            $buffer .= $character;
        }

        if ($buffer !== '') {
            $tokens[] = $buffer;
        }

        return $tokens;
    }

    private function findFirstTopLevelEquals(string $token): ?int
    {
        $depth = 0;
        $inQuotes = false;
        $length = strlen($token);

        for ($index = 0; $index < $length; $index++) {
            $character = $token[$index];
            $previous = $index > 0 ? $token[$index - 1] : null;

            if ($character === '"' && $previous !== '\\') {
                $inQuotes = ! $inQuotes;
                continue;
            }

            if ($inQuotes) {
                continue;
            }

            if ($character === '(') {
                $depth++;
                continue;
            }

            if ($character === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }

            if ($character === '=' && $depth === 0) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeValue(string $value): mixed
    {
        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            return stripcslashes(substr($value, 1, -1));
        }

        if ($value === 'True') {
            return true;
        }

        if ($value === 'False') {
            return false;
        }

        return $value;
    }

    private function serializeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'True' : 'False';
        }

        if ($value === null) {
            return '""';
        }

        if (is_int($value) || is_float($value)) {
            return $this->formatNumericString((string) $value);
        }

        $stringValue = (string) $value;

        if ($stringValue === '') {
            return '""';
        }

        if (is_numeric($stringValue)) {
            return $this->formatNumericString($stringValue);
        }

        if (in_array($stringValue, ['None', 'Normal', 'Hard', 'Item', 'ItemAndEquipment', 'All', 'Region', 'Text'], true)) {
            return $stringValue;
        }

        if (str_starts_with($stringValue, '(') && str_ends_with($stringValue, ')')) {
            return $stringValue;
        }

        return '"' . addcslashes($stringValue, "\\\"") . '"';
    }

    private function formatNumericString(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        return number_format((float) $value, 6, '.', '');
    }
}
