<?php

declare(strict_types=1);

namespace Renz\Beacon;

use pocketmine\block\tile\Beacon as BeaconTile;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Beacon;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use Renz\Beacon\libs\InvMenu\InvMenu;
use Renz\Beacon\libs\InvMenu\InvMenuHandler;
use Renz\Beacon\libs\InvMenu\type\InvMenuTypeIds;
use Renz\Beacon\libs\InvMenu\transaction\InvMenuTransaction;
use Renz\Beacon\libs\InvMenu\transaction\InvMenuTransactionResult;
use pocketmine\item\VanillaItems;
use Renz\Beacon\tasks\BeaconEffectTask;

class Main extends PluginBase implements Listener {
    
    private Config $config;

    public function onEnable(): void {
        // Save default configuration
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        
        // Register event listeners
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        // Register InvMenu handler for beacon inventories
        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }
        
        // Register beacon effect task with configurable interval
        $effectInterval = $this->config->get('effect-update-interval', 100); // Default 5 seconds
        $this->getScheduler()->scheduleRepeatingTask(
            new BeaconEffectTask($this->getServer(), $this->config),
            $effectInterval
        );
        
        $this->getLogger()->info(TextFormat::GREEN . "BeaconGUI plugin enabled successfully!");
    }
    
    /**
     * Handle player interaction with beacon blocks
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $block = $event->getBlock();
        $player = $event->getPlayer();
        
        // Check if the block is a beacon using instanceof (more reliable)
        if ($block instanceof Beacon) {
            $event->cancel(); // Cancel the default interaction
            
            $position = $block->getPosition();
            $tile = $position->getWorld()->getTile($position);
            
            if ($tile instanceof BeaconTile) {
                // Open beacon inventory using InvMenu
                $this->openBeaconGUI($player, $tile);
            } else {
                // Debug message if no beacon tile found
                if ($this->config->getNested('debug.log-interactions', false)) {
                    $this->getLogger()->debug("No beacon tile found at position: " . $position->__toString());
                }
            }
        }
    }
    
    /**
     * Handle beacon block breaking
     */
    public function onBlockBreak(BlockBreakEvent $event): void {
        $block = $event->getBlock();
        
        // Check if the block is a beacon using instanceof (more reliable)
        if ($block instanceof Beacon) {
            // Additional logic if needed when breaking a beacon  
            $this->getLogger()->debug("Beacon block broken at " . $block->getPosition()->__toString());
        }
    }
    
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender->hasPermission('beacon.admin')) {
            $sender->sendMessage(TextFormat::RED . "You don't have permission to use this command.");
            return true;
        }
        
        switch (strtolower($command->getName())) {
            case 'beacon':
                if (empty($args)) {
                    $sender->sendMessage(TextFormat::YELLOW . "Usage: /beacon <reload|status|info>");
                    return true;
                }
                
                switch (strtolower($args[0])) {
                    case 'reload':
                        $this->reloadConfig();
                        $this->config = $this->getConfig();
                        $sender->sendMessage(TextFormat::GREEN . "BeaconGUI configuration reloaded successfully!");
                        return true;
                        
                    case 'status':
                        $totalBeacons = 0;
                        foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
                            foreach ($world->getTiles() as $tile) {
                                if ($tile instanceof BeaconTile) {
                                    $totalBeacons++;
                                }
                            }
                        }
                        $sender->sendMessage(TextFormat::AQUA . "BeaconGUI Status:");
                        $sender->sendMessage(TextFormat::WHITE . "- Total Beacons: " . TextFormat::GREEN . $totalBeacons);
                        $sender->sendMessage(TextFormat::WHITE . "- Plugin Version: " . TextFormat::GREEN . $this->getDescription()->getVersion());
                        return true;
                        
                    case 'info':
                        $sender->sendMessage(TextFormat::GOLD . "=== BeaconGUI Plugin Info ===");
                        $sender->sendMessage(TextFormat::WHITE . "Version: " . TextFormat::GREEN . $this->getDescription()->getVersion());
                        $sender->sendMessage(TextFormat::WHITE . "Author: " . TextFormat::GREEN . implode(", ", $this->getDescription()->getAuthors()));
                        $sender->sendMessage(TextFormat::WHITE . "Description: " . TextFormat::GRAY . $this->getDescription()->getDescription());
                        return true;
                        
                    default:
                        $sender->sendMessage(TextFormat::RED . "Unknown subcommand. Use: reload, status, or info");
                        return true;
                }
                
            default:
                return false;
        }
    }
    
    /**
     * Open beacon GUI using InvMenu with proper beacon functionality
     */
    private function openBeaconGUI(\pocketmine\player\Player $player, BeaconTile $beacon): void {
        // Check permission
        if (!$player->hasPermission('beacon.use')) {
            $player->sendMessage(TextFormat::RED . "You don't have permission to use beacons.");
            return;
        }
        
        // Create beacon menu using new InvMenu TYPE_BEACON
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_BEACON);
        $menu->setName("§6Beacon");
        
        // Set up beacon payment slot with current fuel if any
        $inventory = $menu->getInventory();
        $currentFuel = $this->getBeaconFuel($beacon);
        if (!$currentFuel->isNull()) {
            $inventory->setItem(0, $currentFuel);
        }
        
        // Set transaction listener for beacon interactions
        $menu->setListener(function(InvMenuTransaction $transaction) use ($beacon): InvMenuTransactionResult {
            return $this->handleBeaconTransaction($transaction, $beacon);
        });
        
        // Set close listener
        $menu->setInventoryCloseListener(function(\pocketmine\player\Player $player, \pocketmine\inventory\Inventory $inventory) use ($beacon): void {
            $this->onBeaconClose($player, $inventory, $beacon);
        });
        
        // Send menu to player
        $menu->send($player);
        
        // Debug logging
        if ($this->config->getNested('debug.log-interactions', false)) {
            $pos = $beacon->getPosition();
            $this->getLogger()->info("Player {$player->getName()} opened beacon GUI at " . $pos->__toString());
        }
    }
    
    /**
     * Handle beacon inventory transactions
     */
    private function handleBeaconTransaction(InvMenuTransaction $transaction, BeaconTile $beacon): InvMenuTransactionResult {
        $player = $transaction->getPlayer();
        $itemOut = $transaction->getItemClicked();
        $itemIn = $transaction->getItemClickedWith();
        
        // Only allow valid beacon payment items
        if (!$itemIn->isNull() && !$this->isValidBeaconPayment($itemIn)) {
            $player->sendMessage(TextFormat::RED . "Invalid payment item! Use Iron Ingot, Gold Ingot, Diamond, Emerald, or Netherite Ingot.");
            return $transaction->discard();
        }
        
        // Allow the transaction and update beacon effects
        return $transaction->continue()->then(function(\pocketmine\player\Player $player) use ($beacon): void {
            $this->updateBeaconEffects($player, $beacon);
        });
    }
    
    /**
     * Handle beacon inventory close
     */
    private function onBeaconClose(\pocketmine\player\Player $player, \pocketmine\inventory\Inventory $inventory, BeaconTile $beacon): void {
        // Save beacon fuel state
        $fuelItem = $inventory->getItem(0);
        $this->setBeaconFuel($beacon, $fuelItem);
        
        // Debug logging
        if ($this->config->getNested('debug.log-interactions', false)) {
            $this->getLogger()->info("Player {$player->getName()} closed beacon GUI");
        }
    }
    
    /**
     * Check if item is valid beacon payment
     */
    private function isValidBeaconPayment(\pocketmine\item\Item $item): bool {
        $validItems = [
            VanillaItems::IRON_INGOT()->getTypeId(),
            VanillaItems::GOLD_INGOT()->getTypeId(),
            VanillaItems::DIAMOND()->getTypeId(),
            VanillaItems::EMERALD()->getTypeId(),
            VanillaItems::NETHERITE_INGOT()->getTypeId()
        ];
        
        return in_array($item->getTypeId(), $validItems, true);
    }
    
    /**
     * Get current beacon fuel item
     */
    private function getBeaconFuel(BeaconTile $beacon): \pocketmine\item\Item {
        // In a real implementation, this would read from beacon tile data
        // For now, return air as placeholder
        return VanillaItems::AIR();
    }
    
    /**
     * Set beacon fuel item
     */
    private function setBeaconFuel(BeaconTile $beacon, \pocketmine\item\Item $item): void {
        // In a real implementation, this would save to beacon tile data
        // For now, this is a placeholder
    }
    
    /**
     * Update beacon effects based on payment and pyramid
     */
    private function updateBeaconEffects(\pocketmine\player\Player $player, BeaconTile $beacon): void {
        // Calculate beacon level using existing utility
        $level = \Renz\Beacon\utils\BeaconPyramidCalculator::calculateLevel($beacon, $this->config);
        
        if ($level > 0) {
            $player->sendMessage(TextFormat::GREEN . "Beacon activated with level {$level} effects!");
            // Apply effects using existing logic from BeaconEffectTask
        } else {
            $player->sendMessage(TextFormat::YELLOW . "Beacon needs a valid pyramid structure underneath!");
        }
    }
    
    public function getBeaconConfig(): Config {
        return $this->config;
    }
    
    public function onDisable(): void {
        $this->getLogger()->info(TextFormat::GRAY . "BeaconGUI plugin disabled");
    }
}