<?php

namespace JanPauw\PalworldSettingsEditor\Services;

class PalworldSettingsSchema
{
    public function getEditableGroups(): array
    {
        return [
            'gameplay_rates' => [
                'label' => 'Gameplay Rates',
                'fields' => [
                    'ExpRate' => ['label' => 'Experience Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PalCaptureRate' => ['label' => 'Pal Capture Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PalSpawnNumRate' => ['label' => 'Pal Spawn Count Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PalEggDefaultHatchingTime' => ['label' => 'Egg Hatching Time', 'type' => 'number', 'min' => 0, 'max' => 1000, 'step' => 0.1],
                    'CollectionDropRate' => ['label' => 'Collection Drop Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'CollectionObjectHpRate' => ['label' => 'Collection Object HP Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'CollectionObjectRespawnSpeedRate' => ['label' => 'Collection Respawn Speed Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'EnemyDropItemRate' => ['label' => 'Enemy Drop Item Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'SupplyDropSpan' => ['label' => 'Supply Drop Span', 'type' => 'number', 'min' => 0, 'max' => 100000, 'step' => 1],
                    'WorkSpeedRate' => ['label' => 'Work Speed Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                ],
            ],
            'player_and_pal_rates' => [
                'label' => 'Damage, Stamina, Hunger, HP',
                'fields' => [
                    'PlayerDamageRateAttack' => ['label' => 'Player Attack Damage', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PlayerDamageRateDefense' => ['label' => 'Player Defense Damage', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PlayerStomachDecreaceRate' => ['label' => 'Player Hunger Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PlayerStaminaDecreaceRate' => ['label' => 'Player Stamina Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PlayerAutoHPRegeneRate' => ['label' => 'Player HP Regen Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PlayerAutoHpRegeneRateInSleep' => ['label' => 'Player Sleep HP Regen Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PalDamageRateAttack' => ['label' => 'Pal Attack Damage', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PalDamageRateDefense' => ['label' => 'Pal Defense Damage', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PalStomachDecreaceRate' => ['label' => 'Pal Hunger Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PalStaminaDecreaceRate' => ['label' => 'Pal Stamina Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PalAutoHPRegeneRate' => ['label' => 'Pal HP Regen Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'PalAutoHpRegeneRateInSleep' => ['label' => 'Pal Sleep HP Regen Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'ItemWeightRate' => ['label' => 'Item Weight Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'EquipmentDurabilityDamageRate' => ['label' => 'Equipment Durability Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                ],
            ],
            'world_behaviour' => [
                'label' => 'Time and World Behaviour',
                'fields' => [
                    'DayTimeSpeedRate' => ['label' => 'Day Time Speed Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'NightTimeSpeedRate' => ['label' => 'Night Time Speed Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'BuildObjectHpRate' => ['label' => 'Build Object HP Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'BuildObjectDamageRate' => ['label' => 'Build Object Damage Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'BuildObjectDeteriorationDamageRate' => ['label' => 'Build Object Deterioration Rate', 'type' => 'number', 'min' => 0, 'max' => 20, 'step' => 0.1],
                    'bEnableFastTravel' => ['label' => 'Enable Fast Travel', 'type' => 'boolean'],
                    'bEnableFastTravelOnlyBaseCamp' => ['label' => 'Fast Travel Only at Base Camp', 'type' => 'boolean'],
                    'bIsStartLocationSelectByMap' => ['label' => 'Choose Start Location by Map', 'type' => 'boolean'],
                    'bExistPlayerAfterLogout' => ['label' => 'Player Persists After Logout', 'type' => 'boolean'],
                    'bEnableDefenseOtherGuildPlayer' => ['label' => 'Enable Defense for Other Guild Players', 'type' => 'boolean'],
                    'bIsShowJoinLeftMessage' => ['label' => 'Show Join/Leave Messages', 'type' => 'boolean'],
                    'bEnableInvaderEnemy' => ['label' => 'Enable Invader Enemy (Raids)', 'type' => 'boolean'],
                    'bUseAuth' => ['label' => 'Use Authentication', 'type' => 'boolean'],
                    'bIsUseBackupSaveData' => ['label' => 'Use Backup Save Data', 'type' => 'boolean'],
                    'AutoSaveSpan' => ['label' => 'Auto Save Span', 'type' => 'number', 'min' => 0, 'max' => 100000, 'step' => 0.1],
                    'BanListURL' => ['label' => 'Ban List URL', 'type' => 'string'],
                    'LogFormatType' => ['label' => 'Log Format Type', 'type' => 'string'],
                    'CrossplayPlatforms' => ['label' => 'Crossplay Platforms', 'type' => 'string'],
                ],
            ],
            'death_and_difficulty' => [
                'label' => 'Death and Difficulty',
                'fields' => [
                    'Difficulty' => ['label' => 'Difficulty', 'type' => 'enum', 'options' => ['None', 'Normal', 'Hard']],
                    'DeathPenalty' => ['label' => 'Death Penalty', 'type' => 'enum', 'options' => ['None', 'Item', 'ItemAndEquipment', 'All']],
                    'RandomizerType' => ['label' => 'Randomizer Type', 'type' => 'enum', 'options' => ['None', 'Region', 'All']],
                    'RandomizerSeed' => ['label' => 'Randomizer Seed', 'type' => 'string'],
                    'bIsRandomizerPalLevelRandom' => ['label' => 'Randomize Pal Levels', 'type' => 'boolean'],
                    'bHardcore' => ['label' => 'Hardcore Mode', 'type' => 'boolean'],
                    'bPalLost' => ['label' => 'Lose Pals on Death', 'type' => 'boolean'],
                    'bEnablePlayerToPlayerDamage' => ['label' => 'Enable Player Damage', 'type' => 'boolean'],
                    'bEnableFriendlyFire' => ['label' => 'Enable Friendly Fire', 'type' => 'boolean'],
                    'bEnableNonLoginPenalty' => ['label' => 'Enable Non-Login Penalty', 'type' => 'boolean'],
                    'bCanPickupOtherGuildDeathPenaltyDrop' => ['label' => 'Can Pickup Other Guild Death Drops', 'type' => 'boolean'],
                    'RespawnPenaltyDurationThreshold' => ['label' => 'Respawn Penalty Duration Threshold', 'type' => 'number', 'min' => 0, 'max' => 100000, 'step' => 0.1],
                    'RespawnPenaltyTimeScale' => ['label' => 'Respawn Penalty Time Scale', 'type' => 'number', 'min' => 0, 'max' => 100000, 'step' => 0.1],
                ],
            ],
            'base_and_guild_limits' => [
                'label' => 'Base, Guild, and Limits',
                'fields' => [
                    'BaseCampMaxNum' => ['label' => 'Base Camp Max Number', 'type' => 'integer', 'min' => 0, 'max' => 100000],
                    'BaseCampWorkerMaxNum' => ['label' => 'Base Camp Worker Max Number', 'type' => 'integer', 'min' => 0, 'max' => 100000],
                    'BaseCampMaxNumInGuild' => ['label' => 'Base Camps Per Guild', 'type' => 'integer', 'min' => 0, 'max' => 100000],
                    'GuildPlayerMaxNum' => ['label' => 'Guild Player Max Number', 'type' => 'integer', 'min' => 0, 'max' => 100000],
                    'DropItemMaxNum' => ['label' => 'Drop Item Max Number', 'type' => 'integer', 'min' => 0, 'max' => 100000],
                    'DropItemMaxNum_UNKO' => ['label' => 'UNKO Drop Item Max Number', 'type' => 'integer', 'min' => 0, 'max' => 100000],
                    'DropItemAliveMaxHours' => ['label' => 'Drop Item Lifetime (Hours)', 'type' => 'number', 'min' => 0, 'max' => 100000, 'step' => 0.1],
                    'CoopPlayerMaxNum' => ['label' => 'Co-op Player Max Number', 'type' => 'integer', 'min' => 0, 'max' => 100000],
                    'AutoResetGuildTimeNoOnlinePlayers' => ['label' => 'Guild Reset Time Without Online Players', 'type' => 'number', 'min' => 0, 'max' => 100000, 'step' => 0.1],
                    'bAutoResetGuildNoOnlinePlayers' => ['label' => 'Auto Reset Guild Without Online Players', 'type' => 'boolean'],
                    'MaxBuildingLimitNum' => ['label' => 'Max Building Limit', 'type' => 'integer', 'min' => 0, 'max' => 100000],
                ],
            ],
            'advanced_present_only' => [
                'label' => 'Advanced / Present-only Fields',
                'fields' => [
                    'bActiveUNKO' => ['label' => 'Enable UNKO', 'type' => 'boolean'],
                    'bEnableAimAssistPad' => ['label' => 'Enable Aim Assist (Pad)', 'type' => 'boolean'],
                    'bEnableAimAssistKeyboard' => ['label' => 'Enable Aim Assist (Keyboard)', 'type' => 'boolean'],
                    'bIsMultiplay' => ['label' => 'Multiplay Enabled', 'type' => 'boolean'],
                    'bIsPvP' => ['label' => 'PVP Enabled', 'type' => 'boolean'],
                    'bCharacterRecreateInHardcore' => ['label' => 'Recreate Character in Hardcore', 'type' => 'boolean'],
                    'Region' => ['label' => 'Region', 'type' => 'string'],
                    'ChatPostLimitPerMinute' => ['label' => 'Chat Post Limit Per Minute', 'type' => 'integer', 'min' => 0, 'max' => 100000],
                    'RESTAPIEnabled' => ['label' => 'REST API Enabled', 'type' => 'boolean'],
                    'RESTAPIPort' => ['label' => 'REST API Port', 'type' => 'integer', 'min' => 1, 'max' => 65535],
                    'bShowPlayerList' => ['label' => 'Show Player List', 'type' => 'boolean'],
                    'EnablePredatorBossPal' => ['label' => 'Enable Predator Boss Pal', 'type' => 'boolean'],
                    'ServerReplicatePawnCullDistance' => ['label' => 'Pawn Cull Distance', 'type' => 'number', 'min' => 0, 'max' => 1000000, 'step' => 0.1],
                    'bAllowGlobalPalboxExport' => ['label' => 'Allow Global Palbox Export', 'type' => 'boolean'],
                    'bAllowGlobalPalboxImport' => ['label' => 'Allow Global Palbox Import', 'type' => 'boolean'],
                    'ItemContainerForceMarkDirtyInterval' => ['label' => 'Item Dirty Interval', 'type' => 'number', 'min' => 0, 'max' => 100000, 'step' => 0.1],
                    'ItemCorruptionMultiplier' => ['label' => 'Item Corruption Multiplier', 'type' => 'number', 'min' => 0, 'max' => 100000, 'step' => 0.1],
                    'DenyTechnologyList' => ['label' => 'Denied Technology List', 'type' => 'string'],
                    'GuildRejoinCooldownMinutes' => ['label' => 'Guild Rejoin Cooldown Minutes', 'type' => 'integer', 'min' => 0, 'max' => 100000],
                    'BlockRespawnTime' => ['label' => 'Block Respawn Time', 'type' => 'number', 'min' => 0, 'max' => 100000, 'step' => 0.1],
                    'bAllowClientMod' => ['label' => 'Allow Client Mod', 'type' => 'boolean'],
                    'bBuildAreaLimit' => ['label' => 'Build Area Limit', 'type' => 'boolean'],
                    'bInvisibleOtherGuildBaseCampAreaFX' => ['label' => 'Hide Other Guild Base FX', 'type' => 'boolean'],
                    'bDisplayPvPItemNumOnWorldMap_BaseCamp' => ['label' => 'Display PVP Item Count on Map (Base)', 'type' => 'boolean'],
                    'bDisplayPvPItemNumOnWorldMap_Player' => ['label' => 'Display PVP Item Count on Map (Player)', 'type' => 'boolean'],
                    'AdditionalDropItemWhenPlayerKillingInPvPMode' => ['label' => 'Additional PVP Drop Item', 'type' => 'string'],
                    'AdditionalDropItemNumWhenPlayerKillingInPvPMode' => ['label' => 'Additional PVP Drop Item Count', 'type' => 'integer', 'min' => 0, 'max' => 100000],
                    'bAdditionalDropItemWhenPlayerKillingInPvPMode' => ['label' => 'Enable Additional PVP Drop Item', 'type' => 'boolean'],
                    'bAllowEnhanceStat_Health' => ['label' => 'Allow Health Enhancement', 'type' => 'boolean'],
                    'bAllowEnhanceStat_Attack' => ['label' => 'Allow Attack Enhancement', 'type' => 'boolean'],
                    'bAllowEnhanceStat_Stamina' => ['label' => 'Allow Stamina Enhancement', 'type' => 'boolean'],
                    'bAllowEnhanceStat_Weight' => ['label' => 'Allow Weight Enhancement', 'type' => 'boolean'],
                    'bAllowEnhanceStat_WorkSpeed' => ['label' => 'Allow Work Speed Enhancement', 'type' => 'boolean'],
                ],
            ],
        ];
    }

    /**
     * INI keys the Palworld egg rewrites from startup variables on every boot
     * (via PalworldServerConfigParser). These are shown read-only and excluded
     * from editing so the plugin never fights the egg over them.
     *
     * Matches the keys the stock games-steamcmd/palworld egg actually manages:
     * ServerName/ServerDescription/ServerPassword/AdminPassword from their vars,
     * ServerPlayerMaxNum from MAX_PLAYERS, RCON from RCON_ENABLE/RCON_PORT, and
     * PublicIP/PublicPort from the built-in SERVER_IP/SERVER_PORT allocation vars.
     */
    public function getEggManagedIniKeys(): array
    {
        return [
            'ServerName',
            'ServerDescription',
            'ServerPassword',
            'AdminPassword',
            'ServerPlayerMaxNum',
            'RCONEnabled',
            'RCONPort',
            'PublicIP',
            'PublicPort',
        ];
    }

    /**
     * Startup variable names to surface read-only, aligned with the stock
     * games-steamcmd/palworld egg's declared variables.
     */
    public function getStartupVariableNames(): array
    {
        return [
            'SERVER_NAME',
            'SERVER_DESCRIPTION',
            'SERVER_PASSWORD',
            'ADMIN_PASSWORD',
            'MAX_PLAYERS',
            'RCON_ENABLE',
            'RCON_PORT',
            'PUBLIC_IP',
            'ALLOW_CONNECT_PLATFORM',
            'AUTO_UPDATE',
        ];
    }

    public function getAllKnownFieldKeys(): array
    {
        $keys = [];

        foreach ($this->getEditableGroups() as $group) {
            foreach (array_keys($group['fields']) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function getDetectedEggManagedKeys(array $parsedSettings): array
    {
        $detected = [];

        foreach ($this->getEggManagedIniKeys() as $key) {
            if (array_key_exists($key, $parsedSettings)) {
                $detected[] = $key;
            }
        }

        return $detected;
    }

    public function getQuickAccessFieldKeys(): array
    {
        return [
            'ExpRate',
            'PalCaptureRate',
            'DayTimeSpeedRate',
            'NightTimeSpeedRate',
            'DeathPenalty',
            'Difficulty',
            'BaseCampMaxNumInGuild',
            'GuildPlayerMaxNum',
            'bEnableFastTravel',
            'bShowPlayerList',
            'PalEggDefaultHatchingTime',
            'AutoSaveSpan',
        ];
    }

    public function getFieldDefinition(string $fieldKey): ?array
    {
        foreach ($this->getEditableGroups() as $group) {
            if (isset($group['fields'][$fieldKey])) {
                return $group['fields'][$fieldKey];
            }
        }

        return null;
    }

    public function getFieldDescription(string $fieldKey): ?string
    {
        $definition = $this->getFieldDefinition($fieldKey);

        if ($definition === null) {
            return null;
        }

        $descriptions = [
            'ExpRate' => 'Controls how quickly players gain experience.',
            'PalCaptureRate' => 'Higher values make Pals easier to capture.',
            'PalSpawnNumRate' => 'Adjusts how many Pals appear in the world.',
            'PalEggDefaultHatchingTime' => 'Sets how long eggs take to hatch.',
            'CollectionDropRate' => 'Changes how many items are gathered from resource nodes.',
            'CollectionObjectHpRate' => 'Changes how durable harvestable resource nodes are.',
            'CollectionObjectRespawnSpeedRate' => 'Controls how quickly resource nodes return after being harvested.',
            'EnemyDropItemRate' => 'Changes how many items enemies drop on defeat.',
            'SupplyDropSpan' => 'Sets the interval between supply drop events.',
            'WorkSpeedRate' => 'Changes the work speed of Pals at base.',
            'DeathPenalty' => 'Choose what players lose when they die.',
            'Difficulty' => 'Select the overall world difficulty preset.',
            'BaseCampMaxNumInGuild' => 'Controls how many base camps each guild may place.',
            'GuildPlayerMaxNum' => 'Sets the maximum number of players allowed in a guild.',
            'bEnableFastTravel' => 'Allows players to use the fast travel system.',
            'bShowPlayerList' => 'Controls whether the in-game player list is visible.',
            'AutoSaveSpan' => 'Sets how often the world auto-saves.',
            'BanListURL' => 'Remote URL used for the server ban list.',
            'LogFormatType' => 'Determines the log output format written by the server.',
            'CrossplayPlatforms' => 'Lists the platforms allowed to join the server.',
        ];

        if (isset($descriptions[$fieldKey])) {
            return $descriptions[$fieldKey];
        }

        if (($definition['type'] ?? null) === 'boolean') {
            return 'Enable or disable this server option.';
        }

        if (($definition['type'] ?? null) === 'enum') {
            return 'Choose one of the supported values for this setting.';
        }

        if (($definition['type'] ?? null) === 'integer' || ($definition['type'] ?? null) === 'number') {
            $parts = ['Adjust this numeric server setting.'];

            if (isset($definition['min']) && isset($definition['max'])) {
                $parts[] = 'Allowed range: ' . $definition['min'] . ' to ' . $definition['max'] . '.';
            }

            return implode(' ', $parts);
        }

        return 'Edit the raw value stored in PalWorldSettings.ini for this option.';
    }

    public function getFieldTooltip(string $fieldKey): string
    {
        return $fieldKey;
    }

    /**
     * Best-effort Palworld default values, sourced from the official
     * PalworldServerConfigParser reference defaults. Used by "Reset to defaults"
     * when the game's shipped DefaultPalWorldSettings.ini cannot be read.
     *
     * @return array<string, mixed>
     */
    public function getDefaultValues(): array
    {
        return [
            // Gameplay rates
            'ExpRate' => 1.0, 'PalCaptureRate' => 1.0, 'PalSpawnNumRate' => 1.0,
            'PalEggDefaultHatchingTime' => 72.0, 'CollectionDropRate' => 1.0,
            'CollectionObjectHpRate' => 1.0, 'CollectionObjectRespawnSpeedRate' => 1.0,
            'EnemyDropItemRate' => 1.0, 'SupplyDropSpan' => 180, 'WorkSpeedRate' => 1.0,
            // Damage, stamina, hunger, HP
            'PlayerDamageRateAttack' => 1.0, 'PlayerDamageRateDefense' => 1.0,
            'PlayerStomachDecreaceRate' => 1.0, 'PlayerStaminaDecreaceRate' => 1.0,
            'PlayerAutoHPRegeneRate' => 1.0, 'PlayerAutoHpRegeneRateInSleep' => 1.0,
            'PalDamageRateAttack' => 1.0, 'PalDamageRateDefense' => 1.0,
            'PalStomachDecreaceRate' => 1.0, 'PalStaminaDecreaceRate' => 1.0,
            'PalAutoHPRegeneRate' => 1.0, 'PalAutoHpRegeneRateInSleep' => 1.0,
            'ItemWeightRate' => 1.0, 'EquipmentDurabilityDamageRate' => 1.0,
            // Time and world behaviour
            'DayTimeSpeedRate' => 1.0, 'NightTimeSpeedRate' => 1.0,
            'BuildObjectHpRate' => 1.0, 'BuildObjectDamageRate' => 1.0,
            'BuildObjectDeteriorationDamageRate' => 1.0,
            'bEnableFastTravel' => true, 'bEnableFastTravelOnlyBaseCamp' => false,
            'bIsStartLocationSelectByMap' => true, 'bExistPlayerAfterLogout' => false,
            'bEnableDefenseOtherGuildPlayer' => false, 'bIsShowJoinLeftMessage' => true,
            'bEnableInvaderEnemy' => true, 'bUseAuth' => true, 'bIsUseBackupSaveData' => true,
            'AutoSaveSpan' => 30, 'BanListURL' => 'https://api.palworldgame.com/api/banlist.txt',
            'LogFormatType' => 'Text', 'CrossplayPlatforms' => '(Steam,Xbox,PS5,Mac)',
            // Death and difficulty
            'Difficulty' => 'None', 'DeathPenalty' => 'All', 'RandomizerType' => 'None',
            'RandomizerSeed' => '', 'bIsRandomizerPalLevelRandom' => false,
            'bHardcore' => false, 'bPalLost' => false, 'bEnablePlayerToPlayerDamage' => false,
            'bEnableFriendlyFire' => false, 'bEnableNonLoginPenalty' => true,
            'bCanPickupOtherGuildDeathPenaltyDrop' => false,
            'RespawnPenaltyDurationThreshold' => 0.0, 'RespawnPenaltyTimeScale' => 0.0,
            // Base, guild, and limits
            'BaseCampMaxNum' => 128, 'BaseCampWorkerMaxNum' => 15, 'BaseCampMaxNumInGuild' => 3,
            'GuildPlayerMaxNum' => 20, 'DropItemMaxNum' => 3000, 'DropItemMaxNum_UNKO' => 100,
            'DropItemAliveMaxHours' => 1.0, 'CoopPlayerMaxNum' => 4,
            'AutoResetGuildTimeNoOnlinePlayers' => 72.0, 'bAutoResetGuildNoOnlinePlayers' => false,
            'MaxBuildingLimitNum' => 0,
            // Advanced / present-only
            'bActiveUNKO' => false, 'bEnableAimAssistPad' => true, 'bEnableAimAssistKeyboard' => false,
            'bIsMultiplay' => false, 'bIsPvP' => false, 'bCharacterRecreateInHardcore' => false,
            'Region' => '', 'ChatPostLimitPerMinute' => 10, 'RESTAPIEnabled' => false,
            'RESTAPIPort' => 8212, 'bShowPlayerList' => false, 'EnablePredatorBossPal' => true,
            'ServerReplicatePawnCullDistance' => 15000.0, 'bAllowGlobalPalboxExport' => true,
            'bAllowGlobalPalboxImport' => false, 'ItemContainerForceMarkDirtyInterval' => 1.0,
            'ItemCorruptionMultiplier' => 1.0, 'bBuildAreaLimit' => false,
            'bInvisibleOtherGuildBaseCampAreaFX' => false,
        ];
    }

    /**
     * Resolve a field's default value, falling back to a sensible type-based
     * default when the field is not in the reference defaults map.
     */
    public function getDefaultValue(string $fieldKey, mixed $fallback = null): mixed
    {
        $defaults = $this->getDefaultValues();

        if (array_key_exists($fieldKey, $defaults)) {
            return $defaults[$fieldKey];
        }

        $definition = $this->getFieldDefinition($fieldKey);

        return match ($definition['type'] ?? 'string') {
            'boolean' => false,
            'number' => 1.0,
            'integer' => 0,
            'enum' => $definition['options'][0] ?? ($fallback ?? ''),
            default => '',
        };
    }

    /**
     * Themed presets. Every key must exist in getEditableGroups(); the page skips
     * any key not present in the current file. 'normal' carries no values — the page
     * derives it from getDefaultValue() so it always tracks the defaults.
     *
     * @return array<string, array{label: string, values: array<string, mixed>}>
     */
    public function getPresets(): array
    {
        return [
            'normal' => ['label' => 'Normal / Vanilla', 'values' => []],
            'casual' => ['label' => 'Casual', 'values' => $this->casualPresetValues()],
            'hardcore' => ['label' => 'Hardcore', 'values' => $this->hardcorePresetValues()],
            'pvp' => ['label' => 'PvP', 'values' => $this->pvpPresetValues()],
            'fast_progression' => ['label' => 'Fast Progression', 'values' => $this->fastProgressionPresetValues()],
        ];
    }

    /** @return array<string, mixed> */
    private function casualPresetValues(): array
    {
        return [
            'ExpRate' => 2.0,
            'PalCaptureRate' => 2.0,
            'PalEggDefaultHatchingTime' => 2.0,
            'CollectionDropRate' => 2.0,
            'EnemyDropItemRate' => 2.0,
            'WorkSpeedRate' => 2.0,
            'PlayerStomachDecreaceRate' => 0.5,
            'PlayerStaminaDecreaceRate' => 0.5,
            'PlayerAutoHPRegeneRate' => 2.0,
            'PlayerAutoHpRegeneRateInSleep' => 2.0,
            'PalStomachDecreaceRate' => 0.5,
            'PalStaminaDecreaceRate' => 0.5,
            'PalAutoHPRegeneRate' => 2.0,
            'ItemWeightRate' => 0.5,
            'EquipmentDurabilityDamageRate' => 0.5,
            'Difficulty' => 'None',
            'DeathPenalty' => 'None',
            'bHardcore' => false,
            'bPalLost' => false,
            'bEnableNonLoginPenalty' => false,
            'bEnablePlayerToPlayerDamage' => false,
            'bEnableFriendlyFire' => false,
            'bEnableInvaderEnemy' => false,
            'bEnableFastTravel' => true,
            'bIsPvP' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function hardcorePresetValues(): array
    {
        return [
            'ExpRate' => 0.5,
            'PalCaptureRate' => 0.5,
            'CollectionDropRate' => 0.75,
            'EnemyDropItemRate' => 0.75,
            'WorkSpeedRate' => 0.75,
            'PlayerDamageRateDefense' => 1.5,
            'PlayerStomachDecreaceRate' => 1.5,
            'PlayerStaminaDecreaceRate' => 1.5,
            'PlayerAutoHPRegeneRate' => 0.5,
            'PlayerAutoHpRegeneRateInSleep' => 0.5,
            'PalStomachDecreaceRate' => 1.5,
            'PalStaminaDecreaceRate' => 1.5,
            'ItemWeightRate' => 1.5,
            'EquipmentDurabilityDamageRate' => 1.5,
            'Difficulty' => 'Hard',
            'DeathPenalty' => 'All',
            'bHardcore' => true,
            'bCharacterRecreateInHardcore' => true,
            'bPalLost' => true,
            'bEnableNonLoginPenalty' => true,
            'bEnableInvaderEnemy' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function pvpPresetValues(): array
    {
        return [
            'bIsMultiplay' => true,
            'bIsPvP' => true,
            'bEnablePlayerToPlayerDamage' => true,
            'bEnableFriendlyFire' => true,
            'bEnableDefenseOtherGuildPlayer' => true,
            'bCanPickupOtherGuildDeathPenaltyDrop' => true,
            'DeathPenalty' => 'Item',
            'bAdditionalDropItemWhenPlayerKillingInPvPMode' => true,
            'AdditionalDropItemNumWhenPlayerKillingInPvPMode' => 1,
            'bDisplayPvPItemNumOnWorldMap_BaseCamp' => true,
            'bDisplayPvPItemNumOnWorldMap_Player' => true,
            'bEnableInvaderEnemy' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function fastProgressionPresetValues(): array
    {
        return [
            'ExpRate' => 5.0,
            'PalCaptureRate' => 4.0,
            'PalSpawnNumRate' => 2.0,
            'PalEggDefaultHatchingTime' => 1.0,
            'CollectionDropRate' => 4.0,
            'CollectionObjectRespawnSpeedRate' => 3.0,
            'EnemyDropItemRate' => 3.0,
            'WorkSpeedRate' => 4.0,
            'ItemWeightRate' => 0.5,
            'PlayerStaminaDecreaceRate' => 0.5,
            'PlayerStomachDecreaceRate' => 0.5,
            'DeathPenalty' => 'None',
            'bEnableFastTravel' => true,
        ];
    }
}
