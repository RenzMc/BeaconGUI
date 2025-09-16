<?php

declare(strict_types=1);

namespace Renz\Beacon\inventory\handler;

use pocketmine\block\tile\Beacon as BeaconTile;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\Inventory;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\ItemStackRequestPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\BeaconPaymentStackRequestAction;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use Renz\Beacon\inventory\BeaconInventory;
use Renz\Beacon\network\BeaconStackRequestHandler;

class BeaconInventoryHandler implements Listener {
    
    private PluginBase $plugin;
    private Config $config;
    
    /** @var array<string, BeaconInventory> */
    private array $openInventories = [];
    
    /**
     * BeaconInventoryHandler constructor.
     * 
     * @param PluginBase $plugin
     */
    public function __construct(PluginBase $plugin) {
        $this->plugin = $plugin;
        $this->config = $plugin->getConfig();
    }
    
    /**
     * Register event listeners
     */
    public function register(): void {
        $this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
    }
    
    /**
     * Open a beacon inventory for a player
     * 
     * @param Player $player
     * @param BeaconTile $beacon
     */
    public function openBeaconInventory(Player $player, BeaconTile $beacon): void {
        // Check permission
        if (!$player->hasPermission('beacon.use')) {
            $player->sendMessage(TextFormat::RED . "You don't have permission to use beacons.");
            return;
        }
        
        try {
            // Create beacon inventory
            $inventory = new BeaconInventory($beacon);
            $inventory->setViewer($player);
            
            // Store the open inventory
            $this->openInventories[$player->getName()] = $inventory;
            
            // Get the network session
            $networkSession = $player->getNetworkSession();
            $inventoryManager = $networkSession->getInvManager();
            
            // Override the container open callback for this specific inventory
            $inventoryManager->getContainerOpenCallbacks()->add(function(int $id, Inventory $inv) use ($beacon): ?array {
                if ($inv instanceof BeaconInventory) {
                    return [
                        ContainerOpenPacket::blockInv(
                            $id, 
                            WindowTypes::BEACON, 
                            BlockPosition::fromVector3($beacon->getPosition())
                        )
                    ];
                }
                return null;
            });
            
            // Add window to inventory manager (this handles packet sending automatically)
            $windowId = $inventoryManager->addWindow($inventory);
            
            if ($windowId === null) {
                $this->plugin->getLogger()->warning("Failed to assign window ID for beacon inventory for player " . $player->getName());
                unset($this->openInventories[$player->getName()]);
                return;
            }
            
            // Store the window ID for validation
            $this->openInventories[$player->getName()]->windowId = $windowId;
            
            // Log interaction if debug enabled
            if ($this->config->getNested('debug.log-interactions', false)) {
                $pos = $beacon->getPosition();
                $this->plugin->getLogger()->info("Player {$player->getName()} opened beacon at " . $pos->__toString());
            }
            
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Error opening beacon inventory for {$player->getName()}: " . $e->getMessage());
            $player->sendMessage(TextFormat::RED . "An error occurred while opening the beacon. Please try again.");
        }
    }
    
    /**
     * Handle packet receive event to process beacon interactions
     * 
     * @param DataPacketReceiveEvent $event
     */
    public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
        $packet = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();
        
        if ($player === null) {
            return;
        }
        
