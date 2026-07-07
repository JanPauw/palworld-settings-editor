<?php

namespace JanPauw\PalworldSettingsEditor\Filament\Server\Pages;

use App\Enums\SubuserPermission;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JanPauw\PalworldSettingsEditor\Services\PalworldOptionSettingsParser;
use JanPauw\PalworldSettingsEditor\Services\PalworldServerDetector;
use JanPauw\PalworldSettingsEditor\Services\PalworldSettingsFileService;
use JanPauw\PalworldSettingsEditor\Services\PalworldSettingsSchema;
use JanPauw\PalworldSettingsEditor\Services\PelicanServerStateService;
use JanPauw\PalworldSettingsEditor\Services\PelicanStartupVariableService;
use Livewire\Attributes\Locked;
use Throwable;

class PalworldSettingsPage extends Page
{
    use InteractsWithFormActions;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $slug = 'palworld-settings';

    protected static ?int $navigationSort = 30;

    // Custom view (a copy of the native server-form-page WITHOUT a page-level
    // wire:submit) — see the view file for why. The native view's wire:submit="save"
    // caught submit events bubbling up from every action modal, re-opening the Save
    // confirmation modal after any preset/reset/restart/restore/delete confirmation.
    protected string $view = 'palworld-settings-editor::filament.server.pages.palworld-settings-page';

    // Server-authoritative state — locked so the client cannot tamper with it between
    // requests (e.g. redirecting the write/backup path or forging the backup allowlist).
    // Only $formData / $fieldSearch / collapse state are client-mutable.

    #[Locked]
    public bool $isSafeToEdit = false;

    #[Locked]
    public string $stateLabel = 'Unknown';

    #[Locked]
    public string $stateMessage = 'Editing is disabled because the current server state could not be confirmed.';

    /** @var array<string, array{name: string, value: mixed, description: ?string, is_sensitive: bool}> */
    #[Locked]
    public array $startupVariables = [];

    #[Locked]
    public bool $startupVariablesAvailable = false;

    #[Locked]
    public bool $canReadStartup = false;

    #[Locked]
    public string $settingsPath = '';

    #[Locked]
    public bool $settingsFileExists = false;

    #[Locked]
    public ?string $settingsFileError = null;

    #[Locked]
    public ?string $optionSettingsLine = null;

    #[Locked]
    public ?string $settingsFilePreview = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $parsedSettings = [];

    /** @var array<string, array{label: string, items: array<int, array{key: string, label: string, value: mixed, type: string, options?: array<int, string>}>}> */
    #[Locked]
    public array $groupedSettings = [];

    /** @var array<string, mixed> */
    #[Locked]
    public array $unknownSettings = [];

    #[Locked]
    public ?string $settingsParseError = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $stateDiagnostics = [];

    /** @var array<string, mixed> */
    public array $formData = [];

    /** @var array<int, string> */
    #[Locked]
    public array $detectedEggManagedIniKeys = [];

    /** @var array<int, array{key: string, label: string, value: mixed, type: string, options?: array<int, string>}> */
    #[Locked]
    public array $quickAccessItems = [];

    /** @var array<string, string> */
    #[Locked]
    public array $startupVariableDisplayValues = [];

