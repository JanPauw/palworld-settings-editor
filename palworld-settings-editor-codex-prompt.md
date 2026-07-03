# Codex Task: Create a Pelican Panel Plugin — Palworld Settings Editor

## Goal

Create a Pelican Panel plugin named **Palworld Settings Editor**.

The plugin should add a server-panel page for Palworld servers that lets users safely view and edit common gameplay/world settings from:

```text
Pal/Saved/Config/LinuxServer/PalWorldSettings.ini
```

The plugin must **not** edit settings that are managed by the Palworld Pelican/Pterodactyl egg startup variables, because those values may be regenerated/overwritten by the egg's startup/config parser.

The plugin must be built as a normal Pelican Panel plugin using Laravel + Filament conventions.

---

## Reference docs

Use these docs as implementation references:

```text
Pelican plugin docs:
https://pelican.dev/docs/panel/advanced/plugins/

Palworld official configuration docs:
https://docs.palworldgame.com/settings-and-operation/configuration/

Palworld egg docs:
https://eggs.pterodactyl.io/egg/games-palworld/

Common Palworld egg JSON:
https://github.com/parkervcp/eggs/blob/master/game_eggs/steamcmd_servers/palworld/egg-palworld.json
```

---

## Important compatibility notes

Pelican plugins are still in development, so avoid clever hacks and keep the plugin simple and maintainable.

The common Palworld egg may use startup variables and a config parser such as `PalworldServerConfigParser`. Do not fight that system. Treat egg-managed values as read-only inside this plugin.

The common Palworld egg startup command invokes `PalworldServerConfigParser` before launching the server. This means some values may be written from startup variables into `PalWorldSettings.ini` on start. For the common egg, assume overlapping startup-managed values are authoritative and should remain read-only in this plugin.

Palworld config changes should only be saved while the server is stopped/offline. If the server is running, starting, stopping, or unknown, disable all editable form fields and disable the save action.

---

## Expected plugin identity

Plugin folder:

```text
plugins/palworld-settings-editor
```

Plugin ID:

```text
palworld-settings-editor
```

Display name:

```text
Palworld Settings Editor
```

Suggested namespace:

```php
JanPauw\PalworldSettingsEditor
```

Target panel:

```text
server
```

Navigation group:

```text
Palworld
```

Navigation label:

```text
Palworld Settings
```

---

## Required features

### 1. Server panel page

Add a Filament page inside the server panel.

The page should show:

1. A warning/status banner showing whether the server is safe to edit.
2. A read-only tab/section for egg-managed startup variables.
3. Editable grouped settings for Palworld gameplay/world options.
4. A save button that is disabled unless the server is stopped/offline.
5. A note that the server must be restarted after saving.

---

### 2. Server power-state safety

Before rendering the editable form, determine the server state.

Editable settings must be disabled unless the server appears stopped/offline.

Treat these states as **not safe to edit**:

```text
running
starting
stopping
installing
suspended
unknown
```

Treat these states as **safe to edit** only if the Pelican server state clearly reports stopped/offline.

If the exact Pelican API for server state differs, create a small adapter/service so this can be patched easily.

Suggested service:

```php
src/Services/PelicanServerStateService.php
```

The service should expose something like:

```php
public function isSafeToEdit($server): bool;
public function getStateLabel($server): string;
```

---

### 3. Read-only egg-managed values

Show these values in a read-only section or tab named:

```text
Egg / Startup Variables
```

All fields in this section must be disabled/read-only.

Show a warning like:

```text
These values are managed by the Pelican egg startup variables and may be regenerated on server start. Edit them from the server Startup tab, not from this plugin.
```

Include at least these variable names if available:

```text
SERVER_NAME
SERVER_DESCRIPTION
SERVER_PASSWORD
ADMIN_PASSWORD
MAX_PLAYERS
RCON_ENABLE
RCON_PORT
PUBLIC_IP
SERVER_PORT
AUTO_UPDATE
ENABLE_ENEMY
```

Notes:

- `ENABLE_ENEMY` corresponds to the `bEnableInvaderEnemy` config value and should be treated as egg-managed for the common Palworld egg.
- `SERVER_PORT` may be startup/allocation-managed rather than exposed as a normal editable egg variable. If available through Pelican, show it read-only; otherwise do not fabricate it.

If the exact Pelican model relationship for variables differs, wrap it in a service so it can be patched.

