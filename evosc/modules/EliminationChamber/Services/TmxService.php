<?php

namespace EvoSC\Modules\EliminationChamber\Services;

use EvoSC\Classes\File;
use EvoSC\Classes\Log;
use EvoSC\Controllers\MapController;
use EvoSC\Models\Map;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class TmxService
{
    private Client $httpClient;
    private array $config;
    private array $mapQueue = [];

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => false,
        ]);
        
        $this->config = config('elimination-chamber');
    }

    /**
     * Search and queue random maps from TMX
     */
    public function queueRandomMaps(int $count = 10): bool
    {
        try {
            $filters = $this->config['tmxFilters'] ?? [];
            
            // Build TMX API query
            $params = [
                'api' => 'on',
                'format' => 'json',
                'limit' => $count * 2, // Get more than needed for randomization
                'random' => '1',
                'style' => $filters['style'] ?? 'Race',
                'lengthop' => '1', // Between
                'length' => ($filters['minLength'] ?? 30) . ',' . ($filters['maxLength'] ?? 90),
                'uploaded' => 'since:' . ($filters['uploadedAfter'] ?? '2020-01-01'),
            ];
            
            if (!empty($filters['difficulty'])) {
                $params['difficulty'] = $filters['difficulty'];
            }
            
            $url = 'https://trackmania.exchange/mapsearch2/search?' . http_build_query($params);
            
            Log::info("EliminationChamber: Searching TMX with URL: $url");
            
            $response = $this->httpClient->get($url);
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!$data || !isset($data['results'])) {
                Log::error('EliminationChamber: Invalid TMX response');
                return false;
            }
            
            // Randomize and select maps
            shuffle($data['results']);
            $selectedMaps = array_slice($data['results'], 0, $count);
            
            foreach ($selectedMaps as $mapData) {
                $this->mapQueue[] = [
                    'tmxId' => $mapData['TrackID'],
                    'name' => $mapData['Name'],
                    'author' => $mapData['Username'],
                    'authorTime' => $mapData['AuthorTime'],
                    'downloaded' => false,
                ];
            }
            
            Log::info("EliminationChamber: Queued " . count($selectedMaps) . " maps from TMX");
            return true;
            
        } catch (RequestException $e) {
            Log::error("EliminationChamber: TMX request failed: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error("EliminationChamber: TMX search error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Download and add next map from queue
     */
    public function addNextMapFromQueue(): ?Map
    {
        if (empty($this->mapQueue)) {
            Log::warning('EliminationChamber: Map queue is empty');
            return null;
        }
        
        $mapInfo = array_shift($this->mapQueue);
        
        try {
            // Check if map already exists
            $existingMap = Map::whereFilename($mapInfo['tmxId'] . '.Map.Gbx')->first();
            if ($existingMap) {
                Log::info("EliminationChamber: Map {$mapInfo['name']} already exists, adding to matchsettings");
                $this->addToMatchsettings($existingMap);
                return $existingMap;
            }
            
            // Download map
            $gbxData = $this->downloadMapGbx($mapInfo['tmxId']);
            if (!$gbxData) {
                Log::error("EliminationChamber: Failed to download map {$mapInfo['tmxId']}");
                return null;
            }
            
            // Save to maps directory
            $mapsPath = $this->config['mapsPath'] ?? 'UserData/Maps/';
            $filename = $mapInfo['tmxId'] . '.Map.Gbx';
            $filepath = $mapsPath . $filename;
            
            if (!File::put($filepath, $gbxData)) {
                Log::error("EliminationChamber: Failed to save map file: $filepath");
                return null;
            }
            
            // Add to database and matchsettings
            $map = MapController::addMap($filepath);
            if ($map) {
                $this->addToMatchsettings($map);
                Log::info("EliminationChamber: Successfully added map: {$mapInfo['name']}");
                return $map;
            }
            
        } catch (\Exception $e) {
            Log::error("EliminationChamber: Error adding map from queue: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Download map GBX file from TMX
     */
    private function downloadMapGbx(int $tmxId): ?string
    {
        try {
            $url = "https://trackmania.exchange/maps/download/$tmxId";
            $response = $this->httpClient->get($url);
            
            if ($response->getStatusCode() === 200) {
                return $response->getBody()->getContents();
            }
            
        } catch (RequestException $e) {
            Log::error("EliminationChamber: Failed to download map $tmxId: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Add map to matchsettings
     */
    private function addToMatchsettings(Map $map): void
    {
        try {
            $matchsettingsPath = $this->config['matchsettingsPath'] ?? 'UserData/Maps/MatchSettings/elimination_chamber.txt';
            
            // Read existing matchsettings or create new
            $matchsettings = [];
            if (File::exists($matchsettingsPath)) {
                $content = File::get($matchsettingsPath);
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line) && !str_starts_with($line, '#')) {
                        $matchsettings[] = $line;
                    }
                }
            }
            
            // Add map if not already present
            $mapPath = $map->filename;
            if (!in_array($mapPath, $matchsettings)) {
                $matchsettings[] = $mapPath;
                
                // Write back to file
                $content = "# EliminationChamber Matchsettings\n" . implode("\n", $matchsettings) . "\n";
                File::put($matchsettingsPath, $content);
                
                Log::info("EliminationChamber: Added {$map->name} to matchsettings");
            }
            
        } catch (\Exception $e) {
            Log::error("EliminationChamber: Error updating matchsettings: " . $e->getMessage());
        }
    }

    /**
     * Get queue status
     */
    public function getQueueStatus(): array
    {
        return [
            'count' => count($this->mapQueue),
            'maps' => $this->mapQueue,
        ];
    }

    /**
     * Clear map queue
     */
    public function clearQueue(): void
    {
        $this->mapQueue = [];
        Log::info('EliminationChamber: Map queue cleared');
    }
}
