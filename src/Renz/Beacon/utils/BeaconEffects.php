<?php

declare(strict_types=1);

namespace Renz\Beacon\utils;

use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\Position;

/**
 * Utility class for handling beacon effects
 */
class BeaconEffects {
    
    /**
     * Get the effect instance for a beacon primary effect
     * 
     * @param int $effectId
     * @param int $level Beacon pyramid level (1-4)
     * @param bool $enhanced Whether to use enhanced effects
     * @return EffectInstance|null
     */
    public static function getPrimaryEffectInstance(int $effectId, int $level, bool $enhanced = false): ?EffectInstance {
        $effect = self::getEffectById($effectId);
        if ($effect === null) {
            return null;
        }
        
        // Duration for beacon effects is typically very long (30 seconds)
        // but we'll refresh it frequently
        $duration = 30 * 20; // 30 seconds in ticks
        
        // Base amplifier calculation
        $amplifier = 0; // Default to level 1 effect
        
        if ($level >= 4 && self::isUpgradableEffect($effectId)) {
            $amplifier = 1; // Level 2 effect for level 4 beacon
        }
        
        // Apply enhanced effects if enabled
        if ($enhanced) {
            $amplifier = min($amplifier + 1, 3); // Cap at level 4 effect
            $duration = (int)($duration * 1.5); // 50% longer duration
        }
        
        return new EffectInstance($effect, $duration, $amplifier, false);
    }
    
    /**
     * Get the effect instance for a beacon secondary effect
     * 
     * @param int $effectId
     * @param bool $enhanced Whether to use enhanced effects
     * @return EffectInstance|null
     */
    public static function getSecondaryEffectInstance(int $effectId, bool $enhanced = false): ?EffectInstance {
        $effect = self::getEffectById($effectId);
        if ($effect === null) {
            return null;
        }
        
        // Secondary effects are typically level 1 and last for 30 seconds
        $duration = 30 * 20; // 30 seconds in ticks
        $amplifier = 0;
        
        // Apply enhanced effects if enabled
        if ($enhanced) {
            $amplifier = 1; // Upgrade to level 2
            $duration = (int)($duration * 1.2); // 20% longer duration
        }
        
        return new EffectInstance($effect, $duration, $amplifier, false);
    }
    
    /**
     * Get an effect by its ID
     * 
     * @param int $effectId
     * @return Effect|null
     */
    public static function getEffectById(int $effectId): ?Effect {
        return match($effectId) {
            1 => VanillaEffects::SPEED(),
            3 => VanillaEffects::HASTE(),
            5 => VanillaEffects::STRENGTH(),
            8 => VanillaEffects::JUMP_BOOST(),
            10 => VanillaEffects::REGENERATION(),
            11 => VanillaEffects::RESISTANCE(),
            _ => null
        };
    }
    
    /**
     * Check if an effect can be upgraded to level 2 in a level 4 beacon
     * 
     * @param int $effectId
     * @return bool
     */
    public static function isUpgradableEffect(int $effectId): bool {
        // Only certain effects can be upgraded to level 2
        return in_array($effectId, [1, 3, 5, 10, 11]);
    }
    
    /**
     * Get the maximum range of a beacon based on its level
     * 
     * @param int $level Beacon pyramid level (1-4)
     * @return int
     */
    public static function getBeaconRange(int $level): int {
        // Beacon range is 10 + (10 * level) blocks
        return 10 + (10 * $level);
    }
    
    /**
     * Apply beacon effects to all players in range
     * 
     * @param Position $beaconPos
     * @param int $primaryEffectId
     * @param int $secondaryEffectId
     * @param int $level Beacon pyramid level (1-4)
     * @param Config|null $config Plugin configuration
     */
    public static function applyEffectsInRange(Position $beaconPos, int $primaryEffectId, int $secondaryEffectId, int $level, ?Config $config = null): void {
        $rangeMultiplier = $config?->getNested('beacon.range-multiplier', 1.0) ?? 1.0;
        $enhancedEffects = $config?->getNested('beacon.enhanced-effects', false) ?? false;
        
        $range = (int)(self::getBeaconRange($level) * $rangeMultiplier);
        $world = $beaconPos->getWorld();
        
        // Get primary and secondary effect instances with possible enhancements
        $primaryEffect = self::getPrimaryEffectInstance($primaryEffectId, $level, $enhancedEffects);
        $secondaryEffect = self::getSecondaryEffectInstance($secondaryEffectId, $enhancedEffects);
        
        // If no valid effects, return early
        if ($primaryEffect === null && $secondaryEffect === null) {
            return;
        }
        
        $playersAffected = 0;
        
        // Apply effects to all players in range
        foreach ($world->getPlayers() as $player) {
            if ($player->getPosition()->distance($beaconPos) <= $range) {
                // Apply primary effect
                if ($primaryEffect !== null) {
                    $player->getEffects()->add($primaryEffect);
                }
                
                // Apply secondary effect (only if beacon is level 4)
                if ($level >= 4 && $secondaryEffect !== null) {
                    $player->getEffects()->add($secondaryEffect);
                }
                
                $playersAffected++;
            }
        }
        
        // Debug logging
        if ($config?->getNested('debug.log-effects', false) && $playersAffected > 0) {
            // Log would go to plugin logger, but we don't have access here
            // This could be improved by passing a logger instance
        }
    }
}