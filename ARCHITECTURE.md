# Architecture & Contributor Guidelines

> Orientation for anyone — human or AI agent — making changes to this plugin.
> The [README](README.md) is the user-facing manual; this file explains **how the
> plugin is built, why it's built that way, and the rules to keep in mind when
> changing it**. Read the "Security model" and "Guidelines" sections before
> touching save, file, permission, or Livewire-property code.

---

## 1. What this plugin is

A [Pelican Panel](https://pelican.dev) **server-scoped** plugin (Laravel +
[Filament v4](https://filamentphp.com) + Livewire 3). It adds a **Palworld
Settings** page to a Palworld server's sidebar that reads, parses, edits, and
rewrites the single-line `OptionSettings=(...)` payload inside
`Pal/Saved/Config/LinuxServer/PalWorldSettings.ini`, entirely through Pelican's
daemon-backed file API — no SSH, no manual editing.

It is deliberately narrow: it edits a **gameplay/world** subset of settings and
stays away from the values the Palworld egg owns (see §7).

---

## 2. Repository layout

| Path | Responsibility |
| --- | --- |
| `plugin.json` | Pelican plugin manifest (id, namespace, entry class, panels, `meta`). No `composer.json` — Pelican autoloads from here. |
| `src/PalworldSettingsEditorPlugin.php` | Filament `Plugin`: `register()` discovers pages, `boot()` merges config. |
| `src/Filament/Server/Pages/PalworldSettingsPage.php` | The page. Everything user-facing lives here: state, form schema, header actions, save/backup/power logic, validation, redaction. ~1400 lines — the heart of the plugin. |
| `src/Services/PalworldSettingsSchema.php` | Static knowledge: field definitions (label/type/bounds), groups, egg-managed key list, defaults, presets. |
| `src/Services/PalworldOptionSettingsParser.php` | Parse/rewrite the `OptionSettings=(...)` line. Format-critical; symmetric parse↔serialize. |
| `src/Services/PalworldSettingsFileService.php` | Daemon file API wrapper: read/write/copy, backup listing/deletion. |
| `src/Services/PelicanServerStateService.php` | Power-state detection (`retrieveStatus()`) and "safe to edit" gate. |
| `src/Services/PelicanStartupVariableService.php` | Reads the server's egg/startup variables (read-only display). |
| `src/Services/PalworldServerDetector.php` | Multi-signal "is this a Palworld server?" for page visibility. |
| `config/palworld-settings-editor.php` | `settings_path`, `backup_suffix_format`, `show_debug_section`. |

There is **no** `resources/views/` on `main`: the page renders through Pelican's
own server-form-page Blade view (see §4).

---

## 3. How Pelican loads the plugin

- **No `composer.json`.** Pelican reads `plugin.json` and autoloads the plugin's
  `src/` under the declared `namespace`. Keep PSR-4: file path mirrors namespace.
- `register()` calls `$panel->discoverPages(plugin_path(...), 'JanPauw\\...\\Filament\\Server\\Pages')`.
  The `Server` segment is derived from the panel id, so the page is only wired
  into the **server** panel (matching `"panels": ["server"]` in the manifest).
- `boot()` merges `config/palworld-settings-editor.php` into the runtime config,
  with **already-set values winning** (so operator/env overrides are honoured).
  The plugin also passes inline defaults at every `config()` read, so it still
  works if the file is somehow absent.
- **`plugin.json` `meta.status: "enabled"` must stay.** It is what makes the
  panel auto-enable the plugin on a zip upload. Removing it (which the official
  `pelican-dev/plugins` CI validator wants) makes zip installs land **disabled**,
  requiring a manual `artisan` enable. This repo self-hosts via zip and is not
  submitted to the official registry, so `meta` stays. This was removed once and
  had to be restored — do not remove it again.

---

## 4. The page (`PalworldSettingsPage`)

Extends `Filament\Pages\Page`; uses `InteractsWithForms` + `InteractsWithFormActions`.

- **View:** `protected string $view = 'filament.server.pages.server-form-page';`
  — the **host panel's own** view, which is what makes the page look native
  (same header/action layout as Startup/Settings). See the wire:submit gotcha in §11.
- **Access:** `canAccess()` = `parent::canAccess()` **and** a tenant exists **and**
  `PalworldServerDetector::isPalworldServer()` **and** the user has
  `SubuserPermission::FileReadContent` on the server.
- **Navigation:** group `Palworld`, label `Palworld Settings`, slug
  `palworld-settings`, sort `30`, icon `heroicon-o-adjustments-horizontal`.

### Lifecycle (`mount()`)

Services are method-injected. `mount()`:
1. Resolves the settings path (config path, falling back to the `WindowsServer/`
   path if the Linux file is absent).
2. Resolves power state, labels, message, diagnostics (via `PelicanServerStateService`).
3. Reads startup variables **only if** the user has `SubuserPermission::StartupRead`
   (they can contain secrets), and stores **sanitized** copies.
4. Reads the INI, extracts + parses the `OptionSettings` line, and builds the
   grouped settings, form data, quick-access items, and backup list. **The raw
   line, file preview, and parsed map are redacted before being stored** (§6).

### Public-property model (important)

Livewire serializes every `public` property to the browser and back. This page
splits them deliberately:

- **`#[Locked]` (server-authoritative):** state flags, resolved `settingsPath`,
  parsed/grouped settings, backup list, startup-variable display, etc. `#[Locked]`
  stops the **client from mutating** them between requests (e.g. redirecting the
  write path or forging the backup allowlist). **It does NOT stop dehydration** —
  the values are still sent to the browser, which is why secrets must be redacted
  at the source, not merely locked (§6, §11).
- **Client-mutable (not locked):** `$formData` (the form binding),
  `$fieldSearch`, `$collapseOverride`, `$collapseNonce`.

### Form schema (`getFormSchema()`)

Builds, in order (each conditional on state/permission/search): **Server Status** →
**Egg / Startup Variables** (read-only) → **Search settings** → **Quick Access** →
the editable **groups** (from the schema) → **Advanced / Present-only** → **Backups**
→ **Advanced / Debug** (only when `show_debug_section` is true). Live search filters
fields by label/key and auto-expands matching sections.

### Header actions (`getHeaderActions()`)

`expandAll`, `collapseAll`, `applyPreset` *(authz: FileUpdate)*, `resetDefaults`
*(FileUpdate)*, `resetChanges`, `save` *(primary; FileUpdate)*, `startServer`
*(ControlStart, contextual)*, `restartServer` *(ControlRestart, contextual)*.

### Save flow

`save()` just calls `$this->mountAction('save')` — routing both the Save button
and the view's `wire:submit` through the same confirmation modal, which previews
every change as *old → new* (`renderSaveDiff()`), then calls `writeSettings()`.

`writeSettings()` is the guarded write path, in order:
1. Re-check `SubuserPermission::FileUpdate` (never trust the button being visible).
2. Re-check `isSafeToEdit()` (server must be offline/stopped).
3. `getChangedFormData()` — only changed keys; bail if empty.
4. `validateFormData()` — per-field rules (§10); throws `ValidationException`.
5. Read current file → **copy to a fresh timestamped backup** → `parser->write()`
   the changes in place → `putContent()`.
6. Re-parse and refresh all state **through the redaction helpers**.
7. `ValidationException` and generic `Throwable` are caught; the latter is
   `report()`ed and surfaces a generic failure notice (no internals leaked).

### Power / backup actions

- `sendPowerAction($action)` accepts **only** `start`/`restart` via a `match`,
  mapping each to its exact permission — a crafted request cannot run `stop`/`kill`
  under the start permission.
- `restoreBackup()` / `deleteBackup()` re-check permission (`FileUpdate` /
  `FileDelete` respectively) **and** `isKnownBackup()` before acting. Restore also
  snapshots the current file first and requires a safe state.

---

## 5. Data flow

```
PalWorldSettings.ini  (daemon file API)
        │  read
        ▼
PalworldOptionSettingsParser::parse()        →  key ⇒ value map
        │  redactSensitiveSettings()
        ▼
buildGroupedSettings() + Schema groups        →  sections / fields
        │
        ▼
$formData  ⇄  Filament form (user edits)
        │  Save (confirm modal)
        ▼
getChangedFormData() → coerceForType() → validateFormData()
        │  backup current file
        ▼
PalworldOptionSettingsParser::write()         →  in-place rewrite, unknown keys preserved
        │  putContent()
        ▼
PalWorldSettings.ini      →  reload + redact + rebuild state
```

---

## 6. Secret redaction (do not regress)

The Palworld egg writes `AdminPassword`/`ServerPassword` **into the
`OptionSettings` line itself** (see §7). Because Livewire dehydrates every public
property to the browser, storing the raw line/preview/parsed map would ship those
passwords in cleartext to the client — even though the properties are `#[Locked]`.

Guards, applied at **every** assignment site (`mount`, `writeSettings`,
`reloadFromFile`):

- `redactSecrets(string)` — masks `*Password`/`*Token`/`*Secret` values in the raw
  line and the file preview.
- `redactSensitiveSettings(array)` / `isSensitiveKey()` — masks sensitive keys in
  the parsed map before it becomes a public property.
- Startup variables are `sanitizeStartupVariables()`-d and only read with
  `StartupRead`.

**Rule:** any new code that puts file content, parsed values, or startup values
into a `public` property must route it through these helpers first.

---

## 7. Palworld egg compatibility (the key insight)

Built for the official
[games-steamcmd/palworld](https://github.com/pelican-eggs/games-steamcmd/tree/main/palworld)
egg and its
[PalworldServerConfigParser](https://github.com/pelican-eggs/Palworld-Config-Parser-Tool).

On **every boot** the egg's parser does an **in-place** update of only the INI keys
that are backed by startup variables (server name/description, server & admin
**passwords**, max players, RCON enable/port, public IP/port — ~9 keys) and
**preserves everything else**. This plugin edits a **disjoint** set of gameplay/world
keys, so plugin edits survive restarts and the parser's edits don't clobber them.

Consequences baked into the code:
- The egg-managed keys are listed in `PalworldSettingsSchema::getEggManagedIniKeys()`
  / `getStartupVariableNames()` and are shown **read-only** — never editable here.
- Passwords live inside the `OptionSettings` line → §6 redaction is mandatory.
- Change egg-managed values from the server's **Startup** tab, not here.

> If an operator adds their own egg variable for a gameplay key this plugin also
> edits, the parser would set it from the variable on boot. With the stock egg
> there is no overlap.

---

## 8. Server-state safety

`PelicanServerStateService` uses Pelican core's native `Server::retrieveStatus()`
(a `ContainerStatus` enum) rather than probing the daemon directly. **Only a
confirmed `offline`/`exited` state is "safe to edit."** It deliberately does *not*
use `ContainerStatus::isOffline()`, which also returns true for `missing` (daemon
unreachable / node maintenance) — that must keep editing **locked**. Unknown/
unresolvable state ⇒ locked. Status is cached per request.

---

## 9. Security model & invariants

| Concern | Mechanism |
| --- | --- |
| Page visibility & read | `canAccess()` requires `FileReadContent` |
| Read startup variables | `StartupRead` (secrets) |
| Save / reset / preset / restore | `FileUpdate`, re-checked inside the action |
| Delete backup | `FileDelete`, re-checked inside the action |
| Power start / restart | `ControlStart` / `ControlRestart`, exact per action |
| Client tampering with paths/allowlist | `#[Locked]` server-authoritative props |
| Secrets to browser | redaction at every assignment (§6) |
| Path traversal (backup names) | `isKnownBackup()` — must be in `$this->backups`, and no `/`, `\`, or `..` |
| Backup collisions | `newBackupPath()` appends `bin2hex(random_bytes(3))` |
| INI injection via values | parser strips CR/LF, quotes/escapes untrusted strings, only passes through a single balanced tuple (§10) |
| Write while running | `isSafeToEdit()` re-checked before every write |
| Error disclosure | generic notices; real errors `report()`ed |

**Invariants for future changes:**
1. Every mutating public method re-checks its permission **and** (for writes) the
   safe state — never rely on a button being hidden.
2. Never place raw secrets/file content in a public property without redaction.
3. Never build a file path from client input without an allowlist + traversal check.
4. Keep the power-action whitelist to `start`/`restart`.

---

## 10. The `OptionSettings` format & parser

The payload is a single line: `OptionSettings=(Key=Value,Key2=Value2,...)`.

- **Splitting** (`splitTopLevel` / `findFirstTopLevelEquals`) is quote-, escape-,
  and paren-depth aware, so commas/equals inside `"..."` or `(...)` don't split a token.
- **`serializeValue()` type rules** (must stay symmetric with `normalizeValue()`):
  - `bool` → `True` / `False`
  - `int` → as-is; `float` → `number_format($v, 6, '.', '')` (Palworld's 6-decimal style)
  - known enum literals → bare (`None`, `Normal`, `Hard`, `Item`, …)
  - a single fully-balanced tuple like `(Steam,Xbox)` → passed through verbatim
  - everything else → CR/LF-stripped, then `"`-quoted with `\`/`"` escaped
- **`write()`** updates matched keys in place, appends genuinely new keys, and
  replaces the line with `preg_replace_callback` (a callback so `$`, `\1`, `${x}`
  in values are written literally, not treated as backreferences). **Unknown keys
  are preserved.**
- **Per-field validation** (`buildValidationRules`): numbers/integers get
  `numeric`/`integer` + min/max; enums get `in:` including the current value;
  strings are length-bounded. `getChangedFormData()` skips emptied numeric/enum
  fields so it never writes `Key=`.

---

## 11. Gotchas & known considerations

- **`wire:submit` on the host view.** The native `server-form-page` view has a
  page-level `wire:submit="save"`. Submit events bubbling up from *action modals*
  (preset/reset/restart/restore/delete confirmations) can be caught by it and
  re-trigger the Save modal. `save()` deliberately routes through
  `mountAction('save')` to make that harmless. **If you change the save UX or add
  a custom view, verify that confirming any other modal does not re-open Save.**
  (There is active work exploring a trimmed custom view for exactly this reason.)
- **`#[Locked]` ≠ not-dehydrated.** It blocks client *writes*, not serialization
  to the browser. Redact secrets regardless (§6).
- **`missing` status is not safe.** Don't "simplify" `statusIsOffline()` to
  `ContainerStatus::isOffline()`.
- **Daemon `getDirectory` entries** use a `file` boolean to distinguish files from
  directories; an empty JSON body can trip a `TypeError`, so `listBackups()`
  catches `Throwable` and guards `is_iterable`.
- **Floats vs integers matter** in the schema (`type => 'number'` vs `'integer'`)
  because they drive both the input step and serialization.
- **LF/CRLF** git warnings on Windows are benign.

---

## 12. Guidelines for changes

**Conventions**
- Match the native Pelican/Filament look; avoid heavy custom theming and custom
  Blade unless there's a concrete reason (and then mind §11).
- Keep `plugin.json` `meta.status` (§3). No `composer.json`. Config is auto-loaded
  — no publish step.
- Follow the existing service split; keep the parser symmetric and unknown-key-safe.

**Adding an editable setting**
1. Add it to the right group in `PalworldSettingsSchema::getEditableGroups()` with
   `label`, `type`, and bounds/options.
2. Add its vanilla default to `getDefaultValues()`.
3. Optionally include it in the relevant preset method.
4. Make sure it is **not** an egg-managed key (`getEggManagedIniKeys()` /
   `getStartupVariableNames()`); those stay read-only.

**Testing** (there is no PHP toolchain in this repo)
- The maintainer tests manually: build a zip with a top-level
  `palworld-settings-editor/` folder, upload via **admin → plugins**, and exercise
  it against a live Pelican panel + Palworld server.
- If a PHP toolchain is available, run Pint (style) and Larastan (static analysis)
  before opening a PR.

**Commits & PRs**
- Do work on a branch and open a PR; the maintainer merges.
- **No AI attribution** in commits or PRs (no `Co-Authored-By`, no "Generated with"
  footers) — this is configured in Claude Code settings and must be respected.
- Small, frequent, descriptive commits are preferred.

---

## 13. Reference

- Pelican Panel: <https://pelican.dev> · Filament v4: <https://filamentphp.com>
- Palworld egg: <https://github.com/pelican-eggs/games-steamcmd/tree/main/palworld>
- Config parser: <https://github.com/pelican-eggs/Palworld-Config-Parser-Tool>
- User manual: [README.md](README.md)
