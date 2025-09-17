<?php

declare(strict_types=1);

namespace Renz\Beacon\libs\InvMenu\session\network\handler;

use Closure;
use Renz\Beacon\libs\InvMenu\session\network\NetworkStackLatencyEntry;

interface PlayerNetworkHandler{

	public function createNetworkStackLatencyEntry(Closure $then) : NetworkStackLatencyEntry;
}