Suggested service:

```php
src/Services/PelicanStartupVariableService.php
```

The service should expose something like:

```php
public function getVariablesForServer($server): array;
```

Expected normalized return shape:

```php
[
    'SERVER_NAME' => [
        'name' => 'SERVER_NAME',
        'value' => 'My Palworld Server',
        'description' => 'Server name',
        'is_sensitive' => false,
    ],
]
```

For sensitive variables like passwords, mask values by default.

---

### 4. PalWorldSettings.ini handling

Read/write this file relative to the server root:

```text
Pal/Saved/Config/LinuxServer/PalWorldSettings.ini
```

Inside the running container this maps to:

```text
/home/container/Pal/Saved/Config/LinuxServer/PalWorldSettings.ini
```

On the Wings node this is usually under the server UUID volume, but the plugin should use Pelican/Wings file abstractions rather than hard-coding host paths.

The file format usually has this shape:

```ini
[/Script/Pal.PalGameWorldSettings]
OptionSettings=(ExpRate=1.000000,PalCaptureRate=1.000000,DeathPenalty=All,...)
```

Do not rewrite the whole file from scratch if avoidable.

Parser requirements:

- Preserve the section header.
- Preserve unknown settings.
- Preserve comments and unrelated lines if possible.
- Only update keys that this plugin explicitly supports.
- Handle quoted strings correctly, including commas inside quoted values.
- Handle booleans in Palworld style, e.g. `True` / `False`.
- Create a backup before saving.

Suggested services:

```php
src/Services/PalworldSettingsFileService.php
src/Services/PalworldOptionSettingsParser.php
```

---

### 5. Backup-before-save

Before writing changes, create a timestamped backup next to the config file.

Suggested format:

```text
Pal/Saved/Config/LinuxServer/PalWorldSettings.ini.bak-YYYYmmdd-HHMMSS
```

If backup creation fails, do not save.

Display a clear error message.

---

### 6. Typed controls

The form should use typed controls that match each setting.

Use these control types where supported by the installed Filament version:

- TextInput for text values.
- Password field for password-like values, but egg-managed password fields should be read-only and masked.
- Toggle for booleans.
- Select/dropdown for limited option values.
- Numeric input or slider for multipliers/rates.
- Numeric input for ports and counts.
- Placeholder/helper text for warnings.

If Filament `Slider` is not available in the installed Pelican version, fall back to numeric inputs with min/max/step rules.

Do not require a third-party Composer package for sliders.

---

## Editable settings to include

Do **not** include egg-managed values in the editable INI form.

The editable INI form should include these groups:

---

### Gameplay rates

Use numeric inputs or sliders.

```text
ExpRate
PalCaptureRate
PalSpawnNumRate
PalEggDefaultHatchingTime
CollectionDropRate
CollectionObjectHpRate
CollectionObjectRespawnSpeedRate
EnemyDropItemRate
SupplyDropSpan
```

Suggested input rules:

```text
min: 0
max: 20
step: 0.1
```

For egg hatching time, allow a wider range if useful.

---

### Damage, stamina, hunger, HP

Use numeric inputs or sliders.

```text
PlayerDamageRateAttack
PlayerDamageRateDefense
PlayerStomachDecreaceRate
PlayerStaminaDecreaceRate
PlayerAutoHPRegeneRate
PlayerAutoHpRegeneRateInSleep

PalDamageRateAttack
PalDamageRateDefense
PalStomachDecreaceRate
PalStaminaDecreaceRate
PalAutoHPRegeneRate
PalAutoHpRegeneRateInSleep
```

Suggested input rules:

```text
min: 0
max: 20
step: 0.1
```

---

### Time and world behaviour

Use numeric inputs/sliders for speed multipliers and toggles for booleans.

```text
DayTimeSpeedRate
NightTimeSpeedRate
BuildObjectDamageRate
BuildObjectDeteriorationDamageRate
bEnableFastTravel
bIsStartLocationSelectByMap
bExistPlayerAfterLogout
bEnableDefenseOtherGuildPlayer
bIsShowJoinLeftMessage
bUseAuth
bIsUseBackupSaveData
```

---

### Death and difficulty options

Use dropdowns.

```text
DeathPenalty
```

Suggested `DeathPenalty` options:

```text
None
Item
ItemAndEquipment
All
```

Suggested `Difficulty` options:

```text
None
Normal
Hard
```

