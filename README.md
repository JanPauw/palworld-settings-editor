# Palworld Settings Editor

A [Pelican Panel](https://pelican.dev) **server**-panel plugin for safely editing
Palworld gameplay and world settings straight from the panel — no manual file
editing, no SSH.

It adds a **Palworld Settings** page to a Palworld server view that reads,
parses, and rewrites the `OptionSettings=(...)` payload in:

```
Pal/Saved/Config/LinuxServer/PalWorldSettings.ini
```

It's built for the standard Palworld Pelican egg workflow, where some values are
owned by startup variables and may be regenerated on boot — so the editor stays
out of their way and only touches what's safe to change.

> [!WARNING]
> Stop the server before editing. The editor only allows saving while the server
> is confirmed offline/stopped, and a timestamped backup is written before every
> save. Restart the server after saving to apply your changes.

---

## Features

- **Native panel integration** — adds a *Palworld Settings* page to the Pelican
  server sidebar, styled to sit naturally alongside the built-in *Startup* page.
- **Typed controls** — numbers, integers, toggles, text fields, and enums, each
  with sensible min/max/step bounds, labels, helper text, and tooltips.
- **Grouped settings** — organized into readable sections:
  - Gameplay Rates
  - Damage, Stamina, Hunger, HP
  - Time and World Behaviour
  - Death and Difficulty
  - Base, Guild, and Limits
  - Advanced / present-only fields
- **Safe-by-default saving** — writes are blocked unless the live daemon state
  resolves to offline/stopped.
- **Automatic backups** — a timestamped copy of the config is created before
  every successful save.
- **Non-destructive rewrites** — unknown `OptionSettings` keys are preserved when
  the file is written back.
- **Respects egg-managed values** — startup-variable values (server name,
  passwords, RCON, ports, etc.) are shown read-only and never edited here.

## Requirements

- A working **Pelican Panel** install with a Palworld server using the Palworld
  egg.
- Access to the Pelican **admin plugin UI** (or the panel's `plugins` directory)
  to install the plugin.
- The server must have been started at least once so Palworld can generate
  `PalWorldSettings.ini`.

## Installation

1. Get the plugin as a zip containing a top-level `palworld-settings-editor/`
   directory (clone this repo and zip the folder, or grab a release archive).
2. Upload the zip through the Pelican **admin → plugins** UI, or drop it into the
   panel's `plugins` directory.
3. Install/enable the plugin in Pelican.
4. Open a Palworld server and go to **Palworld → Palworld Settings**.

## Usage

1. **Stop the server.** Saving is disabled while it's running.
2. Open **Palworld Settings** and adjust the values you want.
3. **Save.** A backup of the current `PalWorldSettings.ini` is created first.
4. **Start the server** to apply the changes.

> [!NOTE]
> Startup-managed values (server name, passwords, RCON, public IP/port) are shown
> read-only. Change those from the server's **Startup** tab — the egg manages them
> and may overwrite the INI on boot.

## Configuration

Plugin config lives in [`config/palworld-settings-editor.php`](config/palworld-settings-editor.php).

Developer diagnostics are hidden by default. To surface the debug section, set:

```php
'show_debug_section' => true,
```

## Scope

**Does:**

- Edit supported `OptionSettings` values from `PalWorldSettings.ini`.
- Detect server power state and gate saving on a safe (offline) state.
- Back up the config before each save and preserve unknown keys.

**Does not:**

- Edit egg/startup-managed values (server name, passwords, RCON, IP/port).
- Edit arbitrary host paths outside Pelican's server file abstractions.
- Enable editing while the server is running, starting, stopping, suspended, or
  in an unconfirmed state.
- Restore backups from the panel UI (backups are written, not yet restorable
  in-app).

## Troubleshooting

<details>
<summary><strong>Config file missing</strong></summary>

Start the Palworld server once so it generates `PalWorldSettings.ini`, then stop
it and reload the plugin page.
</details>

<details>
<summary><strong>Save button is disabled</strong></summary>

Saving is only enabled when the live daemon state resolves to offline/stopped. If
the server is running — or the state can't be confirmed safely — saving stays
disabled.
</details>

<details>
<summary><strong>Some values keep getting overwritten</strong></summary>

Those are egg-managed startup variables. They're intentionally excluded from
editing here because the Palworld egg may rewrite them on server start. Change
them from the **Startup** tab.
</details>

<details>
<summary><strong>File API / adapter errors</strong></summary>

The plugin reads and writes through Pelican's daemon-backed file APIs. If file
operations fail, check Pelican/Wings connectivity, daemon permissions, and server
file access.
</details>

<details>
<summary><strong>Using a Proton / Windows-style setup</strong></summary>

The primary target is the Linux config path above. Proton/Windows Palworld setups
may generate the config elsewhere and aren't the primary target of this version.
</details>

## Project structure

| Path | Responsibility |
| --- | --- |
| `src/Filament/Server/Pages/PalworldSettingsPage.php` | The settings page: loads state, builds the form, validates and writes saves. |
| `src/PalworldSettingsEditorPlugin.php` | Plugin registration and a small CSS hook for spacing polish. |
| `src/Services/PalworldSettingsSchema.php` | Field definitions, labels, groups, bounds, and tooltips. |
| `src/Services/PalworldOptionSettingsParser.php` | Parses and rewrites the `OptionSettings=(...)` payload. |
| `src/Services/PalworldSettingsFileService.php` | File read/write/backup through Pelican. |
| `src/Services/PelicanServerStateService.php` | Server state detection and "safe to edit" logic. |
| `src/Services/PelicanStartupVariableService.php` | Startup-variable lookup and read-only display. |

## Contributing

Issues and pull requests are welcome. The plugin deliberately sticks to native
Filament/Pelican controls and appearance rather than heavy custom theming — please
keep changes close to existing patterns and test against a live Pelican panel by
rebuilding the zip and re-uploading.
