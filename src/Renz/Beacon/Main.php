<?php

declare(strict_types=1);

namespace Renz\Beacon;

use pocketmine\block\tile\Beacon as BeaconTile;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use Renz\Beacon\inventory\BeaconInventory;
use Renz\Beacon\inventory\handler\BeaconInventoryHandler;
use Renz\Beacon\tasks\BeaconEffectTask;

class Main extends PluginBase implements Listener {
    
    private BeaconInventoryHandler $inventoryHandler;
    private Config $config;

    public function onEnable(): void {
        // Save default configuration
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        
        // Register event listeners
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        // Initialize inventory handler
        $this->inventoryHandler = new BeaconInventoryHandler($this);
        $this->inventoryHandler->register();
        
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
        
        // Check if the block is a beacon
        if ($block->isSameType(VanillaBlocks::BEACON())) {
            $event->cancel(); // Cancel the default interaction
            
            $position = $block->getPosition();
            $tile = $position->getWorld()->getTile($position);
            
            if ($tile instanceof BeaconTile) {
                // Open beacon inventory
                $this->inventoryHandler->openBeaconInventory($player, $tile);
            }
        }
    }
    
    /**
     * Handle beacon block breaking
     */
    public function onBlockBreak(BlockBreakEvent $event): void {
        $block = $event->getBlock();
        
        // Check if the block is a beacon
        if ($block->isSameType(VanillaBlocks::BEACON())) {
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
    
    public function getBeaconConfig(): Config {
        return $this->config;
    }
    
    public function onDisable(): void {
        $this->getLogger()->info(TextFormat::GRAY . "BeaconGUI plugin disabled");
    }
}