Only include `Difficulty` if it already exists in the file or if the official docs for the installed Palworld version clearly support it. Keep the existing value if it is not in the known option list.

---

### Base, guild, and limits

Use integer numeric inputs.

```text
BaseCampWorkerMaxNum
BaseCampMaxNumInGuild
GuildPlayerMaxNum
```

For the first implementation pass, prefer fields confirmed in the official Palworld configuration docs. Treat `DropItemMaxNum` and `DropItemMaxNum_UNKO` as optional/advanced fields only if they are already present in the file or confirmed for the target Palworld version.

Suggested input rules:

```text
min: 0
max: 100000
step: 1
```

---

### Palworld 1.0 / newer options where present

Include these if they already exist in the file, but do not blindly add them if missing unless the form clearly marks them as advanced/new.

Use toggles/dropdowns/numeric controls as appropriate.

```text
RandomizerType
bHardcore
bBuildAreaLimit
bPalLost
bEnablePlayerToPlayerDamage
bEnableFriendlyFire
bEnableNonLoginPenalty
bEnableInvaderEnemy
bActiveUNKO
bAutoResetGuildNoOnlinePlayers
AutoResetGuildTimeNoOnlinePlayers
bInvisibleOtherGuildBaseCampAreaFX
bCanPickupOtherGuildDeathPenaltyDrop
bEnableAimAssistPad
bEnableAimAssistKeyboard
bShowPlayerList
bAllowGlobalPalboxExport
bAllowGlobalPalboxImport
```

Suggested `RandomizerType` options:

```text
None
Region
All
```

If official docs show additional values, include them.

For the first implementation pass, prefer newer options that are confirmed by the official Palworld configuration docs or already present in the file. If a field is not documented and not present, do not add it speculatively.

---

## Fields that must remain read-only / egg-managed

These should not be editable in the INI form:

```text
ServerName
ServerDescription
ServerPassword
AdminPassword
ServerPlayerMaxNum
RCONEnabled
RCONPort
PublicIP
PublicPort
bEnableInvaderEnemy
```

Show them under the read-only startup variables section instead.

`bEnableInvaderEnemy` must remain read-only for the common Palworld egg because it is mapped from the startup variable `ENABLE_ENEMY`.

If these keys are detected in `PalWorldSettings.ini`, optionally show an info line saying:

```text
This value exists in PalWorldSettings.ini, but this plugin does not edit it because the egg may manage it from startup variables.
```

---

## Suggested file structure

Create files similar to:

```text
palworld-settings-editor/
├── plugin.json
├── src/
│   ├── PalworldSettingsEditorPlugin.php
│   ├── Providers/
│   │   └── PalworldSettingsEditorServiceProvider.php
│   ├── Filament/
│   │   └── Server/
│   │       └── Pages/
│   │           └── PalworldSettingsPage.php
│   └── Services/
│       ├── PalworldSettingsFileService.php
│       ├── PalworldOptionSettingsParser.php
│       ├── PalworldSettingsSchema.php
│       ├── PelicanServerStateService.php
│       └── PelicanStartupVariableService.php
├── resources/
│   └── views/
│       └── filament/
│           └── server/
│               └── pages/
│                   └── palworld-settings-page.blade.php
└── README.md
```

Avoid migrations unless absolutely necessary.

Avoid adding external Composer dependencies.

---

## plugin.json

Create a valid Pelican plugin manifest.

Example shape:

```json
{
  "id": "palworld-settings-editor",
  "name": "Palworld Settings Editor",
  "author": "Jan Pauw",
  "version": "0.1.0",
  "description": "Safely edit Palworld gameplay settings from Pelican Panel.",
  "category": "plugin",
  "namespace": "JanPauw\\PalworldSettingsEditor",
  "class": "PalworldSettingsEditorPlugin",
  "panels": ["server"],
  "panel_version": null,
  "composer_packages": null
}
```

Adjust fields only if Pelican requires a different current manifest format.

---

## Parser details

Implement a parser that can split `OptionSettings=(...)` while respecting quoted strings.

Example input:

```text
OptionSettings=(ServerName="My, Server",ExpRate=2.000000,DeathPenalty=All,bEnableFastTravel=True)
```

Expected parsed output:

```php
[
    'ServerName' => 'My, Server',
    'ExpRate' => '2.000000',
    'DeathPenalty' => 'All',
    'bEnableFastTravel' => 'True',
]
```

