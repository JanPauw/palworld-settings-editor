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
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use JanPauw\PalworldSettingsEditor\Services\PalworldOptionSettingsParser;
use JanPauw\PalworldSettingsEditor\Services\PalworldServerDetector;
use JanPauw\PalworldSettingsEditor\Services\PalworldSettingsFileService;
use JanPauw\PalworldSettingsEditor\Services\PalworldSettingsSchema;
use JanPauw\PalworldSettingsEditor\Services\PelicanServerStateService;
use JanPauw\PalworldSettingsEditor\Services\PelicanStartupVariableService;
use Illuminate\Support\Facades\Validator;
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
        $this->settingsPath = (string) config('palworld-settings-editor.settings_path');

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

        $this->fillFormState();
    }

    /**
     * @return Component[]
     */
    public function getFormSchema(): array
    {
        $schema = [
            $this->buildStatusSection(),
            $this->buildGuidanceSection(),
        ];

        if ($this->hasUnsavedChanges()) {
            $schema[] = Section::make('Pending Changes')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('pending_changes_notice')
                        ->hiddenLabel()
                        ->state('You have unsaved changes in the editor.'),
                ]);
        }

        $schema[] = $this->buildStartupVariablesSection();

        if ($this->settingsFileError !== null) {
            $schema[] = Section::make('PalWorldSettings.ini')
                ->description('Expected path: ' . $this->settingsPath)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('settings_file_error')
                        ->label('Read error')
                        ->state($this->settingsFileError),
                ]);

            return $schema;
        }

        if (! $this->settingsFileExists) {
            $schema[] = Section::make('PalWorldSettings.ini')
                ->description('Expected path: ' . $this->settingsPath)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('settings_file_missing')
                        ->label('Status')
                        ->state('PalWorldSettings.ini was not found. Start the Palworld server once to generate it, then stop the server before editing.'),
                ]);

            return $schema;
        }

        if ($this->settingsParseError !== null) {
            $schema[] = Section::make('PalWorldSettings.ini')
                ->description('Expected path: ' . $this->settingsPath)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('settings_parse_error')
                        ->label('Parse error')
                        ->state($this->settingsParseError),
                ]);

            return $schema;
        }

        $schema[] = $this->buildSettingsOverviewSection();

        if ($this->quickAccessItems !== []) {
            $schema[] = Section::make('Quick Access')
                ->description('Common Palworld settings for quick edits.')
                ->columns(2)
                ->columnSpanFull()
                ->extraAttributes(['class' => 'palworld-settings-grid-section'])
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
                ->extraAttributes(['class' => 'palworld-settings-grid-section'])
                ->schema($this->buildEditableFieldComponents($group['items']));
        }

        if (isset($this->groupedSettings['advanced_present_only'])) {
            $advancedGroup = $this->groupedSettings['advanced_present_only'];

            $schema[] = Section::make($advancedGroup['label'])
                ->description('Less common or version-specific Palworld settings.')
                ->columns(2)
                ->columnSpanFull()
                ->collapsible()
                ->collapsed()
                ->extraAttributes(['class' => 'palworld-settings-grid-section'])
                ->schema($this->buildEditableFieldComponents($advancedGroup['items']));
        }

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
            Action::make('resetChanges')
                ->label('Reset changes')
                ->color('gray')
                ->disabled(fn (): bool => ! $this->hasUnsavedChanges())
                ->action('resetChanges'),
            Action::make('save')
                ->label('Save settings')
                ->color('primary')
                ->disabled(fn (): bool => ! $this->canSave())
                ->keyBindings(['mod+s'])
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
            $backupPath = $this->settingsPath . '.bak-' . now()->format((string) config('palworld-settings-editor.backup_suffix_format'));

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

        if ($item['type'] === 'boolean') {
            return $this->wrapFieldInSettingCard(
                component: Toggle::make($item['key'])
                    ->label($item['label'])
                    ->helperText($description)
                    ->hintIcon('tabler-code', tooltip: $tooltip)
                    ->hintColor('info')
                    ->onColor('info')
                    ->offColor('gray')
                    ->disabled(fn (): bool => ! $this->canSave()),
            );
        }

        if ($item['type'] === 'enum') {
            $options = $item['options'] ?? [];
            $currentValue = $this->formData[$item['key']] ?? null;

            if ($currentValue !== null && ! in_array((string) $currentValue, $options, true)) {
                $options[] = (string) $currentValue;
            }

            return $this->wrapFieldInSettingCard(
                component: Select::make($item['key'])
                    ->label($item['label'])
                    ->helperText($description)
                    ->hintIcon('tabler-code', tooltip: $tooltip)
                    ->hintColor('info')
                    ->options(array_combine($options, $options))
                    ->disabled(fn (): bool => ! $this->canSave()),
            );
        }

        $input = TextInput::make($item['key'])
            ->label($item['label'])
            ->helperText($description)
            ->hintIcon('tabler-code', tooltip: $tooltip)
            ->hintColor('info')
            ->disabled(fn (): bool => ! $this->canSave())
            ->extraAttributes([]);

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

        return $this->wrapFieldInSettingCard(
            component: $input,
        );
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
                    ->state($this->isSafeToEdit ? 'Settings can be edited safely.' : 'Editing is locked until the server is offline/stopped.'),
            ]);
    }

    private function buildGuidanceSection(): Section
    {
        return Section::make('Editing Guidance')
            ->columnSpanFull()
            ->schema([
                TextEntry::make('editing_guidance')
                    ->hiddenLabel()
                    ->state('Restart the server after saving any Palworld settings changes. Values managed by the server egg startup variables should be edited from the Startup tab, not here.'),
            ]);
    }

    private function buildStartupVariablesSection(): Section
    {
        $components = [];

        if ($this->startupVariablesAvailable) {
            foreach ($this->startupVariables as $variable) {
                $components[] = $this->wrapFieldInSettingCard(
                    component: TextInput::make('startupVariableDisplayValues.' . $variable['name'])
                        ->label($variable['name'])
                        ->helperText($variable['description'] ?: null)
                        ->hintIcon('tabler-code', tooltip: $variable['name'])
                        ->hintColor('info')
                        ->disabled(),
                );
            }
        } else {
            $components[] = TextEntry::make('startup_variables_unavailable')
                ->hiddenLabel()
                ->state('No supported startup variables were detected for this server yet. This can happen if the variables relation differs on this Pelican build or if the server egg does not expose the expected values.');
        }

        return Section::make('Egg / Startup Variables')
            ->description('These values are managed by the Pelican egg startup variables and may be regenerated on server start.')
            ->columns(2)
            ->columnSpanFull()
            ->collapsible()
            ->extraAttributes(['class' => 'palworld-settings-grid-section'])
            ->schema($components);
    }

    private function buildSettingsOverviewSection(): Section
    {
        $components = [
            TextEntry::make('settings_file_status')
                ->label('Status')
                ->state('PalWorldSettings.ini was found and read successfully.'),
        ];

        if ($this->detectedEggManagedIniKeys !== []) {
            $components[] = TextEntry::make('settings_egg_managed')
                ->label('Startup-managed values')
                ->state(implode(', ', $this->detectedEggManagedIniKeys));
        }

        if ($this->lastBackupPath !== null) {
            $components[] = TextEntry::make('last_backup_path')
                ->label('Last backup')
                ->state($this->lastBackupPath . ($this->lastSavedAt ? ' | Saved at ' . $this->lastSavedAt : ''));
        }

        return Section::make('PalWorldSettings.ini')
            ->description('Expected path: ' . $this->settingsPath)
            ->columnSpanFull()
            ->schema($components);
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
            ->collapsed()
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

    private function wrapFieldInSettingCard(Component $component): Section
    {
        return Section::make()
            ->schema([$component])
            ->extraAttributes([
                'class' => 'palworld-setting-card',
            ]);
    }

    private function fillFormState(): void
    {
        $this->form->fill(array_merge(
            $this->formData,
            ['startupVariableDisplayValues' => $this->startupVariableDisplayValues],
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
