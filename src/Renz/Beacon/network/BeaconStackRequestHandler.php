<?php

declare(strict_types=1);

namespace Renz\Beacon\network;

use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\ItemStackResponsePacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackResponse;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseContainerInfo;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseSlotInfo;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\BeaconPaymentStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseStatus;
use pocketmine\player\Player;
use Renz\Beacon\inventory\BeaconInventory;

/**
 * Handles beacon-specific ItemStackRequest responses
 */
class BeaconStackRequestHandler {
    
    /**
     * Create and send a response for a beacon payment action
     * 
     * @param Player $player
     * @param BeaconInventory|null $inventory
     * @param BeaconPaymentStackRequestAction $action
     * @param int $requestId
     * @param bool $success
     */
    public static function sendBeaconResponse(
        Player $player, 
        ?BeaconInventory $inventory, 
        BeaconPaymentStackRequestAction $action, 
        int $requestId, 
        bool $success
    ): void {
        // Create response packet
        $responsePacket = new ItemStackResponsePacket();
        
        // Create response data
        $responseData = new ItemStackResponse(
            $requestId,
            $success ? ItemStackResponseStatus::OK : ItemStackResponseStatus::ERROR,
            []
        );
        
        // If successful and inventory exists, add container info for both beacon and player inventory
        if ($success && $inventory !== null) {
            // Get the network session
            $networkSession = $player->getNetworkSession();
            $playerInventory = $player->getInventory();
            
            // Get window ID for beacon
            $beaconWindowId = $networkSession->getInvManager()->getWindowId($inventory);
            
            // Add beacon container info if window exists
            if ($beaconWindowId !== null) {
                $paymentItem = $inventory->getItem(BeaconInventory::SLOT_FUEL);
                $beaconContainerInfo = new ItemStackResponseContainerInfo(
                    $beaconWindowId,
                    [
                        new ItemStackResponseSlotInfo(
                            BeaconInventory::SLOT_FUEL, // Use the constant for the payment slot
                            0, // Stack ID
                            $paymentItem->getCount(),
                            $paymentItem->getTypeId(),
                            $paymentItem->getMeta()
                        )
                    ]
                );
                $responseData->containerInfos[] = $beaconContainerInfo;
            }
            
            // Add player inventory container info to reflect the debited item
            $playerWindowId = $networkSession->getInvManager()->getWindowId($playerInventory);
            if ($playerWindowId !== null) {
                // Find the slot that was debited (simplified - in production should track the actual slot)
                for ($slot = 0; $slot < $playerInventory->getSize(); $slot++) {
                    $item = $playerInventory->getItem($slot);
                    if (!$item->isNull() && $inventory->isValidPayment($item)) {
                        $playerContainerInfo = new ItemStackResponseContainerInfo(
                            $playerWindowId,
                            [
                                new ItemStackResponseSlotInfo(
                                    $slot,
                                    0, // Stack ID
                                    $item->getCount(),
                                    $item->getTypeId(),
                                    $item->getMeta()
                                )
                            ]
                        );
                        $responseData->containerInfos[] = $playerContainerInfo;
                        break;
                    }
                }
            }
        }
        
        // Add response data to packet
        $responsePacket->responses[] = $responseData;
        
        // Send the packet
        $player->getNetworkSession()->sendDataPacket($responsePacket);
    }
}