        // Handle ItemStackRequest packets for beacon interactions
        if ($packet instanceof ItemStackRequestPacket) {
            foreach ($packet->requests as $request) {
                foreach ($request->actions as $action) {
                    if ($action instanceof BeaconPaymentStackRequestAction) {
                        $this->handleBeaconPayment($player, $action, $request->requestId);
                        $event->cancel(); // Cancel the packet to handle it ourselves
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * Handle beacon payment action with proper inventory validation
     * 
     * @param Player $player
     * @param BeaconPaymentStackRequestAction $action
     * @param int $requestId
     */
    private function handleBeaconPayment(Player $player, BeaconPaymentStackRequestAction $action, int $requestId): void {
        // Check if player has an open beacon inventory
        if (!isset($this->openInventories[$player->getName()])) {
            $this->plugin->getLogger()->warning("Beacon payment attempt without open inventory by {$player->getName()}");
            BeaconStackRequestHandler::sendBeaconResponse($player, null, $action, $requestId, false);
            return;
        }
        
        $inventory = $this->openInventories[$player->getName()];
        $beacon = $inventory->getBeacon();
        
        // Validate window ID to prevent spoofed requests
        $networkSession = $player->getNetworkSession();
        $currentWindowId = $networkSession->getInvManager()->getWindowId($inventory);
        
        if ($currentWindowId === null || $currentWindowId !== $inventory->windowId) {
            $this->plugin->getLogger()->warning("Invalid window ID in beacon payment by {$player->getName()}");
            BeaconStackRequestHandler::sendBeaconResponse($player, $inventory, $action, $requestId, false);
            return;
        }
        
        // Get the primary and secondary effect from the action
        $primaryEffectId = $action->primaryEffectId;
        $secondaryEffectId = $action->secondaryEffectId;
        
        // Calculate beacon level to validate effect choices
        $level = \Renz\Beacon\utils\BeaconPyramidCalculator::calculateLevel($beacon, $this->config);
        
        // Validate effect choices against beacon level (vanilla behavior)
        if (!$this->isValidEffectForLevel($primaryEffectId, $level) || 
            ($level < 4 && $secondaryEffectId >= 0) ||
            ($level >= 4 && $secondaryEffectId >= 0 && !$this->isValidSecondaryEffect($secondaryEffectId))) {
            $player->sendMessage(TextFormat::RED . "Invalid effect choice for beacon level {$level}!");
            BeaconStackRequestHandler::sendBeaconResponse($player, $inventory, $action, $requestId, false);
            return;
        }
        
        // Get payment item from player's inventory, not beacon inventory
        $playerInventory = $player->getInventory();
        $paymentItem = null;
        $paymentSlot = -1;
        
        // Find valid payment item in player's inventory
        for ($slot = 0; $slot < $playerInventory->getSize(); $slot++) {
            $item = $playerInventory->getItem($slot);
            if (!$item->isNull() && $inventory->isValidPayment($item)) {
                $paymentItem = $item;
                $paymentSlot = $slot;
                break;
            }
        }
        
        $success = false;
        
        if ($paymentItem !== null && $paymentSlot >= 0) {
            try {
                // Validate beacon tile still exists at expected position
                $beaconPos = $beacon->getPosition();
                $currentTile = $beaconPos->getWorld()->getTile($beaconPos);
                if (!($currentTile instanceof BeaconTile)) {
                    $player->sendMessage(TextFormat::RED . "Beacon no longer exists at this location!");
                    BeaconStackRequestHandler::sendBeaconResponse($player, $inventory, $action, $requestId, false);
                    return;
                }
                
                // Atomically debit the player's inventory
                $newItem = clone $paymentItem;
                $newItem->setCount($paymentItem->getCount() - 1);
                $playerInventory->setItem($paymentSlot, $newItem->getCount() > 0 ? $newItem : VanillaItems::AIR());
                
                // Apply the effects to the beacon tile
                if ($primaryEffectId >= 0) {
                    $beacon->setPrimaryEffect($primaryEffectId);
                }
                
                if ($secondaryEffectId >= 0) {
                    $beacon->setSecondaryEffect($secondaryEffectId);
                }
                
                // Update the beacon inventory display (cosmetic)
                if ($newItem->getCount() > 0) {
                    $displayItem = clone $newItem;
                    $displayItem->setCount(1);
                    $inventory->setItem(BeaconInventory::SLOT_FUEL, $displayItem);
                } else {
                    $inventory->setItem(BeaconInventory::SLOT_FUEL, VanillaItems::AIR());
                }
                
                // Send success message
                $player->sendMessage(TextFormat::GREEN . "Beacon effects applied successfully!");
                $success = true;
                
                // Debug logging
                if ($this->config->getNested('debug.log-interactions', false)) {
                    $this->plugin->getLogger()->info("Player {$player->getName()} paid beacon with " . $paymentItem->getName());
                }
                
            } catch (\Exception $e) {
                $this->plugin->getLogger()->error("Error processing beacon payment for {$player->getName()}: " . $e->getMessage());
                $player->sendMessage(TextFormat::RED . "An error occurred while processing payment.");
            }
        } else {
            // Send error message
            $player->sendMessage(TextFormat::RED . "You need a valid payment item in your inventory! Use Iron Ingot, Gold Ingot, Diamond, Emerald, or Netherite Ingot.");
        }
        
        // Send response to client
        BeaconStackRequestHandler::sendBeaconResponse($player, $inventory, $action, $requestId, $success);
    }
    
    /**
     * Handle inventory close event
     * 
     * @param InventoryCloseEvent $event
     */
    public function onInventoryClose(InventoryCloseEvent $event): void {
        $inventory = $event->getInventory();
        $player = $event->getPlayer();
        
        // Check if the closed inventory is a beacon inventory
        if ($inventory instanceof BeaconInventory) {
            $this->closeBeaconInventory($player);
        }
    }
    
    /**
     * Close a beacon inventory for a player
     * 
     * @param Player $player
     */
    public function closeBeaconInventory(Player $player): void {
        if (isset($this->openInventories[$player->getName()])) {
            unset($this->openInventories[$player->getName()]);
        }
    }
    
    /**
     * Validate if an effect is available for the given beacon level
     * 
     * @param int $effectId
     * @param int $level
     * @return bool
     */
    private function isValidEffectForLevel(int $effectId, int $level): bool {
        return match($level) {
            1 => in_array($effectId, [1, 3]), // Speed, Haste
            2 => in_array($effectId, [1, 3, 11]), // Speed, Haste, Resistance
            3 => in_array($effectId, [1, 3, 11, 8]), // Speed, Haste, Resistance, Jump Boost
            4 => in_array($effectId, [1, 3, 11, 8, 5, 10]), // All effects including Strength, Regeneration
            default => false
        };
    }
    
    /**
     * Validate if an effect is available as secondary effect
     * 
     * @param int $effectId
     * @return bool
     */
    private function isValidSecondaryEffect(int $effectId): bool {
        // Secondary effects are the same as primary but also include regeneration
        return in_array($effectId, [1, 3, 11, 8, 5, 10]);
    }
}