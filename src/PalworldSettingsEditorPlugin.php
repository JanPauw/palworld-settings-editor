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

    public function boot(Panel $panel): void {}
}
