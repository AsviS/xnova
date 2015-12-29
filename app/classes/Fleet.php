<?php
namespace App;

use App\Models\User;
use Phalcon\Di;
use Phalcon\DiInterface;

class Fleet extends Building
{
	static function GetTargetDistance ($OrigGalaxy, $DestGalaxy, $OrigSystem, $DestSystem, $OrigPlanet, $DestPlanet)
	{
		if (($OrigGalaxy - $DestGalaxy) != 0)
			return abs($OrigGalaxy - $DestGalaxy) * 20000;

		if (($OrigSystem - $DestSystem) != 0)
			return abs($OrigSystem - $DestSystem) * 95 + 2700;

		if (($OrigPlanet - $DestPlanet) != 0)
			return abs($OrigPlanet - $DestPlanet) * 5 + 1000;

		return 5;
	}

	/**
	 * @param int $fleetSpeedFactor скорость полёта, от 1 до 10
	 * @param int $maxFleetSpeed
	 * @param int $distance
	 * @param float $gameFleetSpeed множитель скорости полётов
	 * @return float
	 */
	static function GetMissionDuration ($fleetSpeedFactor, $maxFleetSpeed, $distance, $gameFleetSpeed)
	{
		return round(((35000 / $fleetSpeedFactor) * sqrt($distance * 10 / $maxFleetSpeed) + 10) / $gameFleetSpeed);
	}

	/**
	 * @param  $FleetArray
	 * @param  $Fleet
	 * @param  $user user
	 * @return array|int
	 */
	static function GetFleetMaxSpeed ($FleetArray, $Fleet, User $user)
	{
		$game = $user->getDI()->getShared('game');

		$speedalls = array();

		if ($Fleet != 0)
		{
			$FleetArray[$Fleet] = 1;
		}

		foreach ($FleetArray as $Ship => $Count)
		{
			switch ($game->CombatCaps[$Ship]['type_engine'])
			{
				case 1:
					$speedalls[$Ship] = $game->CombatCaps[$Ship]['speed'] * (1 + ($user->combustion_tech * 0.1));
					break;
				case 2:
					$speedalls[$Ship] = $game->CombatCaps[$Ship]['speed'] * (1 + ($user->impulse_motor_tech * 0.2));
					break;
				case 3:
					$speedalls[$Ship] = $game->CombatCaps[$Ship]['speed'] * (1 + ($user->hyperspace_motor_tech * 0.3));
					break;
				default:
					$speedalls[$Ship] = $game->CombatCaps[$Ship]['speed'];
			}

			if ($user->bonusValue('fleet_speed') != 1)
				$speedalls[$Ship] = round($speedalls[$Ship] * $user->bonusValue('fleet_speed'));
		}

		if ($Fleet != 0)
			$speedalls = $speedalls[$Fleet];

		return $speedalls;
	}

	static function SetShipsEngine (User $user)
	{
		$game = $user->getDI()->getShared('game');

		foreach ($game->reslist['fleet'] as $Ship)
		{
			if (isset($game->CombatCaps[$Ship]) && isset($game->CombatCaps[$Ship]['engine_up']))
			{
				if ($user->{$game->resource[$game->CombatCaps[$Ship]['engine_up']['tech']]} >= $game->CombatCaps[$Ship]['engine_up']['lvl'])
				{
					$game->CombatCaps[$Ship]['type_engine']++;
					$game->CombatCaps[$Ship]['speed'] = $game->CombatCaps[$Ship]['engine_up']['speed'];

					unset($game->CombatCaps[$Ship]['engine_up']);
				}
			}
		}
	}

	/**
	 * @param  $Ship
	 * @param  $user user
	 * @return float
	 */
	static function GetShipConsumption ($Ship, User $user)
	{
		$game = $user->getDI()->getShared('game');

		return ceil($game->CombatCaps[$Ship]['consumption'] * $user->bonusValue('fleet_fuel'));
	}

