<?php

namespace JanPauw\PalworldSettingsEditor;

use Filament\Contracts\Plugin;
use Filament\Panel;

class PalworldSettingsEditorPlugin implements Plugin
{
    public function getId(): string
    {
        return 'palworld-settings-editor';
    }

    public function register(Panel $panel): void
    {
        $id = str($panel->getId())->title();

        $panel->discoverPages(
            plugin_path($this->getId(), "src/Filament/$id/Pages"),
            "JanPauw\\PalworldSettingsEditor\\Filament\\$id\\Pages"
        );
    }

    public function boot(Panel $panel): void
    {
        // Merge the plugin config so operator overrides in config/palworld-settings-editor.php
        // are honoured (values already set — e.g. from env — take precedence). The plugin also
        // ships inline defaults at each read site, so it works even if this file is absent.
        $configPath = plugin_path($this->getId(), 'config/palworld-settings-editor.php');

        if (is_file($configPath)) {
            $fileConfig = require $configPath;

            if (is_array($fileConfig)) {
                config()->set('palworld-settings-editor', array_merge(
                    $fileConfig,
                    (array) config('palworld-settings-editor', [])
                ));
            }
        }
    }
}
