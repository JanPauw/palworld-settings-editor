<?php

namespace JanPauw\PalworldSettingsEditor\Providers;

use Illuminate\Support\ServiceProvider;
use JanPauw\PalworldSettingsEditor\Services\PalworldOptionSettingsParser;
use JanPauw\PalworldSettingsEditor\Services\PalworldSettingsFileService;
use JanPauw\PalworldSettingsEditor\Services\PalworldSettingsSchema;
use JanPauw\PalworldSettingsEditor\Services\PelicanServerStateService;
use JanPauw\PalworldSettingsEditor\Services\PelicanStartupVariableService;

class PalworldSettingsEditorPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/palworld-settings-editor.php', 'palworld-settings-editor');

        $this->app->singleton(PalworldSettingsSchema::class);
        $this->app->singleton(PalworldOptionSettingsParser::class);
        $this->app->singleton(PelicanServerStateService::class);
        $this->app->singleton(PelicanStartupVariableService::class);
        $this->app->singleton(PalworldSettingsFileService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'palworld-settings-editor');
    }
}
