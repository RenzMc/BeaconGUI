<?php

declare(strict_types=1);

namespace Renz\Beacon\libs\InvMenu\session;

use Renz\Beacon\libs\InvMenu\InvMenu;
use Renz\Beacon\libs\InvMenu\type\graphic\InvMenuGraphic;

final class InvMenuInfo{

	public function __construct(
		readonly public InvMenu $menu,
		readonly public InvMenuGraphic $graphic,
		readonly public ?string $graphic_name
	){}
}
