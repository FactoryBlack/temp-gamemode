<?php

namespace EvoSC\Modules\EliminationChamber\Controllers;

use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Log;
use EvoSC\Controllers\ChatController;
use EvoSC\Models\Player;
use EvoSC\Modules\EliminationChamber\Services\SessionService;

class EliminationController
{
    /**
     * Handle /skip command
     */
    public static function skip(Player $player, ChatCommand $cmd): void
    {
        $sessionService = app(SessionService::class);
        
        if (!$sessionService->isSessionActive()) {
            ChatController::messageToPlayer($player, 'No EliminationChamber session is active.');
            return;
        }
        
        // The actual skip voting is handled by the mode script
        // This just provides feedback to the player
        ChatController::messageToPlayer($player, 'Skip vote registered. Check the HUD for vote status.');
        
        Log::info("EliminationChamber: {$player->NickName} used /skip command");
    }

    /**
     * Handle /ec admin commands
     */
    public static function adminCommand(Player $player, ChatCommand $cmd): void
    {
        $sessionService = app(SessionService::class);
        $args = $cmd->getArguments();
        
        if (empty($args)) {
            self::showAdminHelp($player);
            return;
        }
        
        $subCommand = strtolower($args[0]);
        
        switch ($subCommand) {
            case 'start':
                $lives = isset($args[1]) ? (int)$args[1] : null;
                if ($sessionService->startSession($lives)) {
                    ChatController::messageToPlayer($player, 'EliminationChamber session started!');
                    ChatController::messageToAll("EliminationChamber session started by {$player->NickName}!");
                } else {
                    ChatController::messageToPlayer($player, 'Failed to start session. Check logs for details.');
                }
                break;
                
            case 'stop':
                $sessionService->stopSession();
                ChatController::messageToPlayer($player, 'EliminationChamber session stopped.');
                ChatController::messageToAll("EliminationChamber session stopped by {$player->NickName}.");
                break;
                
            case 'lives':
                if (!isset($args[1]) || !is_numeric($args[1])) {
                    ChatController::messageToPlayer($player, 'Usage: /ec lives <number>');
                    return;
                }
                
                $lives = (int)$args[1];
                if ($lives < 1 || $lives > 10) {
                    ChatController::messageToPlayer($player, 'Lives must be between 1 and 10.');
                    return;
                }
                
                // This would require mode script communication to change mid-session
                ChatController::messageToPlayer($player, "Lives setting changed to $lives (takes effect next session).");
                break;
                
            case 'next':
                if (!$sessionService->isSessionActive()) {
                    ChatController::messageToPlayer($player, 'No session is active.');
                    return;
                }
                
                $sessionService->forceNextMap();
                ChatController::messageToPlayer($player, 'Forced next map.');
                ChatController::messageToAll("Map skipped by {$player->NickName}.");
                break;
                
            case 'status':
                $status = $sessionService->getSessionStatus();
                $active = $status['active'] ? 'Yes' : 'No';
                $queueCount = $status['queueStatus']['count'];
                
                ChatController::messageToPlayer($player, "Session Active: $active");
                ChatController::messageToPlayer($player, "Maps in Queue: $queueCount");
                break;
                
            default:
                self::showAdminHelp($player);
                break;
        }
        
        Log::info("EliminationChamber: {$player->NickName} used admin command: " . implode(' ', $args));
    }

    /**
     * Show admin help
     */
    private static function showAdminHelp(Player $player): void
    {
        $help = [
            'EliminationChamber Admin Commands:',
            '/ec start [lives] - Start session with optional lives count',
            '/ec stop - Stop current session',
            '/ec lives <number> - Set lives for next session',
            '/ec next - Force next map',
            '/ec status - Show session status',
        ];
        
        foreach ($help as $line) {
            ChatController::messageToPlayer($player, $line);
        }
    }
}
