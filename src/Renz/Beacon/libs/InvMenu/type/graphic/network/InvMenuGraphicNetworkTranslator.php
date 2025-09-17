<?php

declare(strict_types=1);

namespace Renz\Beacon\libs\InvMenu\type\graphic\network;

use Renz\Beacon\libs\InvMenu\session\InvMenuInfo;
use Renz\Beacon\libs\InvMenu\session\PlayerSession;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;

interface InvMenuGraphicNetworkTranslator{

	public function translate(PlayerSession $session, InvMenuInfo $current, ContainerOpenPacket $packet) : void;
}
