<?php

namespace JanPauw\PalworldSettingsEditor\Services;

use Throwable;

/**
 * Best-effort detection of whether a server runs Palworld, so the settings page
 * only appears on relevant servers.
 *
 * This mirrors how the official rust-umod (RustUMod::isRustServer) and
 * minecraft-modrinth (ModrinthProjectType::fromServer) plugins gate their pages
 * on the server egg, but checks several signals so it works across differently
 * packaged Palworld eggs rather than depending on a single tag being present.
 */
class PalworldServerDetector
{
    public static function isPalworldServer(mixed $server): bool
    {
        if (! is_object($server)) {
            return false;
        }

        if (method_exists($server, 'loadMissing')) {
            try {
                $server->loadMissing('egg');
            } catch (Throwable) {
                // Ignore; fall back to whatever relations are already loaded.
            }
        }

        // Canonical signal: an egg tag (e.g. ["palworld"]), like Minecraft eggs' "minecraft" tag.
        $tags = data_get($server, 'egg.tags');
        if (is_iterable($tags)) {
            foreach ($tags as $tag) {
                if (is_string($tag) && str_contains(strtolower($tag), 'palworld')) {
                    return true;
                }
            }
        }

        // Textual signals: egg name, the egg/server startup command, and docker images.
        // The Palworld startup command contains "PalServer-Linux-Shipping" /
        // "PalworldServerConfigParser", which makes this reliable in practice.
        $haystacks = [
            data_get($server, 'egg.name'),
            data_get($server, 'egg.startup'),
            data_get($server, 'startup'),
            data_get($server, 'image'),
        ];

        $dockerImages = data_get($server, 'egg.docker_images');
        if (is_iterable($dockerImages)) {
            foreach ($dockerImages as $image) {
                $haystacks[] = $image;
            }
        }

        foreach ($haystacks as $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            $value = strtolower($value);

            if (str_contains($value, 'palworld') || str_contains($value, 'palserver')) {
                return true;
            }
        }

        return false;
    }
}
