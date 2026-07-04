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

        // Use a callback so $ / \1 / ${x} sequences inside values are written literally
        // instead of being interpreted as preg replacement back-references.
        return preg_replace_callback(
            '/^\s*OptionSettings=.*$/m',
            static fn (array $matches): string => $rebuiltLine,
            $contents,
            1
        ) ?? $contents;
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
        $escaped = false;
        $length = strlen($payload);

        for ($index = 0; $index < $length; $index++) {
            $character = $payload[$index];

            // Honour the same C-style escaping serializeValue() emits: a backslash inside
            // quotes escapes the next char, so an escaped \" or \\ never toggles the quote state.
            if ($escaped) {
                $buffer .= $character;
                $escaped = false;
                continue;
            }

            if ($character === '\\' && $inQuotes) {
                $buffer .= $character;
                $escaped = true;
                continue;
            }

            if ($character === '"') {
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
        $escaped = false;
        $length = strlen($token);

        for ($index = 0; $index < $length; $index++) {
            $character = $token[$index];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($character === '\\' && $inQuotes) {
                $escaped = true;
                continue;
            }

            if ($character === '"') {
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

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            // Palworld writes float settings with 6 decimals; keep that format for parity.
            return number_format($value, 6, '.', '');
        }

        $stringValue = (string) $value;

        // Strip CR/LF so a value can never break out of its token and split the
        // single-line OptionSettings=(...) payload or inject extra INI lines.
        $stringValue = str_replace(["\r", "\n"], '', $stringValue);

        if ($stringValue === '') {
            return '""';
        }

        // Numeric values arrive as real int/float (handled above); a numeric-looking string
        // here is a genuine string-field value (e.g. RandomizerSeed) and must be quoted as-is
        // rather than reformatted to 6 decimals.

        if (in_array($stringValue, ['None', 'Normal', 'Hard', 'Item', 'ItemAndEquipment', 'All', 'Region', 'Text'], true)) {
            return $stringValue;
        }

        // Pass through a single fully-balanced tuple like (Steam,Xbox) verbatim, but NOT
        // arbitrary text that merely starts/ends with parens (e.g. "()x=1,Evil=(y)"), which
        // would inject extra top-level keys into the OptionSettings payload.
        if ($this->isSingleBalancedGroup($stringValue)) {
            return $stringValue;
        }

        return '"' . addcslashes($stringValue, "\\\"") . '"';
    }

    /**
     * True only if the whole string is one balanced parenthesised group, e.g. "(Steam,Xbox)".
     * "()x=1" or "(a)(b)" return false so they get quoted rather than passed through raw.
     */
    private function isSingleBalancedGroup(string $value): bool
    {
        if (! str_starts_with($value, '(') || ! str_ends_with($value, ')')) {
            return false;
        }

        $depth = 0;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];

            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;

                if ($depth < 0) {
                    return false;
                }

                // The opening paren must only close at the very end for a single group.
                if ($depth === 0 && $index !== $length - 1) {
                    return false;
                }
            }
        }

        return $depth === 0;
    }
}