When writing:

- String fields should be quoted when required.
- Boolean fields should be written as `True` or `False`.
- Numeric fields should preserve sensible decimal formatting.
- Unknown keys should be retained.
- Original key order should be retained as much as possible.
- New supported keys may be appended to the end only if the form submitted them and the plugin is configured to add missing values.

---

## File service notes

Prefer Pelican/Wings file APIs where possible.

Do not hard-code:

```text
/var/lib/pelican/volumes/<uuid>
```

The plugin should operate against the selected server, not against arbitrary host filesystem paths.

Create an adapter class for file operations because Pelican’s exact file repository class/method names may change between beta versions.

Suggested methods:

```php
public function exists($server, string $path): bool;
public function read($server, string $path): string;
public function write($server, string $path, string $contents): void;
public function copy($server, string $from, string $to): void;
```

If Pelican already has a server file repository, inject that and wrap it.

If not clear, leave TODO comments in only this adapter file and keep the rest of the plugin clean.

---

## UI behaviour

When the file does not exist, show a warning:

```text
PalWorldSettings.ini was not found. Start the Palworld server once to let it generate the config file, then stop the server before editing.
```

When the server is running, show a warning:

```text
Editing is disabled because the server is not stopped. Stop the server before changing Palworld settings, otherwise Palworld or the egg may overwrite changes.
```

When saving succeeds, show:

```text
Settings saved. A backup was created and the server must be restarted before changes take effect.
```

When saving fails, show the underlying error safely without exposing secrets.

If the selected server uses the Proton Palworld egg, the config path may instead be:

```text
Pal/Saved/Config/WindowsServer/PalWorldSettings.ini
```

For v0.1, it is acceptable to support only the Linux path as long as the README states that limitation clearly. If Proton support is added, handle it explicitly rather than guessing.

---

## Validation

Validate submitted values server-side.

Examples:

```text
Rates/multipliers: numeric, min 0, max 20
Ports: integer, min 1, max 65535
Player/base/guild counts: integer, min 0
Dropdowns: allow known values plus existing unknown value
Booleans: cast to True/False
```

Do not trust client-side disabled fields.

Before saving, re-check that the server is still stopped/offline.

For v0.1, only expose editable fields that are both:

1. Not managed by the common egg startup/config parser.
2. Confirmed by the official Palworld configuration docs or already present in the current file.

---

## README requirements

Create a README with:

1. What the plugin does.
2. What it does not do.
3. Installation steps.
4. Supported config path.
5. Warning about stopping the server before editing.
6. Warning about egg-managed startup variables.
7. Troubleshooting section for:
   - config file missing,
   - fields disabled,
   - egg variables overwriting values,
   - file API adapter errors,
   - Filament Slider not available.

---

## Acceptance criteria

The task is complete when:

- The plugin installs under `plugins/palworld-settings-editor`.
- The plugin registers a `Palworld Settings` page in the server panel.
- The page can read `Pal/Saved/Config/LinuxServer/PalWorldSettings.ini`.
- Editable fields are disabled while the server is not stopped/offline.
- The save action is disabled while the server is not stopped/offline.
- A backup file is created before every successful save.
- Unknown Palworld settings are preserved.
- Egg-managed values are shown read-only with a warning.
- Egg-managed values are not edited in the INI form.
- `bEnableInvaderEnemy` is treated as egg-managed/read-only for the common Palworld egg.
- Typed controls are used for text, booleans, numbers, sliders/numeric multipliers, and dropdown enums.
- No external Composer packages are required.
- The plugin has a README.
- PHP syntax checks pass.

---

## Development command hints

Useful commands on the Pelican machine:

```bash
cd /var/www/pelican

php artisan p:plugin:make
php artisan p:plugin:install
php artisan optimize:clear
php artisan view:clear
php artisan config:clear
```

PHP lint:

```bash
find plugins/palworld-settings-editor -name '*.php' -print0 | xargs -0 -n1 php -l
```

---

## Future v0.2 ideas

Do not implement these in the first pass unless the main plugin is already stable:

- Add a backup browser/restore page.
- Add scheduled safe restart after save.
- Add Palworld REST API health check.
- Add player list widget.
- Add RCON broadcast/save/restart buttons.
- Add support for editing Pelican startup variables directly, but only after confirming the correct Pelican API and permission model.
