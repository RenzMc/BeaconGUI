<?php

declare(strict_types=1);

namespace Renz\Beacon\utils;

use pocketmine\block\VanillaBlocks;
use pocketmine\block\tile\Beacon as BeaconTile;
use pocketmine\utils\Config;
use pocketmine\world\World;

/**
 * Utility class for calculating beacon pyramid levels with caching support
 */
class BeaconPyramidCalculator {
    
    private static array $levelCache = [];
    private static int $lastCacheClear = 0;
    
    /**
     * Calculate the beacon pyramid level (0-4) with caching
     * 
     * @param BeaconTile $beacon
     * @param Config|null $config Plugin configuration
     * @return int
     */
    public static function calculateLevel(BeaconTile $beacon, ?Config $config = null): int {
        $enableCaching = $config?->getNested('performance.enable-caching', true) ?? true;
        $cacheTimeout = $config?->getNested('performance.cache-timeout', 30) ?? 30;
        
        $pos = $beacon->getPosition();
        $cacheKey = $pos->getWorld()->getFolderName() . ':' . $pos->getFloorX() . ':' . $pos->getFloorY() . ':' . $pos->getFloorZ();
        
        // Clear cache periodically
        $currentTime = time();
        if ($currentTime - self::$lastCacheClear > $cacheTimeout) {
            self::$levelCache = [];
            self::$lastCacheClear = $currentTime;
        }
        
        // Check cache if enabled
        if ($enableCaching && isset(self::$levelCache[$cacheKey])) {
            return self::$levelCache[$cacheKey];
        }
        
        // Calculate level
        $level = self::calculateLevelInternal($beacon);
        
        // Store in cache if enabled
        if ($enableCaching) {
            self::$levelCache[$cacheKey] = $level;
        }
        
        return $level;
    }
    
    /**
     * Internal method to calculate beacon level
     * 
     * @param BeaconTile $beacon
     * @return int
     */
    private static function calculateLevelInternal(BeaconTile $beacon): int {
        $pos = $beacon->getPosition();
        $world = $pos->getWorld();
        
        // Check each level of the pyramid
        for ($level = 1; $level <= 4; $level++) {
            $size = $level * 2 + 1;
            $y = $pos->getFloorY() - $level;
            
            // Skip if below world
            if ($y < $world->getMinY()) {
                return $level - 1;
            }
            
            // Check if this level is complete
            if (!self::isLevelComplete($world, $pos->getFloorX(), $y, $pos->getFloorZ(), $size)) {
                return $level - 1;
            }
        }
        
        return 4; // All levels complete
    }
    
    /**
     * Check if a pyramid level is complete
     * 
     * @param World $world
     * @param int $centerX
     * @param int $y
     * @param int $centerZ
     * @param int $size
     * @return bool
     */
    public static function isLevelComplete(World $world, int $centerX, int $y, int $centerZ, int $size): bool {
        $halfSize = (int)($size / 2);
        
        for ($x = $centerX - $halfSize; $x <= $centerX + $halfSize; $x++) {
            for ($z = $centerZ - $halfSize; $z <= $centerZ + $halfSize; $z++) {
                $block = $world->getBlockAt($x, $y, $z);
                
                // Check if the block is a valid beacon base block
                if (!self::isValidBeaconBase($block)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Check if a block is a valid beacon base using modern VanillaBlocks
     * 
     * @param mixed $block
     * @return bool
     */
    public static function isValidBeaconBase(mixed $block): bool {
        // Valid beacon base blocks are solid metal blocks:
        // - Iron Block (not ore)
        // - Gold Block (not ore) 
        // - Diamond Block (not ore)
        // - Emerald Block (not ore)
        // - Netherite Block
        $validBlocks = [
            VanillaBlocks::IRON(),
            VanillaBlocks::GOLD(),
            VanillaBlocks::DIAMOND(),
            VanillaBlocks::EMERALD(),
            VanillaBlocks::NETHERITE()
        ];
        
        foreach ($validBlocks as $validBlock) {
            if ($block->isSameType($validBlock)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Clear the level cache manually
     */
    public static function clearCache(): void {
        self::$levelCache = [];
        self::$lastCacheClear = time();
    }
}