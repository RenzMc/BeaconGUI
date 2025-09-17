<?php

declare(strict_types=1);

namespace Renz\Beacon\libs\InvMenu\type;

use Renz\Beacon\libs\InvMenu\InvMenu;
use Renz\Beacon\libs\InvMenu\type\graphic\InvMenuGraphic;
use pocketmine\inventory\Inventory;
use pocketmine\player\Player;

interface InvMenuType{

	public function createGraphic(InvMenu $menu, Player $player) : ?InvMenuGraphic;

	public function createInventory() : Inventory;
}
