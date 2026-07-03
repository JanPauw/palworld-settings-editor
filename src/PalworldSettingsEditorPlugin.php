<?php

namespace JanPauw\PalworldSettingsEditor;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

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
        $panel->renderHook(
            PanelsRenderHook::STYLES_BEFORE,
            fn () => Blade::render(<<<'BLADE'
                <style>
                    .palworld-settings-grid-section [data-slot="section-content"] {
                        gap: 1rem;
                    }

                    .palworld-setting-card {
                        border-radius: 0.75rem;
                    }

                    .palworld-setting-card > [data-slot="section-content-ctn"] {
                        padding: 1rem;
                    }

                    .palworld-setting-card [data-slot="section-content"] {
                        gap: 0.5rem;
                    }
                </style>
            BLADE)
        );
    }
}