	static function GetFleetConsumption ($FleetArray, $gameFleetSpeed, $MissionDuration, $MissionDistance, $Player)
	{
		$consumption = 0;

		if ($MissionDuration <= 1)
			$MissionDuration = 2;

		foreach ($FleetArray as $Ship => $Count)
		{
			if ($Ship > 0)
			{
				$spd = 35000 / ($MissionDuration * $gameFleetSpeed - 10) * sqrt($MissionDistance * 10 / self::GetFleetMaxSpeed("", $Ship, $Player));

				$consumption += (self::GetShipConsumption($Ship, $Player) * $Count) * $MissionDistance / 35000 * (($spd / 10) + 1) * (($spd / 10) + 1);
			}
		}

		$consumption = round($consumption) + 1;

		return $consumption;
	}

	static function GetFleetStay ($FleetArray, DiInterface $di)
	{
		$game = $di->getShared('game');

		$stay = 0;

		foreach ($FleetArray as $Ship => $Count)
		{
			if ($Ship > 0)
			{
				$stay += $game->CombatCaps[$Ship]['stay'] * $Count;
			}
		}

		return $stay;
	}

	static function unserializeFleet ($fleetAmount)
	{
		$fleetTyps = explode(';', $fleetAmount);

		$fleetAmount = array();

		foreach ($fleetTyps as $fleetTyp)
		{
			$temp = explode(',', $fleetTyp);

			if (empty($temp[0]))
				continue;

			if (!isset($fleetAmount[$temp[0]]))
			{
				$fleetAmount[$temp[0]] = array('cnt' => 0, 'lvl' => 0);
			}

			$lvl = explode("!", $temp[1]);

			$fleetAmount[$temp[0]]['cnt'] += $lvl[0];
			$fleetAmount[$temp[0]]['lvl']  = $lvl[1];
		}

		return $fleetAmount;
	}

	static function CreateFleetPopupedFleetLink ($FleetRow, $Texte, $FleetType, User $user)
	{
		$FleetRec = explode(";", $FleetRow['fleet_array']);

		$FleetPopup = "<table width=200>";
		$r = 'javascript:;';
		$Total = 0;

		if ($FleetRow['fleet_owner'] != $user->id && $user->spy_tech < 2)
		{
			$FleetPopup .= "<tr><td width=100% align=center><font color=white>Нет информации<font></td></tr>";
		}
		elseif ($FleetRow['fleet_owner'] != $user->id && $user->spy_tech < 4)
		{
			foreach ($FleetRec as $Group)
			{
				if ($Group != '')
				{
					$Ship = explode(",", $Group);
					$Count = explode("!", $Ship[1]);
					$Total = $Total + $Count[0];
				}
			}
			$FleetPopup .= "<tr><td width=50% align=left><font color=white>Численность:<font></td><td width=50% align=right><font color=white>" . Helpers::pretty_number($Total) . "<font></td></tr>";
		}
		elseif ($FleetRow['fleet_owner'] != $user->id && $user->spy_tech < 8)
		{
			foreach ($FleetRec as $Group)
			{
				if ($Group != '')
				{
					$Ship = explode(",", $Group);
					$Count = explode("!", $Ship[1]);
					$Total = $Total + $Count[0];
					$FleetPopup .= "<tr><td width=100% align=center colspan=2><font color=white>" . _getText('tech', $Ship[0]) . "<font></td></tr>";
				}
			}
			$FleetPopup .= "<tr><td width=50% align=left><font color=white>Численность:<font></td><td width=50% align=right><font color=white>" . Helpers::pretty_number($Total) . "<font></td></tr>";
		}
		else
		{
			if ($FleetRow['fleet_target_owner'] == $user->id && $FleetRow['fleet_mission'] == 1)
				$r = '/sim/?r=';

			foreach ($FleetRec as $Group)
			{
				if ($Group != '')
				{
					$Ship = explode(",", $Group);
					$Count = explode("!", $Ship[1]);
					$FleetPopup .= "<tr><td width=75% align=left><font color=white>" . _getText('tech', $Ship[0]) . ":<font></td><td width=25% align=right><font color=white>" . Helpers::pretty_number($Count[0]) . "<font></td></tr>";

					if ($r != 'javascript:;')
						$r .= $Group . ';';
				}
			}
		}

		$FleetPopup .= "</table>";
		$FleetPopup .= "' class=\"" . $FleetType . "\">" . $Texte . "</a>";

		$FleetPopup = "<a href='" . $r . "' class=\"tooltip\" data-tooltip-content='" . $FleetPopup;

		return $FleetPopup;

	}