    /** @var array<int, array{name: string, path: string, size: mixed, modified: mixed}> */
    #[Locked]
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
            && PalworldServerDetector::isPalworldServer($server)
            && (bool) user()?->can(SubuserPermission::FileReadContent, $server);
    }

    public static function getNavigationLabel(): string
    {
        return 'Palworld Settings';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Palworld';
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

        // Startup variables are governed by the separate startup.read permission and can
        // contain secrets (passwords), so only read them when allowed, and never keep the
        // raw values in a public property (Livewire ships those to the browser).
        $this->canReadStartup = (bool) user()?->can(SubuserPermission::StartupRead, $server);

        if ($this->canReadStartup) {
            $rawStartupVariables = $startupVariableService->getVariablesForServer($server);
            $this->startupVariablesAvailable = $rawStartupVariables !== [];
            $this->startupVariableDisplayValues = $this->buildStartupVariableDisplayValues($rawStartupVariables);
            $this->startupVariables = $this->sanitizeStartupVariables($rawStartupVariables);
        }

        try {
            $this->settingsFileExists = $settingsFileService->exists($server, $this->settingsPath);

            if ($this->settingsFileExists) {
                $contents = $settingsFileService->read($server, $this->settingsPath);
                $rawLine = $settingsFileService->getOptionSettingsLine($contents);
                $this->optionSettingsLine = $rawLine === null ? null : $this->redactSecrets($rawLine);
                $this->settingsFilePreview = $this->redactSecrets(substr($contents, 0, 1500));

                if ($rawLine !== null) {
                    $this->parsedSettings = $this->redactSensitiveSettings($settingsParser->parseOptionSettingsLine($rawLine));
                    $this->groupedSettings = $this->buildGroupedSettings($settingsSchema, $this->parsedSettings);
                    $this->unknownSettings = $this->buildUnknownSettings($settingsSchema, $this->parsedSettings);
                    $this->detectedEggManagedIniKeys = $settingsSchema->getDetectedEggManagedKeys($this->parsedSettings);
                    $this->formData = $this->buildFormData($this->groupedSettings);
                    $this->quickAccessItems = $this->buildQuickAccessItems($settingsSchema, $this->parsedSettings);
                }
            }
        } catch (Throwable $throwable) {
            report($throwable);

            if ($this->optionSettingsLine !== null) {
                $this->settingsParseError = 'The OptionSettings line in PalWorldSettings.ini could not be parsed.';
            } else {
                $this->settingsFileError = 'PalWorldSettings.ini could not be read. Check the node/daemon connection and file access.';
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
        ];

        if ($this->canReadStartup) {
            $schema[] = $this->buildStartupVariablesSection();
        }

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
            $schema[] = Section::make('Search settings')
                ->description('Filter the settings below by name or key.')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('__field_search')
                        ->hiddenLabel()
                        ->placeholder('Start typing to filter, e.g. "exp", "capture", "difficulty"…')
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
                ->authorize(fn (): bool => (bool) user()?->can(SubuserPermission::FileUpdate, Filament::getTenant()))
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
                ->authorize(fn (): bool => (bool) user()?->can(SubuserPermission::FileUpdate, Filament::getTenant()))
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
                ->authorize(fn (): bool => (bool) user()?->can(SubuserPermission::FileUpdate, Filament::getTenant()))
                ->keyBindings(['mod+s'])
                ->action('writeSettings'),
            Action::make('startServer')
                ->label('Start server')
                ->icon('tabler-player-play')
                ->color('success')
                ->visible(fn (): bool => $this->isSafeToEdit && $this->settingsFileExists)
                ->authorize(fn (): bool => (bool) user()?->can(SubuserPermission::ControlStart, Filament::getTenant()))
                ->requiresConfirmation()
                ->modalHeading('Start server')
                ->modalDescription('Start the server so your saved Palworld settings are applied. Make sure you have pressed Save first.')
                ->action(fn () => $this->sendPowerAction('start')),
            Action::make('restartServer')
                ->label('Restart server')
                ->icon('tabler-reload')
                ->color('warning')
                ->visible(fn (): bool => ! $this->isSafeToEdit)
                ->authorize(fn (): bool => (bool) user()?->can(SubuserPermission::ControlRestart, Filament::getTenant()))
                ->requiresConfirmation()
                ->modalHeading('Restart server')
                ->modalDescription('Restart the server to apply changes. Palworld settings can only be edited safely while the server is stopped.')
                ->action(fn () => $this->sendPowerAction('restart')),
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
            if (! $this->valuesEqual($key, $this->parsedSettings[$key] ?? null, $this->formData[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Type-aware equality for change detection. Numeric fields come back from
     * Filament as floats (NumberStateCast) while the parsed file values are strings,
     * so numeric values are compared by float value; booleans strictly; the rest as strings.
     */
    private function valuesEqual(string $key, mixed $a, mixed $b): bool
    {
        $type = $this->settingsSchema()->getFieldDefinition($key)['type'] ?? 'string';

        if ($type === 'boolean') {
            // A toggle is a real bool while the parsed file value may be a non-canonical
            // boolean literal ("1"/"0"/"true"); coerce and compare as bools.
            $boolA = is_bool($a) ? $a : filter_var($a, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $boolB = is_bool($b) ? $b : filter_var($b, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($boolA !== null && $boolB !== null) {
                return $boolA === $boolB;
            }

            return $a === $b;
        }

        // Numeric fields hydrate to floats via Filament while the file value is a string,
        // so compare by float value — but ONLY for numeric fields, never free-text/enum
        // fields where "5" and "05" are legitimately different values.
        if (($type === 'integer' || $type === 'number') && is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return (string) $a === (string) $b;
    }

    /**
     * Coerce a form value to the schema-declared PHP type before it is serialized, so
     * numeric fields serialize as numbers and string fields keep their literal text.
     */
    private function coerceForType(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $this->settingsSchema()->getFieldDefinition($key)['type'] ?? 'string';

        return match ($type) {
            'boolean' => is_bool($value) ? $value : (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => is_numeric($value) ? (int) $value : (string) $value,
            'number' => is_numeric($value) ? (float) $value : (string) $value,
            default => (string) $value,
        };
    }

    public function writeSettings(): void
    {
        $serverStateService = app(PelicanServerStateService::class);
        $settingsFileService = app(PalworldSettingsFileService::class);
        $settingsParser = app(PalworldOptionSettingsParser::class);
        $server = Filament::getTenant();

        if (! (bool) user()?->can(SubuserPermission::FileUpdate, $server)) {
            $this->denyPermission();

            return;
        }

        if (! $serverStateService->isSafeToEdit($server)) {
            Notification::make()
                ->title('Server must be stopped')
                ->body('Stop the server before changing Palworld settings.')
                ->warning()
                ->send();

            return;
        }

        $changes = $this->getChangedFormData();

        if ($changes === []) {
            Notification::make()
                ->title('No changes to save')
                ->body('The form already matches the current PalWorldSettings.ini.')
                ->info()
                ->send();

            return;
        }

        try {
            $this->validateFormData();

            $contents = $settingsFileService->read($server, $this->settingsPath);
            $backupPath = $this->newBackupPath();

            $settingsFileService->copy($server, $this->settingsPath, $backupPath);

            $updatedContents = $settingsParser->write($contents, $changes);
            $settingsFileService->write($server, $this->settingsPath, $updatedContents);

            $rawOptionSettingsLine = $settingsFileService->getOptionSettingsLine($updatedContents);
            $this->optionSettingsLine = $rawOptionSettingsLine === null ? null : $this->redactSecrets($rawOptionSettingsLine);
            $this->parsedSettings = $this->redactSensitiveSettings($settingsParser->parse($updatedContents));
            $this->groupedSettings = $this->buildGroupedSettings($this->settingsSchema(), $this->parsedSettings);
            $this->unknownSettings = $this->buildUnknownSettings($this->settingsSchema(), $this->parsedSettings);
            $this->detectedEggManagedIniKeys = $this->settingsSchema()->getDetectedEggManagedKeys($this->parsedSettings);
            $this->formData = $this->buildFormData($this->groupedSettings);
            $this->quickAccessItems = $this->buildQuickAccessItems($this->settingsSchema(), $this->parsedSettings);
            $this->settingsFilePreview = $this->redactSecrets(substr($updatedContents, 0, 1500));
            $this->backups = $settingsFileService->listBackups($server, $this->settingsPath);
            $this->fillFormState();

            Notification::make()
                ->title('Settings saved')
                ->body('Settings saved. A backup was created and the server must be restarted before changes take effect.')
                ->success()
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Invalid settings')
                ->body(collect($exception->errors())->flatten()->take(6)->implode(' '))
                ->danger()
                ->send();
        } catch (Throwable $throwable) {
            report($throwable);

            Notification::make()
                ->title('Save failed')
                ->body('Could not save the settings. Please try again or check the server file access.')
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
        $server = Filament::getTenant();

        if (! (bool) user()?->can(SubuserPermission::FileUpdate, $server)) {
            $this->denyPermission();

            return;
        }

        if (! $this->canSave()) {
            return;
        }

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
        if (! (bool) user()?->can(SubuserPermission::FileUpdate, Filament::getTenant())) {
            $this->denyPermission();

            return;
        }

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

    /**
     * Trigger a Pelican power action (start/restart) via the daemon, so an admin
     * can apply saved settings without leaving the page. Permission-gated to match
     * the native Console page.
     */
    public function sendPowerAction(string $action): void
    {
        $server = Filament::getTenant();

        // Only start/restart are supported; map each to its exact permission so a
        // crafted request cannot run stop/kill under the start permission.
        $permission = match ($action) {
            'start' => SubuserPermission::ControlStart,
            'restart' => SubuserPermission::ControlRestart,
            default => null,
        };

        if ($permission === null) {
            return;
        }

        if (! (bool) user()?->can($permission, $server)) {
            Notification::make()
                ->title('Not permitted')
                ->body('You do not have permission to control this server\'s power state.')
                ->danger()
                ->send();

            return;
        }

        try {
            Http::daemon($server->node)
                ->post("/api/servers/{$server->uuid}/power", ['action' => $action])
                ->throw();

            // Refresh cached state so the header actions re-evaluate (a full reload is most reliable).
            $stateService = app(PelicanServerStateService::class);
            $this->isSafeToEdit = $stateService->isSafeToEdit($server);
            $this->stateLabel = $stateService->getStateLabel($server);
            $this->stateMessage = $stateService->getStatusMessage($server);

            Notification::make()
                ->title($action === 'restart' ? 'Restart requested' : 'Start requested')
                ->body('Power action sent to the server. It may take a moment to change state.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            report($throwable);

            Notification::make()
                ->title('Power action failed')
                ->body('Could not send the power action to the server. Please try again.')
                ->danger()
                ->send();
        }
    }

    public function restoreBackup(string $backupName): void
    {
        $server = Filament::getTenant();
        $serverStateService = app(PelicanServerStateService::class);
        $fileService = app(PalworldSettingsFileService::class);

        if (! (bool) user()?->can(SubuserPermission::FileUpdate, $server)) {
            $this->denyPermission();

            return;
        }

        if (! $this->isKnownBackup($backupName)) {
            $this->denyUnknownBackup();

            return;
        }

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
                $safetyCopy = $this->newBackupPath();
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
            report($throwable);

            Notification::make()
                ->title('Restore failed')
                ->body('Could not restore the backup. Please try again.')
                ->danger()
                ->send();
        }
    }

    public function deleteBackup(string $backupName): void
    {
        $server = Filament::getTenant();
        $fileService = app(PalworldSettingsFileService::class);

        if (! (bool) user()?->can(SubuserPermission::FileDelete, $server)) {
            $this->denyPermission();

            return;
        }

        if (! $this->isKnownBackup($backupName)) {
            $this->denyUnknownBackup();

            return;
        }

        try {
            $fileService->deleteBackup($server, $this->settingsPath, $backupName);
            $this->backups = $fileService->listBackups($server, $this->settingsPath);

            Notification::make()
                ->title('Backup deleted')
                ->body('Deleted ' . $backupName . '.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            report($throwable);

            Notification::make()
                ->title('Delete failed')
                ->body('Could not delete the backup. Please try again.')
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
                $rawLine = $fileService->getOptionSettingsLine($contents);
                $this->optionSettingsLine = $rawLine === null ? null : $this->redactSecrets($rawLine);
                $this->settingsFilePreview = $this->redactSecrets(substr($contents, 0, 1500));

                if ($rawLine !== null) {
                    $this->parsedSettings = $this->redactSensitiveSettings($parser->parseOptionSettingsLine($rawLine));
                    $this->groupedSettings = $this->buildGroupedSettings($schema, $this->parsedSettings);
                    $this->unknownSettings = $this->buildUnknownSettings($schema, $this->parsedSettings);
                    $this->detectedEggManagedIniKeys = $schema->getDetectedEggManagedKeys($this->parsedSettings);
                    $this->formData = $this->buildFormData($this->groupedSettings);
                    $this->quickAccessItems = $this->buildQuickAccessItems($schema, $this->parsedSettings);
                }
            }
        } catch (Throwable $throwable) {
            report($throwable);

            if ($this->optionSettingsLine !== null) {
                $this->settingsParseError = 'The OptionSettings line in PalWorldSettings.ini could not be parsed.';
            } else {
                $this->settingsFileError = 'PalWorldSettings.ini could not be read. Check the node/daemon connection and file access.';
            }
        }

        $this->backups = $fileService->listBackups($server, $this->settingsPath);
        $this->fillFormState();
    }

    private function newBackupPath(): string
    {
        // Append a short random token so two saves/restores within the same second don't
        // collide on the timestamp and overwrite each other's backup.
        return $this->settingsPath
            . '.bak-'
            . now()->format((string) config('palworld-settings-editor.backup_suffix_format', 'Ymd-His'))
            . '-' . bin2hex(random_bytes(3));
    }

    private function backupPathFor(string $backupName): string
    {
        $position = strrpos($this->settingsPath, '/');
        $directory = $position === false ? '' : substr($this->settingsPath, 0, $position);

        return ($directory === '' ? '' : $directory . '/') . $backupName;
    }

    /**
     * Only allow acting on a backup that is in the current backup list and has no
     * path separators / traversal — the name comes from the client and must never
     * be used to reach arbitrary files.
     */
    private function isKnownBackup(string $backupName): bool
    {
        if ($backupName === '' || str_contains($backupName, '/') || str_contains($backupName, '\\') || str_contains($backupName, '..')) {
            return false;
        }

        foreach ($this->backups as $backup) {
            if (($backup['name'] ?? null) === $backupName) {
                return true;
            }
        }

        return false;
    }

    private function denyPermission(): void
    {
        Notification::make()
            ->title('Not permitted')
            ->body('You do not have permission to perform this action on this server.')
            ->danger()
            ->send();
    }

    private function denyUnknownBackup(): void
    {
        Notification::make()
            ->title('Backup not found')
            ->body('That backup could not be found. Reload the page and try again.')
            ->danger()
            ->send();
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
                        ->authorize(fn (): bool => (bool) user()?->can(SubuserPermission::FileUpdate, Filament::getTenant()))
                        ->action(fn () => $this->restoreBackup($name)),
                    Action::make('delete_' . $hash)
                        ->label('Delete')
                        ->icon('tabler-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete backup')
                        ->modalDescription('Permanently delete this backup file?')
                        ->authorize(fn (): bool => (bool) user()?->can(SubuserPermission::FileDelete, Filament::getTenant()))
                        ->action(fn () => $this->deleteBackup($name)),
                ])
                    // Stable key per backup so that when the list changes after a
                    // restore/delete, Livewire morphs the surviving rows in place and
                    // keeps their action triggers bound (otherwise the next
                    // restore/delete can silently fail to open its confirmation modal).
                    ->key('backup_actions_' . $hash)
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

        if (preg_match('/\.bak-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})/', $backup['name'], $matches)) {
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

    /**
     * Mask AdminPassword / ServerPassword in a raw INI fragment so the debug section
     * cannot leak credentials the plugin masks everywhere else.
     */
    private function redactSecrets(string $text): string
    {
        return preg_replace(
            '/\b(\w*(?:Password|Token|Secret))=("[^"]*"|[^,)\r\n]*)/i',
            '$1="********"',
            $text
        ) ?? $text;
    }

    /**
     * Mask the values of sensitive keys (passwords/tokens/secrets) in a parsed settings
     * map before it is stored in a public property — Livewire dehydrates public properties
     * into the client payload. These keys are egg-managed and never editable, so masking
     * their values here has no effect on change detection or writes.
     *
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function redactSensitiveSettings(array $parsed): array
    {
        foreach ($parsed as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $parsed[$key] = '********';
            }
        }

        return $parsed;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        return str_contains($key, 'password')
            || str_contains($key, 'token')
            || str_contains($key, 'secret');
    }

    private function buildDebugSection(): Section
    {
        $components = [
            TextEntry::make('debug_option_settings')
                ->label('Detected OptionSettings line')
                ->state($this->redactSecrets($this->optionSettingsLine ?? 'OptionSettings line not detected.')),
        ];

        if ($this->unknownSettings !== []) {
            $components[] = TextEntry::make('debug_unknown_settings')
                ->label('Unmapped settings preserved by the parser')
                ->state(implode(', ', array_keys($this->unknownSettings)));
        }

        if ($this->settingsFilePreview) {
            $components[] = TextEntry::make('debug_settings_preview')
                ->label('File preview')
                ->state($this->redactSecrets($this->settingsFilePreview));
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
            'offline', 'exited' => 'success',
            'running', 'starting', 'stopping', 'restarting' => 'warning',
            'dead', 'missing', 'removing' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Replace each startup variable's raw value with its display value (masked for
     * sensitive entries) so no secret is dehydrated into the client-side Livewire state.
     *
     * @param  array<string, array{name: string, value: mixed, description: ?string, is_sensitive: bool}>  $variables
     * @return array<string, array{name: string, value: string, description: ?string, is_sensitive: bool}>
     */
    private function sanitizeStartupVariables(array $variables): array
    {
        $sanitized = [];

        foreach ($variables as $key => $variable) {
            $variable['value'] = $this->formatStartupVariableValue($variable);
            $sanitized[$key] = $variable;
        }

        return $sanitized;
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
     * Editable keys whose form value differs from the current file value, so writeSettings()
     * rewrites only what actually changed (leaving untouched entries byte-for-byte).
     *
     * @return array<string, mixed>
     */
    private function getChangedFormData(): array
    {
        $changed = [];

        foreach ($this->getEditableFieldKeys() as $key) {
            $old = $this->parsedSettings[$key] ?? null;
            $new = $this->formData[$key] ?? null;

            if ($this->valuesEqual($key, $old, $new)) {
                continue;
            }

            // Skip an emptied numeric/enum field rather than writing Field="" (an invalid
            // token the game can't parse); the current file value is left untouched.
            $type = $this->settingsSchema()->getFieldDefinition($key)['type'] ?? 'string';
            if (($new === null || $new === '') && in_array($type, ['integer', 'number', 'enum'], true)) {
                continue;
            }

            $changed[$key] = $this->coerceForType($key, $new);
        }

        return $changed;
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

            // Allow both the mount-time file value and the current form value (which a preset
            // or reset-to-defaults may have set) so a legitimate on-disk/default value outside
            // the known options isn't rejected on save.
            foreach ([$item['value'] ?? null, $this->formData[$item['key']] ?? null] as $candidate) {
                if ($candidate !== null && $candidate !== '' && ! in_array((string) $candidate, $allowed, true)) {
                    $allowed[] = (string) $candidate;
                }
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
