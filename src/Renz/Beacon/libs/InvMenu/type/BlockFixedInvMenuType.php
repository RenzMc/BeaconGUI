<?php

declare(strict_types=1);

namespace Renz\Beacon\libs\InvMenu\type;

use Renz\Beacon\libs\InvMenu\inventory\InvMenuInventory;
use Renz\Beacon\libs\InvMenu\InvMenu;
use Renz\Beacon\libs\InvMenu\type\graphic\BlockInvMenuGraphic;
use Renz\Beacon\libs\InvMenu\type\graphic\InvMenuGraphic;
use Renz\Beacon\libs\InvMenu\type\graphic\network\InvMenuGraphicNetworkTranslator;
use Renz\Beacon\libs\InvMenu\type\util\InvMenuTypeHelper;
use pocketmine\block\Block;
use pocketmine\inventory\Inventory;
use pocketmine\player\Player;

final class BlockFixedInvMenuType implements FixedInvMenuType{

	public function __construct(
		readonly private Block $block,
		readonly private int $size,
		readonly private ?InvMenuGraphicNetworkTranslator $network_translator = null
	){}

	public function getSize() : int{
		return $this->size;
	}

	public function createGraphic(InvMenu $menu, Player $player) : ?InvMenuGraphic{
		$origin = $player->getPosition()->addVector(InvMenuTypeHelper::getBehindPositionOffset($player))->floor();
		if(!InvMenuTypeHelper::isValidYCoordinate($origin->y)){
			return null;
		}

		return new BlockInvMenuGraphic($this->block, $origin, $this->network_translator);
	}

	public function createInventory() : Inventory{
		return new InvMenuInventory($this->size);
	}
}
