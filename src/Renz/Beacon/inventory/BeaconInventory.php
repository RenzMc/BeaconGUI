<?php

declare(strict_types=1);

namespace Renz\Beacon\inventory;

use pocketmine\block\inventory\BlockInventory;
use pocketmine\block\tile\Beacon as BeaconTile;
use pocketmine\inventory\SimpleInventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\player\Player;
use pocketmine\world\Position;

/**
 * Represents a beacon inventory with a single payment slot
 */
class BeaconInventory extends SimpleInventory implements BlockInventory {
    
    public const SLOT_FUEL = 0;
    
    private BeaconTile $beacon;
    private ?Player $viewer = null;
    public ?int $windowId = null;
    
    /**
     * BeaconInventory constructor.
     * 
     * @param BeaconTile $beacon The beacon tile entity
     */
    public function __construct(BeaconTile $beacon) {
        parent::__construct(1); // Beacon has only 1 slot
        $this->beacon = $beacon;
    }
    
    /**
     * Get the beacon tile entity
     * 
     * @return BeaconTile
     */
    public function getBeacon(): BeaconTile {
        return $this->beacon;
    }
    
    /**
     * Get the position of the beacon block
     * 
     * @return Position
     */
    public function getHolder(): Position {
        return $this->beacon->getPosition();
    }
    
    /**
     * Get the window type for this inventory
     * 
     * @return int
     */
    public function getWindowType(): int {
        return WindowTypes::BEACON;
    }
    
    /**
     * Set the current viewer of this inventory
     * 
     * @param Player|null $player
     */
    public function setViewer(?Player $player): void {
        $this->viewer = $player;
    }
    
    /**
     * Get the current viewer of this inventory
     * 
     * @return Player|null
     */
    public function getViewer(): ?Player {
        return $this->viewer;
    }
    
    /**
     * Check if the item is valid for beacon payment
     * 
     * @param Item $item
     * @return bool
     */
    public function isValidPayment(Item $item): bool {
        // Valid payment items for beacon are:
        // - Iron Ingot
        // - Gold Ingot
        // - Diamond
        // - Emerald
        // - Netherite Ingot
        $validPaymentItems = [
            VanillaItems::IRON_INGOT(),
            VanillaItems::GOLD_INGOT(),
            VanillaItems::DIAMOND(),
            VanillaItems::EMERALD(),
            VanillaItems::NETHERITE_INGOT()
        ];
        
        foreach ($validPaymentItems as $validItem) {
            if ($item->getTypeId() === $validItem->getTypeId()) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all valid payment items for beacon
     * 
     * @return Item[]
     */
    public function getValidPaymentItems(): array {
        return [
            VanillaItems::IRON_INGOT(),
            VanillaItems::GOLD_INGOT(),
            VanillaItems::DIAMOND(),
            VanillaItems::EMERALD(),
            VanillaItems::NETHERITE_INGOT()
        ];
    }
    
    /**
     * Get payment item tier (affects beacon power)
     * Higher tier items provide stronger beacon effects
     * 
     * @param Item $item
     * @return int 0 = invalid, 1-5 = tier level
     */
    public function getPaymentTier(Item $item): int {
        return match($item->getTypeId()) {
            VanillaItems::IRON_INGOT()->getTypeId() => 1,
            VanillaItems::GOLD_INGOT()->getTypeId() => 2,
            VanillaItems::EMERALD()->getTypeId() => 3,
            VanillaItems::DIAMOND()->getTypeId() => 4,
            VanillaItems::NETHERITE_INGOT()->getTypeId() => 5,
            default => 0
        };
    }
    
    /**
     * Get the fuel item in the beacon
     * 
     * @return Item
     */
    public function getFuelItem(): Item {
        return $this->getItem(self::SLOT_FUEL);
    }
    
    /**
     * Set the fuel item in the beacon
     * 
     * @param Item $item
     */
    public function setFuelItem(Item $item): void {
        $this->setItem(self::SLOT_FUEL, $item);
    }
}