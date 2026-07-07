<?php

namespace JanPauw\PalworldSettingsEditor\Services;

class PelicanStartupVariableService
{
    public function __construct(
        private readonly PalworldSettingsSchema $schema,
    ) {
    }

    public function getVariablesForServer(mixed $server): array
    {
        $definitions = [];

        foreach ($this->getCandidateCollections($server) as $items) {
            foreach ($items as $item) {
                $name = $this->resolveName($item);
                if ($name === null) {
                    continue;
                }

                $definitions[$name] ??= [
                    'name' => $name,
                    'value' => $this->resolveValue($item),
                    'description' => $this->resolveDescription($item),
                    'is_sensitive' => $this->isSensitive($name, $item),
                ];

                if ($definitions[$name]['value'] === null) {
                    $definitions[$name]['value'] = $this->resolveValue($item);
                }

                if ($definitions[$name]['description'] === null) {
                    $definitions[$name]['description'] = $this->resolveDescription($item);
                }
            }
        }

        $normalized = [];
        foreach ($this->schema->getStartupVariableNames() as $name) {
            if (!isset($definitions[$name])) {
                continue;
            }

            $normalized[$name] = $definitions[$name];
        }

        return $normalized;
    }

    /**
     * @return array<int, iterable<mixed>>
     */
    private function getCandidateCollections(mixed $server): array
    {
        $collections = [];

        $directCandidates = [
            data_get($server, 'variables'),
            data_get($server, 'startupVariables'),
            data_get($server, 'startup_variables'),
            data_get($server, 'eggVariables'),
            data_get($server, 'egg_variables'),
        ];

        foreach ($directCandidates as $candidate) {
            if (is_iterable($candidate)) {
                $collections[] = $candidate;
            }
        }

        $eggCandidates = [
            data_get($server, 'egg'),
            data_get($server, 'nestEgg'),
            data_get($server, 'nest_egg'),
        ];

        foreach ($eggCandidates as $egg) {
            if ($egg === null) {
                continue;
            }

            $variables = data_get($egg, 'variables');
            if (is_iterable($variables)) {
                $collections[] = $variables;
            }
        }

        return $collections;
    }

    private function resolveName(mixed $item): ?string
    {
        $name = data_get($item, 'env_variable')
            ?? data_get($item, 'envVariable')
            ?? data_get($item, 'name');

        if (!is_string($name) || $name === '') {
            return null;
        }

        return strtoupper(trim($name));
    }

    private function resolveValue(mixed $item): mixed
    {
        $valueCandidates = [
            data_get($item, 'server_value'),
            data_get($item, 'serverValue'),
            data_get($item, 'value'),
            data_get($item, 'default_value'),
            data_get($item, 'defaultValue'),
            data_get($item, 'default'),
        ];

        foreach ($valueCandidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveDescription(mixed $item): ?string
    {
        $description = data_get($item, 'description')
            ?? data_get($item, 'label')
            ?? data_get($item, 'display_name')
            ?? data_get($item, 'displayName');

        return is_string($description) && $description !== '' ? $description : null;
    }

    private function isSensitive(string $name, mixed $item): bool
    {
        if ((bool) data_get($item, 'is_sensitive', false) || (bool) data_get($item, 'isSensitive', false)) {
            return true;
        }

        // Mirror PalworldSettingsPage::isSensitiveKey() so any password/token/secret-named
        // variable is masked by default, even when the egg doesn't flag it sensitive.
        return str_contains($name, 'PASSWORD')
            || str_contains($name, 'TOKEN')
            || str_contains($name, 'SECRET');
    }
}
