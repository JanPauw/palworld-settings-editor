# Claude Handoff: Palworld Settings Editor

This file is a practical handoff for another coding assistant or contributor.
It is meant to explain the current state of the plugin, the important design
decisions, and the safest way to continue the work.

## Project Summary

`palworld-settings-editor` is a Pelican `server` panel plugin that adds a
`Palworld Settings` page inside a Palworld server view. The page reads
`Pal/Saved/Config/LinuxServer/PalWorldSettings.ini`, parses the
`OptionSettings=(...)` payload, displays supported settings as typed form
controls, and writes the updated config back after creating a backup.

The plugin is intended to work alongside the Palworld Pelican egg, where some
values are managed by startup variables and should not be edited directly in the
INI editor.

## Current Functional Status

At the time of writing, the plugin is working end-to-end for the main scope:

- The plugin installs and appears in the Pelican server panel.
- The `Palworld Settings` navigation item shows in the server sidebar.
- The page reads and parses `PalWorldSettings.ini`.
- Editable settings are grouped into sections and rendered as typed controls.
- Saving is blocked unless the server is safely offline/stopped.
- A backup is created before each successful write.
- Unknown `OptionSettings` keys are preserved by the parser.
- Egg-managed startup variables are shown separately as read-only values.

## Current UI Direction

The current UI goal is:

- structurally resemble Pelican's native `Startup` page
- keep native Filament / Pelican colors whenever possible
- avoid hardcoded custom color-layer overrides unless absolutely necessary

Important recent decision:

- We intentionally rolled back custom background/input/toggle color overrides.
- Native toggle coloring came back after the rollback.
- Current work is focused on layout and spacing polish, not custom theming.

## Important Files

### Plugin manifest

- `plugin.json`

### Main page

- `src/Filament/Server/Pages/PalworldSettingsPage.php`

This is the core page implementation. It handles:

- loading server state
- loading startup variables
- reading and parsing the settings file
- building grouped form controls
- save validation and write flow
- backup creation

### Plugin bootstrap

- `src/PalworldSettingsEditorPlugin.php`

This handles plugin registration and currently also injects a small amount of
CSS via a Filament render hook for spacing and card polish.

### Services

- `src/Services/PalworldSettingsSchema.php`
  - field definitions, labels, descriptions, quick-access groups, tooltips
- `src/Services/PalworldOptionSettingsParser.php`
  - parsing and rewriting of `OptionSettings=(...)`
- `src/Services/PalworldSettingsFileService.php`
  - file read/write/copy interactions through Pelican
- `src/Services/PelicanServerStateService.php`
  - server state detection and "safe to edit" logic
- `src/Services/PelicanStartupVariableService.php`
  - startup variable lookup and display preparation

### Existing project notes

- `README.md`
- `palworld-settings-editor-codex-prompt.md`

## Packaging Workflow

The working packaging flow in this repo is:

```powershell
.\zip-plugin.ps1
```

This creates:

- `C:\Users\Jan Pauw\Codex\PelicanPlugins\dist\palworld-settings-editor.zip`

The current real-world test workflow used by the project owner is:

1. Uninstall or replace the plugin in Pelican if needed.
2. Zip the `palworld-settings-editor` folder.
3. Upload the generated zip through the Pelican admin plugin UI.
4. Re-test inside a live Palworld server page.

The zip script already automates the archive creation step.

## Current Styling Notes

The page currently uses:

- grouped sections for categories like `Quick Access`, `Gameplay Rates`, etc.
- per-setting cards created by wrapping each field in a child `Section`
- native Filament labels, helper text, and hint icons

Current styling approach:

- keep native colors
- use minimal CSS only for spacing and padding
- avoid forcing toggle colors
- avoid forcing custom surface ladders unless native output is clearly wrong

Small CSS hooks currently live in:

- `src/PalworldSettingsEditorPlugin.php`

Those are only meant to help with spacing:

- section grid gap
- setting card padding
- setting card radius

If UI work continues, treat those styles as optional polish, not the source of
truth for the overall look.

## Known Good Decisions

These are worth preserving unless there is a strong reason to change them:

- Use `filament.server.pages.server-form-page` as the page view.
- Keep startup-variable values read-only on this page.
- Block saving unless server state is safe.
- Preserve unknown settings during rewrite.
- Use native Filament controls rather than introducing extra UI packages.
- Prefer native Pelican/Filament appearance over hand-painted theme overrides.

## Known Pain Points / Open Polish Items

Functionally, the plugin is in decent shape. The remaining work is mostly UX
and polish:

1. The visual match to native Pelican pages can still be improved slightly.
2. Spacing between label, input, and helper text may still need refinement.
3. Some section/card density may still not be a perfect match for Startup.
4. Advanced/debug output should stay optional and hidden by default.

If someone continues the styling work, the safest order is:

1. spacing
2. padding
3. radius
4. only then consider any color adjustment

## Suggested Next Steps

If continuing development, the best next tasks are probably:

### Option 1: UI polish only

- compare card spacing against Pelican `Startup`
- refine helper-text rhythm and field density
- keep all native colors

### Option 2: safer editing UX

- add clearer dirty-state or unsaved-changes indication
- improve save confirmation and backup messaging

### Option 3: backup usability

- surface backup path more cleanly
- potentially add backup restore support later

### Option 4: broaden config compatibility

- support alternative Palworld config paths if needed
- confirm proton / windows-style path handling

## Constraints / Expectations

- The owner is testing against a real Pelican panel manually.
- Small visual changes are easiest to validate by rebuilding the zip and
  re-uploading it.
- Avoid destructive git operations.
- Avoid large refactors unless they directly help the plugin.
- Keep compatibility conservative and close to existing patterns.

## Quick Orientation for a New Contributor

If you are picking this up fresh:

1. Read `README.md`.
2. Read `src/Filament/Server/Pages/PalworldSettingsPage.php`.
3. Read `src/Services/PalworldSettingsSchema.php`.
4. Check `src/PalworldSettingsEditorPlugin.php` for any CSS hook changes.
5. Rebuild with `.\zip-plugin.ps1`.
6. Test in Pelican.

## Bottom Line

The plugin is no longer in scaffolding mode. It is working software with the
main Palworld settings workflow already wired up.

The biggest current priority is not "make it work" but "make it feel native"
without breaking the stable behavior that is already in place.
