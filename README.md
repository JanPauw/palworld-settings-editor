# Palworld Settings Editor

Palworld Settings Editor is a Pelican `server` panel plugin for safely editing
supported Palworld gameplay and world settings from:

`Pal/Saved/Config/LinuxServer/PalWorldSettings.ini`

It is designed for the common Palworld egg workflow where some values are
managed by startup variables and may be regenerated on server start.

## Current status

The plugin is already functional for the main workflow:

- adds a `Palworld Settings` page to the Pelican `server` panel
- reads and parses the `OptionSettings=(...)` payload from
  `PalWorldSettings.ini`
- renders supported settings as typed controls
- blocks editing unless the server is safely offline/stopped
- creates a timestamped backup before each successful save
- preserves unknown `OptionSettings` keys when rewriting the file
- shows startup-variable values separately as read-only values

## What it does

- Adds a `Palworld Settings` page to the Pelican server panel sidebar.
- Detects the live server power state through the daemon and only allows saving
  while the server is offline/stopped.
- Reads and parses the `OptionSettings=(...)` payload from
  `PalWorldSettings.ini`.
- Shows supported settings as typed controls for numbers, toggles, text values,
  and enums.
- Separates egg-managed startup values from editable INI settings.
- Creates a timestamped backup before every successful save.
- Preserves unknown `OptionSettings` keys when rewriting the file.

## What it does not do

- It does not edit egg-managed startup values such as server name, passwords,
  RCON settings, or public IP/port from the INI editor.
- It does not support editing arbitrary host paths outside Pelican's server file
  abstractions.
- It does not currently expose backup restore tooling from the panel UI.
- It does not enable editing while the server is running, starting, stopping,
  suspended, or when the state cannot be confirmed safely.

## Installation

1. Build or package the plugin folder as a zip that contains the top-level
   `palworld-settings-editor` directory.
2. Upload the zip through the Pelican admin plugin UI, or place it in the
   Pelican `plugins` directory using your preferred workflow.
3. Install the plugin in Pelican.
4. Open a Palworld server in the panel and navigate to `Palworld` ->
   `Palworld Settings`.

## Packaging from this repo

Use:

```powershell
.\zip-plugin.ps1
```

This writes the archive to:

`dist/palworld-settings-editor.zip`

This is the archive intended for upload through the Pelican admin plugin UI.

## Supported config path

Current primary target:

`Pal/Saved/Config/LinuxServer/PalWorldSettings.ini`

If you use a Proton or Windows-style Palworld setup, the generated config path
may differ and is not the primary target of this version.

## UI approach

The page is intentionally trying to stay close to native Pelican / Filament
server-page patterns:

- native Filament controls where possible
- native colors where possible
- grouped sections for setting categories
- per-setting cards for readability
- helper text and tooltips for setting context

The current styling goal is to feel like a natural companion to Pelican's
native `Startup` page without introducing heavy custom theming.

## Safety notes

- Stop the server before editing settings.
- Restart the server after saving changes.
- Startup-managed egg values should be changed from the server `Startup` tab,
  not from this plugin.
- A backup is created before each successful write.

## Developer notes

Key implementation files:

- `src/Filament/Server/Pages/PalworldSettingsPage.php`
- `src/PalworldSettingsEditorPlugin.php`
- `src/Services/PalworldSettingsSchema.php`
- `src/Services/PalworldOptionSettingsParser.php`
- `src/Services/PalworldSettingsFileService.php`
- `src/Services/PelicanServerStateService.php`
- `src/Services/PelicanStartupVariableService.php`

For a contributor-oriented project handoff, see:

- `CLAUDE_HANDOFF.md`

## Troubleshooting

### Config file missing

Start the Palworld server once so it can generate `PalWorldSettings.ini`, then
stop it and reload the plugin page.

### Save button disabled

The plugin only enables saving when the live daemon state resolves to an
offline/stopped state. If the server is running or the state is not safe,
saving remains disabled.

### Egg variables overwrite values

Some values are intentionally excluded from editing because the Palworld egg
manages them through startup variables and may rewrite them on server start.

### File API adapter errors

The plugin reads and writes through Pelican's daemon-backed file APIs. If file
operations fail, check Pelican/Wings connectivity, daemon permissions, and
server file access.

### Debug section not visible

Developer diagnostics are hidden by default. To show them, set:

```php
'show_debug_section' => true,
```

in `config/palworld-settings-editor.php`.

### Slider-style controls not present

This plugin uses plain typed inputs and toggles rather than requiring extra
Filament slider packages, to keep installation simple and compatible.
