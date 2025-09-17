<?php

declare(strict_types=1);

namespace Renz\Beacon\libs\InvMenu\type\util\builder;

use Renz\Beacon\libs\InvMenu\type\InvMenuType;

interface InvMenuTypeBuilder{

	public function build() : InvMenuType;
}
