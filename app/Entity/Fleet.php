<?php

namespace Xnova\Entity;

use Xnova\Exceptions\Exception;
use Xnova\Vars;

class Fleet extends Unit
{
	public function __construct ($elementId, $context = null)
	{
		if (Vars::getItemType($elementId) !== Vars::ITEM_TYPE_FLEET)
			throw new Exception('wrong entity type');

		parent::__construct($elementId, $context);
	}

	public function getTime (): int
	{
		$user = $this->getContext()->getUser();

		$time = parent::getTime();
		$time *= $user->bonusValue('time_fleet');

		return max(1, floor($time));
	}
}