	static function CreateFleetPopupedMissionLink ($FleetRow, $Texte, $FleetType)
	{
		$FleetTotalC = $FleetRow['fleet_resource_metal'] + $FleetRow['fleet_resource_crystal'] + $FleetRow['fleet_resource_deuterium'];

		if ($FleetTotalC != 0)
		{
			$FRessource = "<table width=200>";
			$FRessource .= "<tr><td width=50% align=left><font color=white>" . _getText('Metal') . "<font></td><td width=50% align=right><font color=white>" . Helpers::pretty_number($FleetRow['fleet_resource_metal']) . "<font></td></tr>";
			$FRessource .= "<tr><td width=50% align=left><font color=white>" . _getText('Crystal') . "<font></td><td width=50% align=right><font color=white>" . Helpers::pretty_number($FleetRow['fleet_resource_crystal']) . "<font></td></tr>";
			$FRessource .= "<tr><td width=50% align=left><font color=white>" . _getText('Deuterium') . "<font></td><td width=50% align=right><font color=white>" . Helpers::pretty_number($FleetRow['fleet_resource_deuterium']) . "<font></td></tr>";
			$FRessource .= "</table>";
		}
		else
		{
			$FRessource = "";
		}

		if ($FRessource <> "")
		{
			$MissionPopup = "<a href='javascript:;' data-tooltip-content='" . $FRessource . "' class=\"tooltip " . $FleetType . "\">" . $Texte . "</a>";
		}
		else
		{
			$MissionPopup = $Texte . "";
		}

		return $MissionPopup;
	}

	static function getFleetMissions ($fleetArray, $target = array(1, 1, 1, 1), $isYouPlanet = false, $isActivePlanet = false, $isAcs = false)
	{
		$result = array();

		if ($target[2] == 16)
		{
			if (!(count($fleetArray) == 1 && isset($fleetArray[210])))
				$result[15] = _getText('type_mission', 15);
		}
		else
		{
			if ($target[3] == 2 && isset($fleetArray[209]))
				$result[8] = _getText('type_mission', 8); // Переработка
			elseif ($target[3] == 1 || $target[3] == 3 || $target[3] == 5)
			{
				if (isset($fleetArray[216]) && !$isActivePlanet && $target[3] == 1)
					$result[10] = _getText('type_mission', 10); // Создать базу

				if (isset($fleetArray[210]) && !$isYouPlanet)
					$result[6] = _getText('type_mission', 6); // Шпионаж

				if (isset($fleetArray[208]) && !$isActivePlanet)
					$result[7] = _getText('type_mission', 7); // Колонизировать

				if (!$isYouPlanet && $isActivePlanet && !isset($fleetArray[208]) && !isset($fleetArray[209]) && !isset($fleetArray[216]))
					$result[1] = _getText('type_mission', 1); // Атаковать

				if ($isActivePlanet && !$isYouPlanet && !(count($fleetArray) == 1 && isset($fleetArray[210])))
					$result[5] = _getText('type_mission', 5); // Удерживать

				if (isset($fleetArray[202]) || isset($fleetArray[203]))
					$result[3] = _getText('type_mission', 3); // Транспорт

				if ($isYouPlanet)
					$result[4] = _getText('type_mission', 4); // Оставить

				if ($isAcs > 0 && $isActivePlanet)
					$result[2] = _getText('type_mission', 2); // Объединить

				if ($target[3] == 3 && isset($fleetArray[214]) && !$isYouPlanet && $isActivePlanet)
					$result[9] = _getText('type_mission', 9);
			}
		}

		return $result;
	}
}

?>