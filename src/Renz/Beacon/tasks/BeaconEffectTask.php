<?php

declare(strict_types=1);

namespace Renz\Beacon\tasks;

use pocketmine\block\tile\Beacon as BeaconTile;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\world\World;
use Renz\Beacon\utils\BeaconBeamRenderer;
use Renz\Beacon\utils\BeaconEffects;
use Renz\Beacon\utils\BeaconPyramidCalculator;

/**
 * Task to periodically apply beacon effects to players and update beacon beams
 */
class BeaconEffectTask extends Task {
    
    private Server $server;
    private Config $config;
    private array $beaconCache = [];
    private int $lastCacheClear = 0;
    
    /**
     * BeaconEffectTask constructor.
     * 
     * @param Server $server
     * @param Config $config
     */
    public function __construct(Server $server, Config $config) {
        $this->server = $server;
        $this->config = $config;
    }
    
    /**
     * Execute the task
     */
    public function onRun(): void {
        // Clear cache periodically
        $currentTime = time();
        $cacheTimeout = $this->config->getNested('performance.cache-timeout', 30);
        
        if ($currentTime - $this->lastCacheClear > $cacheTimeout) {
            $this->beaconCache = [];
            $this->lastCacheClear = $currentTime;
        }
        
        // Process all loaded worlds with performance limiting
        $maxBeaconsPerTick = $this->config->getNested('performance.max-beacons-per-tick', 10);
        $processedBeacons = 0;
        
        foreach ($this->server->getWorldManager()->getWorlds() as $world) {
            if ($processedBeacons >= $maxBeaconsPerTick) {
                break;
            }
            $processedBeacons += $this->processBeaconsInWorld($world, $maxBeaconsPerTick - $processedBeacons);
        }
    }
    
    /**
     * Process all beacons in a world
     * 
     * @param World $world
     * @param int $maxBeacons Maximum beacons to process
     * @return int Number of beacons processed
     */
    private function processBeaconsInWorld(World $world, int $maxBeacons): int {
        $processed = 0;
        
        // Get all beacon tiles in the world
        foreach ($world->getTiles() as $tile) {
            if ($processed >= $maxBeacons) {
                break;
            }
            
            if ($tile instanceof BeaconTile) {
                $this->processBeacon($tile);
                $processed++;
            }
        }
        
        return $processed;
    }
    
    /**
     * Process a single beacon
     * 
     * @param BeaconTile $beacon
     */
    private function processBeacon(BeaconTile $beacon): void {
        // Get beacon position
        $pos = $beacon->getPosition();
        
        // Calculate beacon level (1-4) based on pyramid structure with caching
        $level = BeaconPyramidCalculator::calculateLevel($beacon, $this->config);
        
        // Update the beacon beam with configuration
        BeaconBeamRenderer::updateBeaconBeam($beacon, $level, $this->config);
        
        // If beacon is not active (level 0), skip effects
        if ($level <= 0) {
            return;
        }
        
        // Get beacon effects
        $primaryEffectId = $beacon->getPrimaryEffect();
        $secondaryEffectId = $beacon->getSecondaryEffect();
        
        // Apply effects to players in range with configuration
        BeaconEffects::applyEffectsInRange($pos, $primaryEffectId, $secondaryEffectId, $level, $this->config);
    }
}