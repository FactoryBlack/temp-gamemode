<?php

namespace EvoSC\Modules\EliminationChamber;

use EvoSC\Classes\Module;
use EvoSC\Classes\Config;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ChatController;
use EvoSC\Controllers\ModeController;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;
use EvoSC\Modules\EliminationChamber\Controllers\EliminationController;
use EvoSC\Modules\EliminationChamber\Services\SessionService;
use EvoSC\Modules\EliminationChamber\Services\TmxService;

class EliminationChamberModule extends Module implements ModuleInterface
{
    /**
     * Called when the module is loaded
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->loadDefaultConfig();
        
        // Register services
        app()->singleton(SessionService::class);
        app()->singleton(TmxService::class);
    }

    /**
     * Load default configuration
     */
    private function loadDefaultConfig()
    {
        $defaultConfig = [
            'livesStart' => 3,
            'skipThresholdPercent' => 51,
            'roundTimeoutSec' => 300,
            'winnersCount' => 1,
            'tmxApiKey' => '',
            'tmxFilters' => [
                'style' => 'Race',
                'lengthMin' => 30,
                'lengthMax' => 90,
                'uploadedAfter' => '2020-01-01'
            ],
            'mapsPath' => 'UserData/Maps/EliminationChamber/',
            'matchsettingsPath' => 'UserData/Maps/MatchSettings/EliminationChamber.txt'
        ];
        
        Config::set('EliminationChamber', $defaultConfig);
    }

    /**
     * Called when EvoSC starts
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        $config = Config::get('EliminationChamber');
        if (!$config) {
            throw new \Exception('EliminationChamber: Module configuration not found');
        }
        
        // Register access rights
        AccessRight::createIfMissing('elimination_chamber', 'Control EliminationChamber sessions');
        
        // Register chat commands
        ChatController::addCommand('skip', [EliminationController::class, 'skip'], 'Vote to skip current map');
        ChatController::addCommand('ec', [EliminationController::class, 'adminCommand'], 'EliminationChamber admin commands', 'elimination_chamber');
        
        // Hook into mode events
        ModeController::addHook('BeginMap', [SessionService::class, 'onBeginMap']);
        ModeController::addHook('EndMap', [SessionService::class, 'onEndMap']);
        ModeController::addHook('PlayerConnect', [SessionService::class, 'onPlayerConnect']);
        ModeController::addHook('PlayerDisconnect', [SessionService::class, 'onPlayerDisconnect']);
        
        // Start session service
        app(SessionService::class)->initialize();
    }

    /**
     * Called when EvoSC stops
     */
    public static function stop(string $mode, bool $isShutdown = false)
    {
        // Clean up session
        app(SessionService::class)->stopSession();
    }

    /**
     * Get module information
     */
    public static function getModuleInfo(): object
    {
        return (object)[
            'name' => 'EliminationChamber',
            'title' => 'Elimination Chamber',
            'description' => 'Server-authoritative elimination gamemode with lives system',
            'version' => '1.0.0',
            'author' => 'EvoSC Community',
            'dependencies' => [],
        ];
    }

    public static function getSettings(): array
    {
        return [
            'livesStart' => [
                'type' => 'integer',
                'default' => 3,
                'description' => 'Starting number of lives per player'
            ],
            'skipThresholdPercent' => [
                'type' => 'integer', 
                'default' => 51,
                'description' => 'Percentage of unsafe players needed to skip'
            ],
            'roundTimeoutSec' => [
                'type' => 'integer',
                'default' => 300,
                'description' => 'Round timeout in seconds (0 = disabled)'
            ],
            'winnersCount' => [
                'type' => 'integer',
                'default' => 1,
                'description' => 'Number of survivors needed to end session'
            ],
            'tmxApiKey' => [
                'type' => 'string',
                'default' => '',
                'description' => 'TMX API key for fetching maps'
            ],
            'tmxFilters' => [
                'type' => 'array',
                'default' => [
                    'style' => 'Race',
                    'lengthMin' => 30,
                    'lengthMax' => 90,
                    'uploadedAfter' => '2020-01-01'
                ],
                'description' => 'Filters for fetching maps from TMX'
            ],
            'mapsPath' => [
                'type' => 'string',
                'default' => 'UserData/Maps/EliminationChamber/',
                'description' => 'Path to store downloaded maps'
            ],
            'matchsettingsPath' => [
                'type' => 'string',
                'default' => 'UserData/Maps/MatchSettings/EliminationChamber.txt',
                'description' => 'Path to match settings file'
            ]
        ];
    }

    public static function getConfig(): array
    {
        return Config::get('EliminationChamber') ?? [];
    }
}
