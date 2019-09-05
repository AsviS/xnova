<?php

namespace Xnova\Missions;

/**
 * @author AlexPro
 * @copyright 2008 - 2019 XNova Game Group
 * Telegram: @alexprowars, Skype: alexprowars, Email: alexprowars@gmail.com
 */

use Illuminate\Support\Facades\Cache;
use Xnova\FleetEngine;
use Xnova\Galaxy;
use Xnova\User;
use Xnova\Models;

class CreateBase extends FleetEngine implements Mission
{
	public function TargetEvent()
	{
		/** @var User $owner */
		$owner = User::query()->find($this->_fleet->owner);

		// Определяем максимальное количество баз
		$maxBases = $owner->getTechLevel('fleet_base');

		$galaxy = new Galaxy();

		// Получение общего количества построенных баз
		$iPlanetCount = Models\Planets::query()->where('id_owner', $this->_fleet->owner)
			->where('planet_type', 5)->count();

		$TargetAdress = sprintf(__('fleet_engine.sys_adress_planet'), $this->_fleet->end_galaxy, $this->_fleet->end_system, $this->_fleet->end_planet);

		// Если в галактике пусто (планета не заселена)
		if ($galaxy->isPositionFree($this->_fleet->end_galaxy, $this->_fleet->end_system, $this->_fleet->end_planet))
		{
			// Если лимит баз исчерпан
			if ($iPlanetCount >= $maxBases)
			{
				$TheMessage = __('fleet_engine.sys_colo_arrival') . $TargetAdress . __('fleet_engine.sys_colo_maxcolo') . $maxBases . __('fleet_engine.sys_base_planet');

				User::sendMessage($this->_fleet->owner, 0, $this->_fleet->start_time, 0, __('fleet_engine.sys_base_mess_from'), $TheMessage);

				$this->ReturnFleet();
			}
			else
			{
				// Создание планеты-базы
				$NewOwnerPlanet = $galaxy->createPlanet($this->_fleet->end_galaxy, $this->_fleet->end_system, $this->_fleet->end_planet, $this->_fleet->owner, __('fleet_engine.sys_base_defaultname'), false, true);

				// Если планета-база создана
				if ($NewOwnerPlanet !== false)
				{
					$TheMessage = __('fleet_engine.sys_colo_arrival') . $TargetAdress . __('fleet_engine.sys_base_allisok');

					User::sendMessage($this->_fleet->owner, 0, $this->_fleet->start_time, 0, __('fleet_engine.sys_base_mess_from'), $TheMessage);

					$newFleet = [];

					$fleetData = $this->_fleet->getShips();

					foreach ($fleetData as $shipId => $shipArr)
					{
						if ($shipId == 216 && $shipArr['count'] > 0)
							$shipArr['count']--;

						$newFleet[] = $shipArr;
					}

					$this->_fleet->fleet_array = $newFleet;
					$this->_fleet->end_type = 5;

					$this->RestoreFleetToPlanet(false);
					$this->KillFleet();

					Cache::forget('app::planetlist_'.$this->_fleet->owner);
				}
				else
				{
					$this->ReturnFleet();

					$TheMessage = __('fleet_engine.sys_colo_arrival') . $TargetAdress . __('fleet_engine.sys_base_badpos');

					User::sendMessage($this->_fleet->owner, 0, $this->_fleet->start_time, 0, __('fleet_engine.sys_base_mess_from'), $TheMessage);
				}
			}
		}
		else
		{
			$this->ReturnFleet();

			$TheMessage = __('fleet_engine.sys_colo_arrival') . $TargetAdress . __('fleet_engine.sys_base_notfree');

			User::sendMessage($this->_fleet->owner, 0, $this->_fleet->end_time, 0, __('fleet_engine.sys_base_mess_from'), $TheMessage);
		}
	}

	public function EndStayEvent()
	{
		return;
	}

	public function ReturnEvent()
	{
		$this->RestoreFleetToPlanet();
		$this->KillFleet();
	}
}