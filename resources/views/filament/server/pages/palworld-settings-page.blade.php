<x-filament-panels::page>
    <div
        x-data="{
            setAllDetails(open) {
                this.$root.querySelectorAll('[data-palworld-section]').forEach((element) => {
                    element.open = open;
                });
            },
        }"
        class="mx-auto max-w-[1180px] space-y-5"
    >
        <style>
            .pal-card {
                background-color: rgb(24 24 27 / 0.96);
                border: 1px solid rgb(63 63 70 / 1);
                border-radius: 0.95rem;
            }

            .pal-card-header {
                border-bottom: 1px solid rgb(63 63 70 / 1);
            }

            .pal-field {
                background-color: rgb(39 39 42 / 0.92);
                border: 1px solid rgb(82 82 91 / 1);
                border-radius: 0.875rem;
                padding: 0.875rem 1rem 0.9375rem 1rem;
            }

            .pal-input,
            .pal-select {
                width: 100%;
                min-height: 2.75rem;
                border-radius: 0.625rem;
                border: 1px solid rgb(82 82 91 / 1);
                background-color: rgb(39 39 42 / 1);
                color: rgb(244 244 245 / 1);
                padding: 0.625rem 0.875rem;
            }

            .pal-input[type="number"] {
                padding-right: 0.625rem;
            }

            .pal-input:disabled,
            .pal-select:disabled {
                opacity: 1;
                color: rgb(228 228 231 / 1);
            }

            .pal-toggle {
                position: relative;
                display: inline-flex;
                align-items: center;
                gap: 0.75rem;
                min-height: 2.75rem;
                width: 100%;
                border-radius: 0.625rem;
                border: 1px solid rgb(82 82 91 / 1);
                background-color: rgb(39 39 42 / 1);
                padding: 0.625rem 0.875rem;
                color: rgb(244 244 245 / 1);
            }

            .pal-toggle input {
                position: absolute;
                opacity: 0;
                pointer-events: none;
            }

            .pal-toggle-track {
                position: relative;
                display: inline-flex;
                flex-shrink: 0;
                width: 2.75rem;
                height: 1.5rem;
                border-radius: 9999px;
                background-color: rgb(82 82 91 / 1);
                transition: background-color 150ms ease;
            }

            .pal-toggle-track::after {
                content: "";
                position: absolute;
                top: 0.125rem;
                left: 0.125rem;
                width: 1.25rem;
                height: 1.25rem;
                border-radius: 9999px;
                background-color: white;
                transition: transform 150ms ease;
            }

            .pal-toggle input:checked + .pal-toggle-track {
                background-color: rgb(96 165 250 / 1);
            }

            .pal-toggle input:checked + .pal-toggle-track::after {
                transform: translateX(1.25rem);
            }

            .pal-summary::-webkit-details-marker {
                display: none;
            }

            .pal-section {
                padding: 1rem;
            }

            .pal-grid {
                display: grid;
                gap: 0.9375rem;
            }

            .pal-stack {
                display: grid;
                gap: 0.9375rem;
            }

            @media (min-width: 900px) {
                .pal-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }
        </style>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <button
                type="button"
                wire:click="resetChanges"
                @disabled(! $this->hasUnsavedChanges())
                class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs font-medium text-zinc-100 transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
                Reset changes
            </button>
            <button
                type="button"
                wire:click="save"
                @disabled(! $this->canSave())
                class="rounded-lg border border-blue-500 bg-blue-500 px-3 py-2 text-xs font-medium text-white transition hover:bg-blue-400 disabled:cursor-not-allowed disabled:border-zinc-700 disabled:bg-zinc-800 disabled:text-zinc-500"
            >
                Save settings
            </button>
            <button
                type="button"
                x-on:click="setAllDetails(true)"
                class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs font-medium text-zinc-100 transition hover:bg-zinc-800"
            >
                Expand all
            </button>
            <button
                type="button"
                x-on:click="setAllDetails(false)"
                class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs font-medium text-zinc-100 transition hover:bg-zinc-800"
            >
                Collapse all
            </button>
        </div>

        <div @class([
            'rounded-lg border p-4 text-sm',
            'border-success-800 bg-success-950/30 text-success-100' => $this->isSafeToEdit,
            'border-warning-800 bg-warning-950/30 text-warning-100' => ! $this->isSafeToEdit,
        ])>
            <div class="font-medium">
                Server state: {{ $this->stateLabel }}
            </div>
            <div class="mt-1">
                {{ $this->stateMessage }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-800 bg-gray-900/70 p-4 text-sm text-gray-300">
            Restart the server after saving any Palworld settings changes. Values managed by the
            server egg startup variables should be edited from the Startup tab, not here.
        </div>

        @if ($this->hasUnsavedChanges())
            <div class="rounded-lg border border-warning-800 bg-warning-950/30 p-4 text-sm text-warning-100">
                You have unsaved changes in the editor.
            </div>
        @endif

        <details class="pal-card" data-palworld-section>
            <summary class="pal-summary cursor-pointer list-none px-4 py-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-100">Egg / Startup Variables</h2>
                        <p class="mt-1 text-sm text-gray-400">
                            These values are managed by the Pelican egg startup variables and may be
                            regenerated on server start.
                        </p>
                    </div>
                    <span class="rounded border border-gray-700 bg-gray-800 px-2 py-1 text-xs text-gray-400">
                        {{ count($this->startupVariables) }} vars
                    </span>
                </div>
            </summary>

            <div class="pal-card-header">
                @if ($this->startupVariablesAvailable)
                    <div class="pal-section">
                        <div class="pal-grid">
                        @foreach ($this->startupVariables as $variable)
                            <div class="pal-field space-y-3">
                                <div>
                                    <div class="text-sm font-medium text-gray-100">
                                        {{ $variable['name'] }}
                                    </div>
                                    @if (! empty($variable['description']))
                                        <div class="text-xs text-gray-400">
                                            {{ $variable['description'] }}
                                        </div>
                                    @endif
                                </div>

                                <div class="text-sm text-gray-200">
                                    <div class="pal-input flex items-center">
                                        {{ $this->formatStartupVariableValue($variable) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        </div>
                    </div>
                @else
                    <div class="px-4 py-4 text-sm text-gray-400">
                        No supported startup variables were detected for this server yet. This can
                        happen if the variables relation differs on this Pelican build or if the
                        server egg does not expose the expected values.
                    </div>
                @endif
            </div>
        </details>

        <section class="pal-card">
            <div class="pal-card-header px-4 py-3">
                <h2 class="text-sm font-semibold text-gray-100">PalWorldSettings.ini</h2>
                <p class="mt-1 text-sm text-gray-400">
                    Expected path: <code>{{ $this->settingsPath }}</code>
                </p>
            </div>

            <div class="space-y-5 px-4 py-4 text-sm text-gray-300">
                @if ($this->settingsFileError)
                    <div class="rounded-md border border-danger-800 bg-danger-950/30 px-3 py-2 text-danger-100">
                        Failed to read the Palworld settings file: {{ $this->settingsFileError }}
                    </div>
                @elseif (! $this->settingsFileExists)
                    <div class="rounded-md border border-warning-800 bg-warning-950/30 px-3 py-2 text-warning-100">
                        PalWorldSettings.ini was not found. Start the Palworld server once to let it
                        generate the config file, then stop the server before editing.
                    </div>
                @else
                    <div class="rounded-md border border-success-800 bg-success-950/30 px-3 py-2 text-success-100">
                        PalWorldSettings.ini was found and read successfully.
                    </div>

                    @if ($this->quickAccessItems !== [])
                        <section class="pal-card">
                            <div class="pal-card-header px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-100">Quick Access</h3>
                                        <p class="mt-1 text-xs text-gray-400">
                                            Common Palworld settings for quick edits.
                                        </p>
                                    </div>
                                    <span class="rounded border border-gray-700 bg-gray-800 px-2 py-1 text-xs text-gray-400">
                                        {{ count($this->quickAccessItems) }} fields
                                    </span>
                                </div>
                            </div>

                            <div class="pal-section">
                                <div class="pal-grid">
                                    @foreach ($this->quickAccessItems as $item)
                                        <div class="pal-field space-y-3">
                                            <div>
                                                <div class="text-sm font-medium text-gray-100">
                                                    {{ $item['label'] }}
                                                </div>
                                            </div>

                                            @if ($item['type'] === 'boolean')
                                                <label class="pal-toggle">
                                                    <input
                                                        type="checkbox"
                                                        wire:model.defer="formData.{{ $item['key'] }}"
                                                        @disabled(! $this->canSave())
                                                    >
                                                    <span class="pal-toggle-track"></span>
                                                    <span>{{ $this->formatSettingValue($this->formData[$item['key']] ?? null, $item['type']) }}</span>
                                                </label>
                                            @elseif ($item['type'] === 'enum')
                                                <select
                                                    wire:model.defer="formData.{{ $item['key'] }}"
                                                    @disabled(! $this->canSave())
                                                    class="pal-select"
                                                >
                                                    @php($options = $this->getFieldOptions($item['key']))
                                                    @foreach ($options as $option)
                                                        <option value="{{ $option }}">{{ $option }}</option>
                                                    @endforeach
                                                    @if (($this->formData[$item['key']] ?? null) !== null && ! in_array((string) ($this->formData[$item['key']] ?? ''), $options, true))
                                                        <option value="{{ (string) $this->formData[$item['key']] }}" selected>
                                                            {{ (string) $this->formData[$item['key']] }}
                                                        </option>
                                                    @endif
                                                </select>
                                            @elseif ($item['type'] === 'integer' || $item['type'] === 'number')
                                                @php($definition = $this->getFieldDefinition($item['key']))
                                                <input
                                                    type="number"
                                                    step="{{ $item['type'] === 'integer' ? '1' : ($definition['step'] ?? '0.1') }}"
                                                    @if (isset($definition['min'])) min="{{ $definition['min'] }}" @endif
                                                    @if (isset($definition['max'])) max="{{ $definition['max'] }}" @endif
                                                    wire:model.defer="formData.{{ $item['key'] }}"
                                                    @disabled(! $this->canSave())
                                                    class="pal-input"
                                                >
                                            @else
                                                <input
                                                    type="text"
                                                    wire:model.defer="formData.{{ $item['key'] }}"
                                                    @disabled(! $this->canSave())
                                                    class="pal-input"
                                                >
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    @endif

                    @if ($this->detectedEggManagedIniKeys !== [])
                        <div class="rounded-md border border-info-800 bg-info-950/30 px-3 py-2 text-info-100">
                            Some startup-managed values were detected in PalWorldSettings.ini and are intentionally
                            excluded from editing here:
                            {{ implode(', ', $this->detectedEggManagedIniKeys) }}
                        </div>
                    @endif

                    @if ($this->lastBackupPath)
                        <div class="rounded-md border border-success-800 bg-success-950/30 px-3 py-2 text-success-100">
                            Last backup created at: <code>{{ $this->lastBackupPath }}</code>
                            @if ($this->lastSavedAt)
                                <span class="ml-2">Saved at {{ $this->lastSavedAt }}.</span>
                            @endif
                        </div>
                    @endif

                    @if ($this->settingsParseError)
                        <div class="rounded-md border border-danger-800 bg-danger-950/30 px-3 py-2 text-danger-100">
                            Failed to parse the OptionSettings line: {{ $this->settingsParseError }}
                        </div>
                    @else
                        @if ($this->groupedSettings !== [])
                            @php($primaryGroupKeys = ['gameplay_rates', 'player_and_pal_rates', 'world_behaviour', 'death_and_difficulty', 'base_and_guild_limits'])
                            <div class="pal-stack">
                                @foreach ($primaryGroupKeys as $groupKey)
                                    @if (isset($this->groupedSettings[$groupKey]))
                                        @php($group = $this->groupedSettings[$groupKey])
                                        <details class="pal-card" data-palworld-section open>
                                            <summary class="pal-summary cursor-pointer list-none px-4 py-3">
                                                <div class="flex items-center justify-between gap-3">
                                                    <h3 class="text-sm font-semibold text-gray-100">{{ $group['label'] }}</h3>
                                                    <span class="rounded border border-gray-700 bg-gray-800 px-2 py-1 text-xs text-gray-400">
                                                        {{ count($group['items']) }} fields
                                                    </span>
                                                </div>
                                            </summary>

                                            <div class="pal-section border-t border-gray-800">
                                                <div class="pal-grid">
                                                @foreach ($group['items'] as $item)
                                                    <div class="pal-field space-y-3">
                                                        <div>
                                                            <div class="text-sm font-medium text-gray-100">
                                                                {{ $item['label'] }}
                                                            </div>
                                                            @if ($this->showDebugSection())
                                                                <div class="text-xs text-gray-400">
                                                                    {{ $item['key'] }}
                                                                </div>
                                                            @endif
                                                        </div>

                                                        @if ($item['type'] === 'boolean')
                                                            <label class="pal-toggle">
                                                                <input
                                                                    type="checkbox"
                                                                    wire:model.defer="formData.{{ $item['key'] }}"
                                                                    @disabled(! $this->canSave())
                                                                >
                                                                <span class="pal-toggle-track"></span>
                                                                <span>{{ $this->formatSettingValue($this->formData[$item['key']] ?? null, $item['type']) }}</span>
                                                            </label>
                                                        @elseif ($item['type'] === 'enum')
                                                            <select
                                                                wire:model.defer="formData.{{ $item['key'] }}"
                                                                @disabled(! $this->canSave())
                                                                class="pal-select"
                                                            >
                                                                @php($options = $this->getFieldOptions($item['key']))
                                                                @foreach ($options as $option)
                                                                    <option value="{{ $option }}">{{ $option }}</option>
                                                                @endforeach
                                                                @if (($this->formData[$item['key']] ?? null) !== null && ! in_array((string) ($this->formData[$item['key']] ?? ''), $options, true))
                                                                    <option value="{{ (string) $this->formData[$item['key']] }}" selected>
                                                                        {{ (string) $this->formData[$item['key']] }}
                                                                    </option>
                                                                @endif
                                                            </select>
                                                        @elseif ($item['type'] === 'integer' || $item['type'] === 'number')
                                                            @php($definition = $this->getFieldDefinition($item['key']))
                                                            <input
                                                                type="number"
                                                                step="{{ $item['type'] === 'integer' ? '1' : ($definition['step'] ?? '0.1') }}"
                                                                @if (isset($definition['min'])) min="{{ $definition['min'] }}" @endif
                                                                @if (isset($definition['max'])) max="{{ $definition['max'] }}" @endif
                                                                wire:model.defer="formData.{{ $item['key'] }}"
                                                                @disabled(! $this->canSave())
                                                                class="pal-input"
                                                            >
                                                        @else
                                                            <input
                                                                type="text"
                                                                wire:model.defer="formData.{{ $item['key'] }}"
                                                                @disabled(! $this->canSave())
                                                                class="pal-input"
                                                            >
                                                        @endif
                                                    </div>
                                                @endforeach
                                                </div>
                                            </div>
                                        </details>
                                    @endif
                                @endforeach

                                @if (isset($this->groupedSettings['advanced_present_only']))
                                    @php($advancedGroup = $this->groupedSettings['advanced_present_only'])
                                    <details class="pal-card" data-palworld-section>
                                        <summary class="pal-summary cursor-pointer list-none px-4 py-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <h3 class="text-sm font-semibold text-gray-100">{{ $advancedGroup['label'] }}</h3>
                                                    <p class="mt-1 text-xs text-gray-400">
                                                        Less common or version-specific Palworld settings.
                                                    </p>
                                                </div>
                                                <span class="rounded border border-gray-700 bg-gray-800 px-2 py-1 text-xs text-gray-400">
                                                    {{ count($advancedGroup['items']) }} fields
                                                </span>
                                            </div>
                                        </summary>

                                        <div class="pal-section border-t border-gray-800">
                                            <div class="pal-grid">
                                            @foreach ($advancedGroup['items'] as $item)
                                                <div class="pal-field space-y-3">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-100">
                                                            {{ $item['label'] }}
                                                        </div>
                                                        @if ($this->showDebugSection())
                                                            <div class="text-xs text-gray-400">
                                                                {{ $item['key'] }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    @if ($item['type'] === 'boolean')
                                                        <label class="pal-toggle">
                                                            <input
                                                                type="checkbox"
                                                                wire:model.defer="formData.{{ $item['key'] }}"
                                                                @disabled(! $this->canSave())
                                                            >
                                                            <span class="pal-toggle-track"></span>
                                                            <span>{{ $this->formatSettingValue($this->formData[$item['key']] ?? null, $item['type']) }}</span>
                                                        </label>
                                                    @elseif ($item['type'] === 'enum')
                                                        <select
                                                            wire:model.defer="formData.{{ $item['key'] }}"
                                                            @disabled(! $this->canSave())
                                                            class="pal-select"
                                                        >
                                                            @php($options = $this->getFieldOptions($item['key']))
                                                            @foreach ($options as $option)
                                                                <option value="{{ $option }}">{{ $option }}</option>
                                                            @endforeach
                                                            @if (($this->formData[$item['key']] ?? null) !== null && ! in_array((string) ($this->formData[$item['key']] ?? ''), $options, true))
                                                                <option value="{{ (string) $this->formData[$item['key']] }}" selected>
                                                                    {{ (string) $this->formData[$item['key']] }}
                                                                </option>
                                                            @endif
                                                        </select>
                                                    @elseif ($item['type'] === 'integer' || $item['type'] === 'number')
                                                        @php($definition = $this->getFieldDefinition($item['key']))
                                                        <input
                                                            type="number"
                                                            step="{{ $item['type'] === 'integer' ? '1' : ($definition['step'] ?? '0.1') }}"
                                                            @if (isset($definition['min'])) min="{{ $definition['min'] }}" @endif
                                                            @if (isset($definition['max'])) max="{{ $definition['max'] }}" @endif
                                                            wire:model.defer="formData.{{ $item['key'] }}"
                                                            @disabled(! $this->canSave())
                                                            class="pal-input"
                                                        >
                                                    @else
                                                        <input
                                                            type="text"
                                                            wire:model.defer="formData.{{ $item['key'] }}"
                                                            @disabled(! $this->canSave())
                                                            class="pal-input"
                                                        >
                                                    @endif
                                                </div>
                                            @endforeach
                                            </div>
                                        </div>
                                    </details>
                                @endif
                            </div>
                        @endif

                        @if ($this->showDebugSection())
                            <details class="pal-card" data-palworld-section>
                                <summary class="pal-summary cursor-pointer list-none px-4 py-3 text-sm font-medium text-gray-100">
                                    Advanced / Debug
                                </summary>

                                <div class="space-y-4 border-t border-gray-800 px-4 py-4">
                                    <div>
                                        <div class="mb-2 font-medium text-gray-100">Detected OptionSettings line</div>
                                        <div class="overflow-x-auto rounded-md border border-gray-700 bg-gray-800 px-3 py-2 font-mono text-xs text-gray-300">
                                            {{ $this->optionSettingsLine ?? 'OptionSettings line not detected.' }}
                                        </div>
                                    </div>

                                    @if ($this->unknownSettings !== [])
                                        <div>
                                            <div class="mb-2 font-medium text-gray-100">Unmapped settings preserved by the parser</div>
                                            <div class="rounded-md border border-gray-700 bg-gray-800 px-3 py-2">
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach (array_keys($this->unknownSettings) as $unknownKey)
                                                        <span class="rounded border border-gray-700 bg-gray-900 px-2 py-1 font-mono text-xs text-gray-300">
                                                            {{ $unknownKey }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($this->settingsFilePreview)
                                        <div>
                                            <div class="mb-2 font-medium text-gray-100">File preview</div>
                                            <pre class="overflow-x-auto rounded-md border border-gray-700 bg-gray-800 px-3 py-2 text-xs text-gray-300 whitespace-pre-wrap">{{ $this->settingsFilePreview }}</pre>
                                        </div>
                                    @endif

                                    <div>
                                        <div class="mb-2 font-medium text-gray-100">Server state diagnostics</div>
                                        <div class="grid gap-px rounded-md border border-gray-800 bg-gray-800 md:grid-cols-2">
                                            @foreach ($this->stateDiagnostics as $key => $value)
                                                <div class="bg-gray-900/70 px-3 py-2">
                                                    <div class="font-mono text-xs text-gray-400">{{ $key }}</div>
                                                    <div class="mt-1 font-mono text-xs text-gray-300 break-all">
                                                        @if (is_bool($value))
                                                            {{ $value ? 'true' : 'false' }}
                                                        @elseif ($value === null || $value === '')
                                                            null
                                                        @elseif (is_scalar($value))
                                                            {{ (string) $value }}
                                                        @else
                                                            {{ json_encode($value) }}
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </details>
                        @endif
                    @endif
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>
