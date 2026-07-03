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
  server sidebar using native Filament controls and the panel's own page layout,
  so it sits naturally alongside the built-in *Startup* and *Settings* pages.
- **Palworld-only** — the page only appears on Palworld servers, detected from the
  server egg (tags, name, and startup command).
- **Typed controls** — numbers, integers, toggles, text fields, and enums, each
  with sensible min/max/step bounds, labels, helper text, and tooltips.
- **Grouped settings** — organized into readable sections:
  - Gameplay Rates
  - Damage, Stamina, Hunger, HP
  - Time and World Behaviour
  - Death and Difficulty
  - Base, Guild, and Limits
  - Advanced / present-only fields
- **Safe-by-default saving** — writes are blocked unless the server's power state
  resolves to offline/stopped (via Pelican's native `retrieveStatus()`).
- **Backups manager** — a timestamped copy of the config is created before every
  save, and existing backups can be **restored** (the current file is backed up
  first) or **deleted** right from the page.
- **Reset to Palworld defaults** — refill the form with Palworld's default values
  (from the game's `DefaultPalWorldSettings.ini` when available); nothing is written
  until you press Save.
- **Expand / collapse all** — quickly open or close every settings section.
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

## Compatibility with the Palworld egg

This plugin is built for the official
[games-steamcmd/palworld](https://github.com/pelican-eggs/games-steamcmd/tree/main/palworld)
egg and its
[PalworldServerConfigParser](https://github.com/pelican-eggs/Palworld-Config-Parser-Tool).

The config parser runs on every boot but does an **in-place** update of only the INI
keys whose startup variables are set (server name, passwords, RCON, max players, IP/port)
and preserves everything else. This plugin edits a **disjoint** set of gameplay/world
keys, so your edits are not overwritten on restart. The keys the egg manages are shown
read-only here — change those from the **Startup** tab instead.

> [!NOTE]
> If you add your own egg variable for a gameplay setting this plugin also edits, the
> parser will set that key from the variable on boot. With the stock egg there is no
> overlap. On Proton/Windows servers the config lives under `WindowsServer/` — the plugin
> falls back to that path automatically.

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
| `src/PalworldSettingsEditorPlugin.php` | Plugin registration and server-panel page discovery. |
| `src/Services/PalworldSettingsSchema.php` | Field definitions, labels, groups, bounds, and tooltips. |
| `src/Services/PalworldOptionSettingsParser.php` | Parses and rewrites the `OptionSettings=(...)` payload. |
| `src/Services/PalworldSettingsFileService.php` | File read/write/backup through Pelican's daemon file API. |
| `src/Services/PelicanServerStateService.php` | Server state detection (native `retrieveStatus()`) and "safe to edit" logic. |
| `src/Services/PelicanStartupVariableService.php` | Startup-variable lookup and read-only display. |
| `src/Services/PalworldServerDetector.php` | Detects whether a server runs Palworld to scope page visibility. |

## Contributing

Issues and pull requests are welcome. The plugin deliberately sticks to native
Filament/Pelican controls and appearance rather than heavy custom theming — please
keep changes close to existing patterns and test against a live Pelican panel by
rebuilding the zip and re-uploading.
