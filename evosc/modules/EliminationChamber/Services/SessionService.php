<?php

namespace EvoSC\Modules\EliminationChamber\Services;

use EvoSC\Classes\Log;
use EvoSC\Classes\Template;
use EvoSC\Controllers\ModeController;
use EvoSC\Controllers\PlayerController;
use EvoSC\Models\Player;
use EvoSC\Modules\EliminationChamber\Services\TmxService;

class SessionService
{
    private bool $sessionActive = false;
    private array $playerStates = [];
    private array $config;
    private TmxService $tmxService;

    public function __construct(TmxService $tmxService)
    {
        $this->tmxService = $tmxService;
        $this->config = config('elimination-chamber');
    }

    /**
     * Initialize the service
     */
    public function initialize(): void
    {
        Log::info('EliminationChamber: SessionService initialized');
    }

    /**
     * Start a new elimination session
     */
    public function startSession(int $lives = null): bool
    {
        if ($this->sessionActive) {
            Log::warning('EliminationChamber: Session already active');
            return false;
        }

        try {
            $lives = $lives ?? $this->config['livesStart'] ?? 3;
            
            // Queue maps from TMX
            if (!$this->tmxService->queueRandomMaps(20)) {
                Log::error('EliminationChamber: Failed to queue maps from TMX');
                return false;
            }
            
            // Add first map
            $map = $this->tmxService->addNextMapFromQueue();
            if (!$map) {
                Log::error('EliminationChamber: Failed to add first map');
                return false;
            }
            
            // Set mode to EliminationChamber
            ModeController::setMode('EliminationChamber.Script.txt');
            
            // Configure mode settings
            $this->setModeSettings([
                'S_LivesStart' => $lives,
                'S_SkipThresholdPercent' => $this->config['skipThresholdPercent'] ?? 51,
                'S_RoundTimeoutSec' => $this->config['roundTimeoutSec'] ?? 300,
                'S_WinnersCount' => $this->config['winnersCount'] ?? 1,
            ]);
            
            // Load matchsettings and restart
            $matchsettingsPath = $this->config['matchsettingsPath'] ?? 'UserData/Maps/MatchSettings/elimination_chamber.txt';
            ModeController::loadMatchSettings($matchsettingsPath);
            ModeController::restartMap();
            
            $this->sessionActive = true;
            $this->playerStates = [];
            
            // Show HUD to all players
            $this->showHudToAll();
            
            Log::info("EliminationChamber: Session started with $lives lives");
            return true;
            
        } catch (\Exception $e) {
            Log::error("EliminationChamber: Failed to start session: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Stop the current session
     */
    public function stopSession(): void
    {
        if (!$this->sessionActive) {
            return;
        }

        $this->sessionActive = false;
        $this->playerStates = [];
        
        // Hide HUD from all players
        $this->hideHudFromAll();
        
        // Clear map queue
        $this->tmxService->clearQueue();
        
        Log::info('EliminationChamber: Session stopped');
    }

    /**
     * Set mode settings
     */
    private function setModeSettings(array $settings): void
    {
        foreach ($settings as $setting => $value) {
            ModeController::setModeScriptSetting($setting, $value);
        }
    }

    /**
     * Update player lives
     */
    public function setPlayerLives(Player $player, int $lives): void
    {
        if (!$this->sessionActive) {
            return;
        }
        
        // This would require custom mode script communication
        // For now, log the action
        Log::info("EliminationChamber: Set {$player->NickName} lives to $lives");
    }

    /**
     * Force next map
     */
    public function forceNextMap(): void
    {
        if (!$this->sessionActive) {
            return;
        }
        
        // Add next map from queue
        $this->tmxService->addNextMapFromQueue();
        
        // Skip to next map
        ModeController::nextMap();
        
        Log::info('EliminationChamber: Forced next map');
    }

    /**
     * Get session status
     */
    public function getSessionStatus(): array
    {
        return [
            'active' => $this->sessionActive,
            'playerStates' => $this->playerStates,
            'queueStatus' => $this->tmxService->getQueueStatus(),
        ];
    }

    /**
     * Event: Begin map
     */
    public function onBeginMap(): void
    {
        if (!$this->sessionActive) {
            return;
        }
        
        // Update HUD for all players
        $this->updateHudForAll();
        
        // Add next map to queue if running low
        $queueStatus = $this->tmxService->getQueueStatus();
        if ($queueStatus['count'] < 5) {
            $this->tmxService->queueRandomMaps(10);
        }
        
        Log::info('EliminationChamber: Map began');
    }

    /**
     * Event: End map
     */
    public function onEndMap(): void
    {
        if (!$this->sessionActive) {
            return;
        }
        
        // Add next map from queue
        $this->tmxService->addNextMapFromQueue();
        
        // Check if session should end (handled by mode script)
        $this->updatePlayerStatesFromMode();
        
        Log::info('EliminationChamber: Map ended');
    }

    /**
     * Event: Player connect
     */
    public function onPlayerConnect(Player $player): void
    {
        if (!$this->sessionActive) {
            return;
        }
        
        // Show HUD to new player
        $this->showHudToPlayer($player);
        
        Log::info("EliminationChamber: Player {$player->NickName} connected");
    }

    /**
     * Event: Player disconnect
     */
    public function onPlayerDisconnect(Player $player): void
    {
        if (!$this->sessionActive) {
            return;
        }
        
        // Remove from player states
        unset($this->playerStates[$player->Login]);
        
        Log::info("EliminationChamber: Player {$player->NickName} disconnected");
    }

    /**
     * Update player states from mode script
     */
    private function updatePlayerStatesFromMode(): void
    {
        // This would read from mode script global variables
        // Implementation depends on EvoSC's mode script communication system
        // For now, this is a placeholder
    }

    /**
     * Show HUD to all players
     */
    private function showHudToAll(): void
    {
        foreach (PlayerController::getPlayers() as $player) {
            $this->showHudToPlayer($player);
        }
    }

    /**
     * Show HUD to specific player
     */
    private function showHudToPlayer(Player $player): void
    {
        $hudXml = Template::toString('EliminationChamber.Views.Hud', [
            'player' => $player,
            'config' => $this->config,
        ]);
        
        PlayerController::sendManialink($player, $hudXml);
    }

    /**
     * Update HUD for all players
     */
    private function updateHudForAll(): void
    {
        foreach (PlayerController::getPlayers() as $player) {
            $this->updateHudForPlayer($player);
        }
    }

    /**
     * Update HUD for specific player
     */
    private function updateHudForPlayer(Player $player): void
    {
        // Send updated data to HUD
        $data = [
            'lives' => $this->playerStates[$player->Login]['lives'] ?? 0,
            'status' => $this->playerStates[$player->Login]['status'] ?? 'UNKNOWN',
            'skipVotes' => 0, // From mode script
            'skipThreshold' => 0, // From mode script
            'timeLeft' => 0, // From mode script
        ];
        
        PlayerController::sendManialinkAction($player, 'EliminationChamber.UpdateHud', $data);
    }

    /**
     * Hide HUD from all players
     */
    private function hideHudFromAll(): void
    {
        foreach (PlayerController::getPlayers() as $player) {
            PlayerController::sendManialink($player, '<manialink id="EliminationChamber.Hud"></manialink>');
        }
    }

    /**
     * Check if session is active
     */
    public function isSessionActive(): bool
    {
        return $this->sessionActive;
    }
}
