<?php

namespace JanPauw\PalworldSettingsEditor\Filament\Server\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use JanPauw\PalworldSettingsEditor\Services\PalworldOptionSettingsParser;
use JanPauw\PalworldSettingsEditor\Services\PalworldServerDetector;
use JanPauw\PalworldSettingsEditor\Services\PalworldSettingsFileService;
use JanPauw\PalworldSettingsEditor\Services\PalworldSettingsSchema;
use JanPauw\PalworldSettingsEditor\Services\PelicanServerStateService;
use JanPauw\PalworldSettingsEditor\Services\PelicanStartupVariableService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Throwable;

class PalworldSettingsPage extends Page
{
    use InteractsWithFormActions;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $slug = 'palworld-settings';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.server.pages.server-form-page';

    public bool $isSafeToEdit = false;

    public string $stateLabel = 'Unknown';

    public string $stateMessage = 'Editing is disabled because the current server state could not be confirmed.';

    /** @var array<string, array{name: string, value: mixed, description: ?string, is_sensitive: bool}> */
    public array $startupVariables = [];

    public bool $startupVariablesAvailable = false;

    public string $settingsPath = '';

    public bool $settingsFileExists = false;

    public ?string $settingsFileError = null;

    public ?string $optionSettingsLine = null;

    public ?string $settingsFilePreview = null;

    /** @var array<string, mixed> */
    public array $parsedSettings = [];

    /** @var array<string, array{label: string, items: array<int, array{key: string, label: string, value: mixed, type: string, options?: array<int, string>}>}> */
    public array $groupedSettings = [];

    /** @var array<string, mixed> */
    public array $unknownSettings = [];

    public ?string $settingsParseError = null;

    /** @var array<string, mixed> */
    public array $stateDiagnostics = [];

    /** @var array<string, mixed> */
    public array $formData = [];

    /** @var array<int, string> */
    public array $detectedEggManagedIniKeys = [];

    public ?string $lastBackupPath = null;

    public ?string $lastSavedAt = null;

    /** @var array<int, array{key: string, label: string, value: mixed, type: string, options?: array<int, string>}> */
    public array $quickAccessItems = [];

    /** @var array<string, string> */
    public array $startupVariableDisplayValues = [];

    /** @var array<int, array{name: string, path: string, size: mixed, modified: mixed}> */
    public array $backups = [];

    /**
     * When set, forces every collapsible section to this collapsed state
     * (used by Expand all / Collapse all). null means each section keeps its own default.
     */
    public ?bool $collapseOverride = null;

    /** Bumped by Expand all / Collapse all to force sections to re-render with the new state. */
    public int $collapseNonce = 0;

