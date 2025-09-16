<?php

declare(strict_types=1);

namespace Renz\Beacon\utils;

use pocketmine\block\tile\Beacon as BeaconTile;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\utils\Config;
use pocketmine\world\World;

/**
 * Utility class for handling beacon beam rendering
 */
class BeaconBeamRenderer {
    
    /**
     * Update the beacon beam for a beacon tile
     * 
     * @param BeaconTile $beacon
     * @param int $level Beacon pyramid level (1-4)
     * @param Config|null $config Plugin configuration
     */
    public static function updateBeaconBeam(BeaconTile $beacon, int $level, ?Config $config = null): void {
        // Check if beams are enabled in config
        if (!($config?->getNested('beacon.enable-beams', true) ?? true)) {
            return;
        }
        
        // If beacon is not active (level 0), don't render beam
        if ($level <= 0) {
            return;
        }
        
        $pos = $beacon->getPosition();
        $world = $pos->getWorld();
        
        // Get the color based on the primary effect and config
        $useColoredBeams = $config?->getNested('beacon.colored-beams', true) ?? true;
        $color = $useColoredBeams ? 
            self::getBeamColor($beacon->getPrimaryEffect()) : 
            self::getDefaultBeamColor();
        
        // Create the beam event packet
        $pk = LevelEventPacket::create(
            LevelEvent::PARTICLE_BEAM,
            $color,
            $pos
        );
        
        // Broadcast to all players in the world
        $world->broadcastPacketToViewers($pos, $pk);
    }
    
    /**
     * Get the default (white) beam color
     * 
     * @return int RGBA color value
     */
    private static function getDefaultBeamColor(): int {
        // White color
        return (255 << 16) | (255 << 8) | 255 | (255 << 24);
    }
    
    /**
     * Get the color for a beacon beam based on the effect ID
     * 
     * @param int $effectId
     * @return int RGBA color value
     */
    public static function getBeamColor(int $effectId): int {
        // Default color is white (no effect)
        $r = 255;
        $g = 255;
        $b = 255;
        
        // Set color based on effect
        switch ($effectId) {
            case 1: // Speed - Cyan
                $r = 0;
                $g = 255;
                $b = 255;
                break;
            case 3: // Haste - Yellow
                $r = 255;
                $g = 255;
                $b = 0;
                break;
            case 5: // Strength - Red
                $r = 255;
                $g = 0;
                $b = 0;
                break;
            case 8: // Jump Boost - Green
                $r = 0;
                $g = 255;
                $b = 0;
                break;
            case 10: // Regeneration - Pink
                $r = 255;
                $g = 0;
                $b = 255;
                break;
            case 11: // Resistance - Purple
                $r = 128;
                $g = 0;
                $b = 255;
                break;
        }
        
        // Create RGBA color value (alpha is always 255)
        return ($r << 16) | ($g << 8) | $b | (255 << 24);
    }
}