    /** Live search needle used to filter the editable settings fields by label/key. */
    public string $fieldSearch = '';

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        return parent::canAccess()
            && $server !== null
            && PalworldServerDetector::isPalworldServer($server);
    }

    public static function getNavigationLabel(): string
    {
        return 'Palworld Settings';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Palworld';
    }

    public static function getModelLabel(): string
    {
        return static::getNavigationLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::getNavigationLabel();
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function mount(
        PelicanServerStateService $serverStateService,
        PelicanStartupVariableService $startupVariableService,
        PalworldSettingsFileService $settingsFileService,
        PalworldOptionSettingsParser $settingsParser,
        PalworldSettingsSchema $settingsSchema,
    ): void {
        $server = Filament::getTenant();
        $this->settingsPath = $this->resolveSettingsPath($settingsFileService, $server);

        $this->isSafeToEdit = $serverStateService->isSafeToEdit($server);
        $this->stateLabel = $serverStateService->getStateLabel($server);
        $this->stateMessage = $serverStateService->getStatusMessage($server);
        $this->stateDiagnostics = $serverStateService->getStateDiagnostics($server);
        $this->startupVariables = $startupVariableService->getVariablesForServer($server);
        $this->startupVariablesAvailable = $this->startupVariables !== [];
        $this->startupVariableDisplayValues = $this->buildStartupVariableDisplayValues($this->startupVariables);

        try {
            $this->settingsFileExists = $settingsFileService->exists($server, $this->settingsPath);

            if ($this->settingsFileExists) {
                $contents = $settingsFileService->read($server, $this->settingsPath);
                $this->optionSettingsLine = $settingsFileService->getOptionSettingsLine($contents);
                $this->settingsFilePreview = substr($contents, 0, 1500);

                if ($this->optionSettingsLine !== null) {
                    $this->parsedSettings = $settingsParser->parseOptionSettingsLine($this->optionSettingsLine);
                    $this->groupedSettings = $this->buildGroupedSettings($settingsSchema, $this->parsedSettings);
                    $this->unknownSettings = $this->buildUnknownSettings($settingsSchema, $this->parsedSettings);
                    $this->detectedEggManagedIniKeys = $settingsSchema->getDetectedEggManagedKeys($this->parsedSettings);
                    $this->formData = $this->buildFormData($this->groupedSettings);
                    $this->quickAccessItems = $this->buildQuickAccessItems($settingsSchema, $this->parsedSettings);
                }
            }
        } catch (Throwable $throwable) {
            if ($this->optionSettingsLine !== null) {
                $this->settingsParseError = $throwable->getMessage();
            } else {
                $this->settingsFileError = $throwable->getMessage();
            }
        }

        $this->backups = $settingsFileService->listBackups($server, $this->settingsPath);

        $this->fillFormState();
    }

    private function resolveSettingsPath(PalworldSettingsFileService $settingsFileService, mixed $server): string
    {
        $configured = (string) config('palworld-settings-editor.settings_path', 'Pal/Saved/Config/LinuxServer/PalWorldSettings.ini');

        // Honour the configured (Linux) path when the file is present.
        if ($settingsFileService->exists($server, $configured)) {
            return $configured;
        }

        // Proton/Windows servers generate the config under WindowsServer instead of
        // LinuxServer (the egg's config parser switches on WINEPREFIX/proton), so fall
        // back to that location when the configured one is not found.
        $windows = str_replace('LinuxServer', 'WindowsServer', $configured);
        if ($windows !== $configured && $settingsFileService->exists($server, $windows)) {
            return $windows;
        }

        return $configured;
    }

    /**
     * @return Component[]
     */
    public function getFormSchema(): array
    {
        $schema = [
            $this->buildStatusSection(),
            $this->buildStartupVariablesSection(),
        ];

        if ($this->settingsFileError !== null) {
            $schema[] = $this->buildNoticeSection('settings_file_error', 'Read error', $this->settingsFileError);
        } elseif (! $this->settingsFileExists) {
            $schema[] = $this->buildNoticeSection(
                'settings_file_missing',
                'Status',
                'PalWorldSettings.ini was not found. Start the Palworld server once to generate it, then stop the server before editing.'
            );
        } elseif ($this->settingsParseError !== null) {
            $schema[] = $this->buildNoticeSection('settings_parse_error', 'Parse error', $this->settingsParseError);
        } else {
            $schema[] = Section::make('field_search')
                ->hiddenLabel()
                ->columnSpanFull()
                ->schema([
                    TextInput::make('__field_search')
                        ->hiddenLabel()
                        ->placeholder('Search settings by name or key…')
                        ->prefixIcon('tabler-search')
                        ->columnSpanFull()
                        ->live(debounce: 300)
                        ->dehydrated(false)
                        ->afterStateUpdated(fn ($state) => $this->fieldSearch = (string) ($state ?? '')),
                ]);

            if ($this->quickAccessItems !== []) {
                $schema[] = Section::make('Quick Access')
                    ->description('Common Palworld settings for quick edits.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed($this->editableCollapsed(false))
                    ->key($this->editableSectionKey('quick-access'))
                    ->hiddenWhenAllChildComponentsHidden()
                    ->schema($this->buildEditableFieldComponents($this->quickAccessItems));
            }

            foreach (['gameplay_rates', 'player_and_pal_rates', 'world_behaviour', 'death_and_difficulty', 'base_and_guild_limits'] as $groupKey) {
                if (! isset($this->groupedSettings[$groupKey])) {
                    continue;
                }

                $group = $this->groupedSettings[$groupKey];

                $schema[] = Section::make($group['label'])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed($this->editableCollapsed(false))
                    ->key($this->editableSectionKey($groupKey))
                    ->hiddenWhenAllChildComponentsHidden()
                    ->schema($this->buildEditableFieldComponents($group['items']));
            }

            if (isset($this->groupedSettings['advanced_present_only'])) {
                $advancedGroup = $this->groupedSettings['advanced_present_only'];

                $schema[] = Section::make($advancedGroup['label'])
                    ->description('Less common or version-specific Palworld settings.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed($this->editableCollapsed(true))
                    ->key($this->editableSectionKey('advanced'))
                    ->hiddenWhenAllChildComponentsHidden()
                    ->schema($this->buildEditableFieldComponents($advancedGroup['items']));
            }
        }

        $schema[] = $this->buildBackupsSection();

        if ($this->showDebugSection()) {
            $schema[] = $this->buildDebugSection();
        }

        return $schema;
    }

    protected function getFormStatePath(): ?string
    {
        return 'formData';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('expandAll')
                ->label('Expand all sections')
                ->tooltip('Expand all sections')
                ->iconButton()
                ->icon('tabler-chevrons-down')
                ->color('gray')
                ->action('expandAll'),
            Action::make('collapseAll')
                ->label('Collapse all sections')
                ->tooltip('Collapse all sections')
                ->iconButton()
                ->icon('tabler-chevrons-up')
                ->color('gray')
                ->action('collapseAll'),
            Action::make('applyPreset')
                ->label('Apply preset')
                ->tooltip('Apply a preset')
                ->iconButton()
                ->icon('tabler-wand')
                ->color('gray')
                ->disabled(fn (): bool => ! $this->canSave())
                ->modalHeading('Apply a preset')
                ->modalDescription('Fill the form with a themed set of values. Only settings present in this server\'s file are changed, and nothing is written until you press Save.')
                ->modalSubmitActionLabel('Apply preset')
                ->schema([
                    Select::make('preset')
                        ->label('Preset')
                        ->options($this->getPresetOptions())
                        ->required()
                        ->native(false)
                        ->helperText('Pick a play-style. You can still tweak individual values afterwards.'),
                ])
                ->action(fn (array $data) => $this->applyPreset((string) ($data['preset'] ?? ''))),
            Action::make('resetDefaults')
                ->label('Reset to defaults')
                ->tooltip('Reset to Palworld defaults')
                ->iconButton()
                ->icon('tabler-restore')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Reset to Palworld defaults')
                ->modalDescription('Fill the form with Palworld default values. Nothing is written until you press Save.')
                ->disabled(fn (): bool => ! $this->canSave())
                ->action('resetToDefaults'),
            Action::make('resetChanges')
                ->label('Reset changes')
                ->icon('tabler-arrow-back')
                ->color('gray')
                ->disabled(fn (): bool => ! $this->hasUnsavedChanges())
                ->action('resetChanges'),
            Action::make('save')
                ->label('Save')
                ->icon('tabler-device-floppy')
                ->color('primary')
                ->disabled(fn (): bool => ! $this->canSave())
                ->keyBindings(['mod+s'])
                ->requiresConfirmation()
                ->modalHeading('Review changes before saving')
                ->modalDescription('These settings will be written to PalWorldSettings.ini. A timestamped backup is created first.')
                ->modalSubmitActionLabel('Save changes')
                ->modalContent(fn (): HtmlString => new HtmlString($this->renderSaveDiff()))
                ->action('save'),
        ];
    }

    public function formatStartupVariableValue(array $variable): string
    {
        if ($variable['value'] === null || $variable['value'] === '') {
            return 'Not set';
        }

        if ($variable['is_sensitive']) {
            return '********';
        }

        if (is_bool($variable['value'])) {
            return $variable['value'] ? 'True' : 'False';
        }

        return (string) $variable['value'];
    }

    public function canSave(): bool
    {
        return $this->isSafeToEdit && $this->settingsFileExists && $this->settingsParseError === null;
    }

    public function hasUnsavedChanges(): bool
    {
        foreach ($this->getEditableFieldKeys() as $key) {
            if (($this->parsedSettings[$key] ?? null) !== ($this->formData[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Escaped HTML change list for the Save confirmation modal: each editable key
     * that differs from the current file value, shown as "Label: old -> new".
     */
    public function renderSaveDiff(): string
    {
        $schema = $this->settingsSchema();
        $rows = [];

        foreach ($this->getEditableFieldKeys() as $key) {
            $old = $this->parsedSettings[$key] ?? null;
            $new = $this->formData[$key] ?? null;

            if ($old === $new) {
                continue;
            }

            $label = $schema->getFieldDefinition($key)['label'] ?? $key;

            $rows[] = sprintf(
                '<li><span class="font-medium">%s</span>: '
                . '<span class="line-through opacity-70">%s</span> '
                . '<span aria-hidden="true">&rarr;</span> '
                . '<span class="font-semibold">%s</span></li>',
                e($label),
                e($this->formatDiffValue($old)),
                e($this->formatDiffValue($new)),
            );
        }

        if ($rows === []) {
            return '<p class="text-sm text-gray-500 dark:text-gray-400">No changes to save.</p>';
        }

        return '<ul class="list-disc space-y-1 ps-5 text-sm">' . implode('', $rows) . '</ul>';
    }

    private function formatDiffValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(unset)';
        }

        if (is_bool($value)) {
            return $value ? 'True' : 'False';
        }

        return (string) $value;
    }

    public function save(): void
    {
        $serverStateService = app(PelicanServerStateService::class);
        $settingsFileService = app(PalworldSettingsFileService::class);
        $settingsParser = app(PalworldOptionSettingsParser::class);
        $server = Filament::getTenant();

        if (! $serverStateService->isSafeToEdit($server)) {
            Notification::make()
                ->title('Server must be stopped')
                ->body('Stop the server before changing Palworld settings.')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->validateFormData();

            $contents = $settingsFileService->read($server, $this->settingsPath);
            $backupPath = $this->settingsPath . '.bak-' . now()->format((string) config('palworld-settings-editor.backup_suffix_format', 'Ymd-His'));

            $settingsFileService->copy($server, $this->settingsPath, $backupPath);
            $this->lastBackupPath = $backupPath;
            $this->lastSavedAt = now()->toDateTimeString();

            $updatedContents = $settingsParser->write($contents, $this->getEditableFormData());
            $settingsFileService->write($server, $this->settingsPath, $updatedContents);

            $this->optionSettingsLine = $settingsFileService->getOptionSettingsLine($updatedContents);
            $this->parsedSettings = $settingsParser->parse($updatedContents);
            $this->groupedSettings = $this->buildGroupedSettings($this->settingsSchema(), $this->parsedSettings);
            $this->unknownSettings = $this->buildUnknownSettings($this->settingsSchema(), $this->parsedSettings);
            $this->detectedEggManagedIniKeys = $this->settingsSchema()->getDetectedEggManagedKeys($this->parsedSettings);
            $this->formData = $this->buildFormData($this->groupedSettings);
            $this->quickAccessItems = $this->buildQuickAccessItems($this->settingsSchema(), $this->parsedSettings);
            $this->settingsFilePreview = substr($updatedContents, 0, 1500);
            $this->backups = $settingsFileService->listBackups($server, $this->settingsPath);
            $this->fillFormState();

            Notification::make()
                ->title('Settings saved')
                ->body('Settings saved. A backup was created and the server must be restarted before changes take effect.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Save failed')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetChanges(): void
    {
        $this->formData = $this->buildFormData($this->groupedSettings);
        $this->fillFormState();

        Notification::make()
            ->title('Changes reset')
            ->body('Unsaved edits were discarded and the form was restored from the current file.')
            ->success()
            ->send();
    }

    public function expandAll(): void
    {
        $this->collapseOverride = false;
        $this->collapseNonce++;
    }

    public function collapseAll(): void
    {
        $this->collapseOverride = true;
        $this->collapseNonce++;
    }

    public function resetToDefaults(): void
    {
        if (! $this->canSave()) {
            return;
        }

        $server = Filament::getTenant();
        $fileService = app(PalworldSettingsFileService::class);
        $parser = app(PalworldOptionSettingsParser::class);
        $schema = $this->settingsSchema();

        // Prefer the game's shipped DefaultPalWorldSettings.ini for accurate defaults,
        // falling back to the reference defaults baked into the schema.
        $fileDefaults = [];
        foreach (['DefaultPalWorldSettings.ini', 'Pal/Binaries/Linux/DefaultPalWorldSettings.ini'] as $candidate) {
            try {
                if ($fileService->exists($server, $candidate)) {
                    $fileDefaults = $parser->parse($fileService->read($server, $candidate));
                    break;
                }
            } catch (Throwable) {
                // Ignore and fall back to schema defaults.
            }
        }

        foreach ($this->getEditableFieldKeys() as $key) {
            $this->formData[$key] = array_key_exists($key, $fileDefaults)
                ? $fileDefaults[$key]
                : $schema->getDefaultValue($key, $this->formData[$key] ?? null);
        }

        $this->fillFormState();

        Notification::make()
            ->title('Defaults loaded')
            ->body('The form was filled with Palworld default values. Review the changes and press Save to apply them.')
            ->success()
            ->send();
    }

    public function applyPreset(string $preset): void
    {
        if (! $this->canSave()) {
            return;
        }

        $schema = $this->settingsSchema();
        $presets = $schema->getPresets();

        if ($preset === '' || ! isset($presets[$preset])) {
            return;
        }

        if ($preset === 'normal') {
            $values = [];
            foreach ($this->getEditableFieldKeys() as $key) {
                $values[$key] = $schema->getDefaultValue($key, $this->formData[$key] ?? null);
            }
        } else {
            $values = $presets[$preset]['values'];
        }

        // Only touch keys already in the form (present in this file + editable).
        foreach ($values as $key => $value) {
            if (array_key_exists($key, $this->formData)) {
                $this->formData[$key] = $value;
            }
        }

        $this->fillFormState();

        Notification::make()
            ->title('Preset applied')
            ->body('Applied the "' . $presets[$preset]['label'] . '" preset. Review the values and press Save to write them.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, string>
     */
    public function getPresetOptions(): array
    {
        $options = [];

        foreach ($this->settingsSchema()->getPresets() as $id => $preset) {
            $options[$id] = $preset['label'];
        }

        return $options;
    }

    public function restoreBackup(string $backupName): void
    {
        $server = Filament::getTenant();
        $serverStateService = app(PelicanServerStateService::class);
        $fileService = app(PalworldSettingsFileService::class);

        if (! $serverStateService->isSafeToEdit($server)) {
            Notification::make()
                ->title('Server must be stopped')
                ->body('Stop the server before restoring a backup.')
                ->warning()
                ->send();

            return;
        }

        try {
            $backupPath = $this->backupPathFor($backupName);
            $backupContents = $fileService->read($server, $backupPath);

            // Snapshot the current file before overwriting it, then restore.
            if ($fileService->exists($server, $this->settingsPath)) {
                $safetyCopy = $this->settingsPath . '.bak-' . now()->format((string) config('palworld-settings-editor.backup_suffix_format', 'Ymd-His'));
                $fileService->copy($server, $this->settingsPath, $safetyCopy);
            }

            $fileService->write($server, $this->settingsPath, $backupContents);

            $this->reloadFromFile($server);

            Notification::make()
                ->title('Backup restored')
                ->body('Restored ' . $backupName . '. The current file was backed up first. Restart the server to apply.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Restore failed')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deleteBackup(string $backupName): void
    {
        $server = Filament::getTenant();
        $fileService = app(PalworldSettingsFileService::class);

        try {
            $fileService->deleteBackup($server, $this->settingsPath, $backupName);
            $this->backups = $fileService->listBackups($server, $this->settingsPath);

            Notification::make()
                ->title('Backup deleted')
                ->body('Deleted ' . $backupName . '.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Delete failed')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    private function reloadFromFile(mixed $server): void
    {
        $fileService = app(PalworldSettingsFileService::class);
        $parser = app(PalworldOptionSettingsParser::class);
        $schema = $this->settingsSchema();

        $this->settingsFileError = null;
        $this->settingsParseError = null;
        $this->optionSettingsLine = null;
        $this->parsedSettings = [];
        $this->groupedSettings = [];
        $this->unknownSettings = [];
        $this->detectedEggManagedIniKeys = [];
        $this->quickAccessItems = [];
        $this->formData = [];

        try {
            $this->settingsFileExists = $fileService->exists($server, $this->settingsPath);

            if ($this->settingsFileExists) {
                $contents = $fileService->read($server, $this->settingsPath);
                $this->optionSettingsLine = $fileService->getOptionSettingsLine($contents);
                $this->settingsFilePreview = substr($contents, 0, 1500);

                if ($this->optionSettingsLine !== null) {
                    $this->parsedSettings = $parser->parseOptionSettingsLine($this->optionSettingsLine);
                    $this->groupedSettings = $this->buildGroupedSettings($schema, $this->parsedSettings);
                    $this->unknownSettings = $this->buildUnknownSettings($schema, $this->parsedSettings);
                    $this->detectedEggManagedIniKeys = $schema->getDetectedEggManagedKeys($this->parsedSettings);
                    $this->formData = $this->buildFormData($this->groupedSettings);
                    $this->quickAccessItems = $this->buildQuickAccessItems($schema, $this->parsedSettings);
                }
            }
        } catch (Throwable $throwable) {
            if ($this->optionSettingsLine !== null) {
                $this->settingsParseError = $throwable->getMessage();
            } else {
                $this->settingsFileError = $throwable->getMessage();
            }
        }

        $this->backups = $fileService->listBackups($server, $this->settingsPath);
        $this->fillFormState();
    }

    private function backupPathFor(string $backupName): string
    {
        $position = strrpos($this->settingsPath, '/');
        $directory = $position === false ? '' : substr($this->settingsPath, 0, $position);

        return ($directory === '' ? '' : $directory . '/') . $backupName;
    }

    /**
     * @param  array<string, mixed>  $parsedSettings
     * @return array<string, array{label: string, items: array<int, array{key: string, label: string, value: mixed, type: string, options?: array<int, string>}>}>
     */
    private function buildGroupedSettings(PalworldSettingsSchema $schema, array $parsedSettings): array
    {
        $groups = [];

        foreach ($schema->getEditableGroups() as $groupKey => $group) {
            $items = [];

            foreach ($group['fields'] as $fieldKey => $field) {
                if (! array_key_exists($fieldKey, $parsedSettings)) {
                    continue;
                }

                $items[] = [
                    'key' => $fieldKey,
                    'label' => $field['label'],
                    'value' => $parsedSettings[$fieldKey],
                    'type' => $field['type'],
                    'options' => $field['options'] ?? [],
                ];
            }

            if ($items !== []) {
                $groups[$groupKey] = [
                    'label' => $group['label'],
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $parsedSettings
     * @return array<string, mixed>
     */
    private function buildUnknownSettings(PalworldSettingsSchema $schema, array $parsedSettings): array
    {
        $knownKeys = array_merge(
            $schema->getAllKnownFieldKeys(),
            $schema->getEggManagedIniKeys()
        );

        $unknown = [];

        foreach ($parsedSettings as $key => $value) {
            if (in_array($key, $knownKeys, true)) {
                continue;
            }

            $unknown[$key] = $value;
        }

        return $unknown;
    }

    /**
     * @param  array<string, array{label: string, items: array<int, array{key: string, label: string, value: mixed, type: string, options?: array<int, string>}>}>  $groupedSettings
     * @return array<string, mixed>
     */
    private function buildFormData(array $groupedSettings): array
    {
        $formData = [];

        foreach ($groupedSettings as $group) {
            foreach ($group['items'] as $item) {
                $formData[$item['key']] = $item['value'];
            }
        }

        return $formData;
    }

    /**
     * @param  array<string, mixed>  $parsedSettings
     * @return array<int, array{key: string, label: string, value: mixed, type: string, options?: array<int, string>}>
     */
    private function buildQuickAccessItems(PalworldSettingsSchema $schema, array $parsedSettings): array
    {
        $items = [];

        foreach ($schema->getQuickAccessFieldKeys() as $fieldKey) {
            if (! array_key_exists($fieldKey, $parsedSettings)) {
                continue;
            }

            $definition = $schema->getFieldDefinition($fieldKey);
            if ($definition === null) {
                continue;
            }

            $items[] = [
                'key' => $fieldKey,
                'label' => $definition['label'],
                'value' => $parsedSettings[$fieldKey],
                'type' => $definition['type'],
                'options' => $definition['options'] ?? [],
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array{key: string, label: string, value: mixed, type: string, options?: array<int, string>}>  $items
     * @return Component[]
     */
    private function buildEditableFieldComponents(array $items): array
    {
        $components = [];

        foreach ($items as $item) {
            $components[] = $this->buildEditableFieldComponent($item);
        }

        return $components;
    }

    /**
     * @param  array{key: string, label: string, value: mixed, type: string, options?: array<int, string>}  $item
     */
    private function buildEditableFieldComponent(array $item): Component
    {
        $definition = $this->settingsSchema()->getFieldDefinition($item['key']) ?? [];
        $description = $this->settingsSchema()->getFieldDescription($item['key']);
        $tooltip = $this->settingsSchema()->getFieldTooltip($item['key']);
        $visible = fn (): bool => $this->matchesSearch($item['key'], $item['label']);

        if ($item['type'] === 'boolean') {
            return Toggle::make($item['key'])
                ->label($item['label'])
                ->helperText($description)
                ->hintIcon('tabler-code', tooltip: $tooltip)
                ->hintColor('info')
                ->visible($visible)
                ->disabled(fn (): bool => ! $this->canSave());
        }

        if ($item['type'] === 'enum') {
            $options = $item['options'] ?? [];
            $currentValue = $this->formData[$item['key']] ?? null;

            if ($currentValue !== null && ! in_array((string) $currentValue, $options, true)) {
                $options[] = (string) $currentValue;
            }

            return Select::make($item['key'])
                ->label($item['label'])
                ->helperText($description)
                ->hintIcon('tabler-code', tooltip: $tooltip)
                ->hintColor('info')
                ->options(array_combine($options, $options))
                ->visible($visible)
                ->disabled(fn (): bool => ! $this->canSave());
        }

        $input = TextInput::make($item['key'])
            ->label($item['label'])
            ->helperText($description)
            ->hintIcon('tabler-code', tooltip: $tooltip)
            ->hintColor('info')
            ->visible($visible)
            ->disabled(fn (): bool => ! $this->canSave());

        if ($item['type'] === 'integer') {
            $input->numeric()->step(1);
        } elseif ($item['type'] === 'number') {
            $input->numeric()->step((string) ($definition['step'] ?? '0.1'));
        }

        if (isset($definition['min'])) {
            $input->minValue($definition['min']);
        }

        if (isset($definition['max'])) {
            $input->maxValue($definition['max']);
        }

        return $input;
    }

    private function buildStatusSection(): Section
    {
        return Section::make('Server Status')
            ->description($this->stateMessage)
            ->columns(2)
            ->columnSpanFull()
            ->schema([
                TextEntry::make('server_state')
                    ->label('Current state')
                    ->state($this->stateLabel)
                    ->badge()
                    ->color($this->getStateColor()),
                TextEntry::make('editing_state')
                    ->label('Editing')
                    ->state($this->isSafeToEdit ? 'Enabled' : 'Locked')
                    ->badge()
                    ->color($this->isSafeToEdit ? 'success' : 'warning'),
            ]);
    }

    private function buildStartupVariablesSection(): Section
    {
        $components = [];

        if ($this->startupVariablesAvailable) {
            foreach ($this->startupVariables as $variable) {
                $components[] = TextInput::make('startupVariableDisplayValues.' . $variable['name'])
                    ->label($variable['name'])
                    ->helperText($variable['description'] ?: null)
                    ->hintIcon('tabler-code', tooltip: $variable['name'])
                    ->hintColor('info')
                    ->disabled();
            }
        } else {
            $components[] = TextEntry::make('startup_variables_unavailable')
                ->hiddenLabel()
                ->columnSpanFull()
                ->state('No supported startup variables were detected for this server yet. This can happen if the variables relation differs on this Pelican build or if the server egg does not expose the expected values.');
        }

        if ($this->detectedEggManagedIniKeys !== []) {
            $components[] = TextEntry::make('detected_egg_managed')
                ->label('Managed in PalWorldSettings.ini by the egg')
                ->columnSpanFull()
                ->state(implode(', ', $this->detectedEggManagedIniKeys))
                ->helperText('These keys are present in the file but excluded from editing here because the egg may overwrite them on start.');
        }

        return Section::make('Egg / Startup Variables')
            ->description('These values are managed by the Pelican egg startup variables and may be regenerated on server start. Edit them from the Startup tab.')
            ->columns(2)
            ->columnSpanFull()
            ->collapsible()
            ->collapsed($this->resolveCollapsed(true))
            ->key($this->sectionKey('startup-vars'))
            ->schema($components);
    }

    private function buildBackupsSection(): Section
    {
        $components = [];

        if ($this->backups === []) {
            $components[] = TextEntry::make('backups_none')
                ->hiddenLabel()
                ->columnSpanFull()
                ->state('No backups yet. A timestamped copy of PalWorldSettings.ini is created automatically before every save.');
        } else {
            foreach ($this->backups as $backup) {
                $name = $backup['name'];
                $hash = md5($name);

                $components[] = TextEntry::make('backup_' . $hash)
                    ->label($name)
                    ->state($this->formatBackupMeta($backup))
                    ->columnSpan(1);

                $components[] = Actions::make([
                    Action::make('restore_' . $hash)
                        ->label('Restore')
                        ->icon('tabler-arrow-back-up')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Restore backup')
                        ->modalDescription('Restore this backup over the current PalWorldSettings.ini? The current file is backed up first. The server must be stopped.')
                        ->disabled(fn (): bool => ! $this->isSafeToEdit)
                        ->action(fn () => $this->restoreBackup($name)),
                    Action::make('delete_' . $hash)
                        ->label('Delete')
                        ->icon('tabler-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete backup')
                        ->modalDescription('Permanently delete this backup file?')
                        ->action(fn () => $this->deleteBackup($name)),
                ])
                    ->columnSpan(1);
            }
        }

        return Section::make('Backups')
            ->description('Timestamped copies of PalWorldSettings.ini created before each save. Restore rolls a backup back into place (after backing up the current file).')
            ->columns(2)
            ->columnSpanFull()
            ->collapsible()
            ->collapsed($this->resolveCollapsed(true))
            ->key($this->sectionKey('backups'))
            ->schema($components);
    }

    private function resolveCollapsed(bool $default): bool
    {
        return $this->collapseOverride ?? $default;
    }

    private function sectionKey(string $id): string
    {
        return 'palworld-section-' . $id . '-' . $this->collapseNonce;
    }

    /**
     * Collapse state for the editable settings sections: force-expand while a
     * search is active so matches are visible, otherwise honour expand/collapse-all
     * and the section's own default.
     */
    private function editableCollapsed(bool $default): bool
    {
        if ($this->searchActive()) {
            return false;
        }

        return $this->collapseOverride ?? $default;
    }

    private function editableSectionKey(string $id): string
    {
        return $this->sectionKey($id) . '-' . ($this->searchActive() ? 's' : 'n');
    }

    private function searchActive(): bool
    {
        return trim($this->fieldSearch) !== '';
    }

    private function matchesSearch(string $key, string $label): bool
    {
        if (! $this->searchActive()) {
            return true;
        }

        $needle = mb_strtolower(trim($this->fieldSearch));

        return str_contains(mb_strtolower($label), $needle)
            || str_contains(mb_strtolower($key), $needle);
    }

    /**
     * @param  array{name: string, path: string, size: mixed, modified: mixed}  $backup
     */
    private function formatBackupMeta(array $backup): string
    {
        $parts = [];

        if (preg_match('/\.bak-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})$/', $backup['name'], $matches)) {
            $parts[] = 'Created ' . "{$matches[1]}-{$matches[2]}-{$matches[3]} {$matches[4]}:{$matches[5]}:{$matches[6]}";
        }

        if (isset($backup['size']) && is_numeric($backup['size'])) {
            $parts[] = $this->formatBytes((int) $backup['size']);
        }

        return $parts === [] ? 'Backup file' : implode('  ·  ', $parts);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KiB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MiB';
    }

    private function buildNoticeSection(string $key, string $label, string $message): Section
    {
        return Section::make('PalWorldSettings.ini')
            ->description('Expected path: ' . $this->settingsPath)
            ->columnSpanFull()
            ->schema([
                TextEntry::make($key)
                    ->label($label)
                    ->state($message),
            ]);
    }

    private function buildDebugSection(): Section
    {
        $components = [
            TextEntry::make('debug_option_settings')
                ->label('Detected OptionSettings line')
                ->state($this->optionSettingsLine ?? 'OptionSettings line not detected.'),
        ];

        if ($this->unknownSettings !== []) {
            $components[] = TextEntry::make('debug_unknown_settings')
                ->label('Unmapped settings preserved by the parser')
                ->state(implode(', ', array_keys($this->unknownSettings)));
        }

        if ($this->settingsFilePreview) {
            $components[] = TextEntry::make('debug_settings_preview')
                ->label('File preview')
                ->state($this->settingsFilePreview);
        }

        foreach ($this->stateDiagnostics as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null || $value === '') {
                $value = 'null';
            } elseif (! is_scalar($value)) {
                $value = json_encode($value);
            } else {
                $value = (string) $value;
            }

            $components[] = TextEntry::make('diagnostic_' . md5($key))
                ->label($key)
                ->state($value);
        }

        return Section::make('Advanced / Debug')
            ->columns(2)
            ->columnSpanFull()
            ->collapsible()
            ->collapsed($this->resolveCollapsed(true))
            ->key($this->sectionKey('debug'))
            ->schema($components);
    }

    /**
     * @return array<int, string>
     */
    private function getEditableFieldKeys(): array
    {
        $keys = [];

        foreach ($this->groupedSettings as $group) {
            foreach ($group['items'] as $item) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }

    private function getStateColor(): string
    {
        return match (strtolower($this->stateLabel)) {
            'offline', 'stopped' => 'success',
            'running', 'starting', 'stopping' => 'warning',
            default => 'gray',
        };
    }

    /**
     * @param  array<string, array{name: string, value: mixed, description: ?string, is_sensitive: bool}>  $variables
     * @return array<string, string>
     */
    private function buildStartupVariableDisplayValues(array $variables): array
    {
        $displayValues = [];

        foreach ($variables as $variable) {
            $displayValues[$variable['name']] = $this->formatStartupVariableValue($variable);
        }

        return $displayValues;
    }

    private function fillFormState(): void
    {
        $this->form->fill(array_merge(
            $this->formData,
            [
                'startupVariableDisplayValues' => $this->startupVariableDisplayValues,
                '__field_search' => $this->fieldSearch,
            ],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function getEditableFormData(): array
    {
        $editable = [];

        foreach ($this->getEditableFieldKeys() as $key) {
            $editable[$key] = $this->formData[$key] ?? null;
        }

        return $editable;
    }

    private function validateFormData(): void
    {
        $rules = [];

        foreach ($this->groupedSettings as $group) {
            foreach ($group['items'] as $item) {
                $ruleKey = 'formData.' . $item['key'];
                $rules[$ruleKey] = $this->buildValidationRules($item);
            }
        }

        Validator::make(
            ['formData' => $this->formData],
            $rules
        )->validate();
    }

    /**
     * @param  array{key: string, label: string, value: mixed, type: string, options?: array<int, string>}  $item
     * @return array<int, string>
     */
    private function buildValidationRules(array $item): array
    {
        $rules = ['nullable'];
        $definition = $this->settingsSchema()->getFieldDefinition($item['key']) ?? [];

        if ($item['type'] === 'boolean') {
            $rules[] = 'boolean';

            return $rules;
        }

        if ($item['type'] === 'integer') {
            $rules[] = 'integer';
            if (isset($definition['min'])) {
                $rules[] = 'min:' . $definition['min'];
            }
            if (isset($definition['max'])) {
                $rules[] = 'max:' . $definition['max'];
            }

            return $rules;
        }

        if ($item['type'] === 'number') {
            $rules[] = 'numeric';
            if (isset($definition['min'])) {
                $rules[] = 'min:' . $definition['min'];
            }
            if (isset($definition['max'])) {
                $rules[] = 'max:' . $definition['max'];
            }

            return $rules;
        }

        if ($item['type'] === 'enum') {
            $allowed = $item['options'] ?? [];
            if ($item['value'] !== null && ! in_array((string) $item['value'], $allowed, true)) {
                $allowed[] = (string) $item['value'];
            }

            if ($allowed !== []) {
                $rules[] = 'in:' . implode(',', $allowed);
            }
        }

        return $rules;
    }

    private function settingsSchema(): PalworldSettingsSchema
    {
        return app(PalworldSettingsSchema::class);
    }

    public function showDebugSection(): bool
    {
        return (bool) config('palworld-settings-editor.show_debug_section', false);
    }
}
