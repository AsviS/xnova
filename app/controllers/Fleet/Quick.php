<?php
namespace App\Controllers\Fleet;

use App\Controllers\FleetController;
use App\Fleet;
use App\Lang;

class Quick
{
	public function show (FleetController $controller)
	{
		if ($controller->user->vacation > 0)
			die("Нет доступа!");

		Lang::includeLang('fleet');

		$maxfleet = $controller->db->fetchColumn("SELECT COUNT(fleet_owner) AS `actcnt` FROM game_fleets WHERE `fleet_owner` = '" . $controller->user->id . "';");

		$MaxFlottes = 1 + $controller->user->{$controller->game->resource[108]};
		if ($controller->user->rpg_admiral > time())
			$MaxFlottes += 2;

		if ($MaxFlottes <= $maxfleet)
			die('Все слоты флота заняты');

		$Mode 	= $controller->request->getQuery('mode', 'int', 0);
		$Galaxy = $controller->request->getQuery('g', 'int', 0);
		$System = $controller->request->getQuery('s', 'int', 0);
		$Planet = $controller->request->getQuery('p', 'int', 0);
		$TypePl = $controller->request->getQuery('t', 'int', 0);
		$num 	= $controller->request->getQuery('count', 'int', 0);

		if ($Galaxy > $controller->config->game->maxGalaxyInWorld || $Galaxy < 1)
			die('Ошибочная галактика!');
		if ($System > $controller->config->game->maxSystemInGalaxy || $System < 1)
			die('Ошибочная система!');
		if ($Planet > $controller->config->game->maxPlanetInSystem || $Planet < 1)
			die('Ошибочная планета!');
		if ($TypePl != 1 && $TypePl != 2 && $TypePl != 3 && $TypePl != 5)
			die('Ошибочный тип планеты!');

		if ($controller->planet->galaxy == $Galaxy && $controller->planet->system == $System && $controller->planet->planet == $Planet && $controller->planet->planet_type == $TypePl)
			$target = $controller->planet->toArray();
		else
		{
			$target = $controller->db->query("SELECT * FROM game_planets WHERE galaxy = " . $Galaxy . " AND system = " . $System . " AND planet = " . $Planet . " AND (planet_type = " . (($TypePl == 2) ? "1 OR planet_type = 5" : $TypePl) . ")")->fetch();

			if (!isset($target['id']))
				die('Цели не существует!');
		}

		$FleetArray = array();

		if ($Mode == 6 && ($TypePl == 1 || $TypePl == 3 || $TypePl == 5))
		{
			if ($num <= 0)
				die('Вы были забанены за читерство!');
			if ($controller->planet->spy_sonde == 0)
				die('Нет шпионских зондов ля отправки!');
			if ($target['id_owner'] == $controller->user->id)
				die('Невозможно выполнить задание!');

			$HeDBRec = $controller->db->query("SELECT id, onlinetime, vacation FROM game_users WHERE `id` = '" . $target['id_owner'] . "';")->fetch();

			$UserPoints  = $controller->db->query("SELECT total_points FROM game_statpoints WHERE `stat_type` = '1' AND `stat_code` = '1' AND `id_owner` = '" . $controller->user->id . "';")->fetch();
			$User2Points = $controller->db->query("SELECT total_points FROM game_statpoints WHERE `stat_type` = '1' AND `stat_code` = '1' AND `id_owner` = '" . $HeDBRec['id'] . "';")->fetch();

			$MyGameLevel = $UserPoints['total_points'];
			$HeGameLevel = $User2Points['total_points'];

			if (!$HeGameLevel)
				$HeGameLevel = 0;

			if ($HeDBRec['onlinetime'] < (time() - 60 * 60 * 24 * 7))
				$NoobNoActive = 1;
			else
				$NoobNoActive = 0;

			if ($controller->user->authlevel != 3)
			{
				if (isset($TargetPlanet['id_owner'])  AND $NoobNoActive == 0 AND $HeGameLevel < ($controller->config->game->get('noobprotectiontime') * 1000))
				{
					if ($MyGameLevel > ($HeGameLevel * $controller->config->game->get('noobprotectionmulti')))
						die('Игрок находится под защитой новичков!');
					if (($MyGameLevel * $controller->config->game->get('noobprotectionmulti')) < $HeGameLevel)
						die('Вы слишком слабы для нападения на этого игрока!');
				}
			}

			if ($HeDBRec['vacation'] > 0)
				die('Игрок в режиме отпуска!');

			if ($controller->planet->spy_sonde < $num)
				$num = $controller->planet->spy_sonde;

			$FleetArray[210] = $num;

			$FleetSpeed = min(Fleet::GetFleetMaxSpeed($FleetArray, 0, $controller->user));

		}
		elseif ($Mode == 8 && $TypePl == 2)
		{
			$DebrisSize = $target['debris_metal'] + $target['debris_crystal'];

			if ($DebrisSize == 0)
				die('Нет обломков для сбора!');
			if ($controller->planet->recycler == 0)
				die('Нет переработчиков для сбора обломков!');

			$RecyclerNeeded = 0;

			if ($controller->planet->recycler > 0 && $DebrisSize > 0)
			{
				$RecyclerNeeded = floor($DebrisSize / ($controller->game->CombatCaps[209]['capacity'] * (1 + $controller->user->fleet_209 * ($controller->game->CombatCaps[209]['power_consumption'] / 100)))) + 1;

				if ($RecyclerNeeded > $controller->planet->recycler)
					$RecyclerNeeded = $controller->planet->recycler;
			}

			if ($RecyclerNeeded > 0)
			{
				$FleetArray[209] = $RecyclerNeeded;

				$FleetSpeed = min(Fleet::GetFleetMaxSpeed($FleetArray, 0, $controller->user));
			}
			else
				die('Произошла какая-то непонятная ситуация');
		}
		else
			die('Такой миссии не существует!');

		if ($FleetSpeed > 0 && count($FleetArray) > 0)
		{
			$SpeedFactor = $controller->game->getSpeed('fleet');
			$distance = Fleet::GetTargetDistance($controller->planet->galaxy, $Galaxy, $controller->planet->system, $System, $controller->planet->planet, $Planet);
			$duration = Fleet::GetMissionDuration(10, $FleetSpeed, $distance, $SpeedFactor);

			$consumption = Fleet::GetFleetConsumption($FleetArray, $SpeedFactor, $duration, $distance, $controller->user);

			$ShipCount = 0;
			$ShipArray = '';
			$FleetSubQRY = '';
			$FleetStorage = 0;

			foreach ($FleetArray as $Ship => $Count)
			{
				$FleetSubQRY .= "`" . $controller->game->resource[$Ship] . "` = `" . $controller->game->resource[$Ship] . "` - " . $Count . " , ";
				$ShipArray .=  $Ship . "," . $Count . "!" . (isset($controller->user->{'fleet_' . $Ship}) ? $controller->user->{'fleet_' . $Ship} : 0) . ";";
				$ShipCount += $Count;

				if (isset($controller->user->{'fleet_' . $Ship}) && isset($controller->game->CombatCaps[$Ship]['power_consumption']) && $controller->game->CombatCaps[$Ship]['power_consumption'] > 0)
					$FleetStorage += round($controller->game->CombatCaps[$Ship]['capacity'] * (1 + $controller->user->{'fleet_' . $Ship} * ($controller->game->CombatCaps[$Ship]['power_consumption'] / 100))) * $Count;
				else
					$FleetStorage += $controller->game->CombatCaps[$Ship]['capacity'] * $Count;
			}

			if ($FleetStorage < $consumption)
				die('Не хватает места в трюме для топлива! (необходимо еще ' . ($consumption - $FleetStorage) . ')');
			if ($controller->planet->deuterium < $consumption)
				die('Не хватает топлива на полёт! (необходимо еще ' . ($consumption - $controller->planet->deuterium) . ')');

			if ($FleetSubQRY != '')
			{
				$QryInsertFleet = "INSERT INTO game_fleets SET ";
				$QryInsertFleet .= "`fleet_owner` = '" . $controller->user->id . "', ";
				$QryInsertFleet .= "`fleet_owner_name` = '" . $controller->planet->name . "', ";
				$QryInsertFleet .= "`fleet_mission` = '" . $Mode . "', ";
				$QryInsertFleet .= "`fleet_array` = '" . $ShipArray . "', ";
				$QryInsertFleet .= "`fleet_start_time` = '" . ($duration + time()) . "', ";
				$QryInsertFleet .= "`fleet_start_galaxy` = '" . $controller->planet->galaxy . "', ";
				$QryInsertFleet .= "`fleet_start_system` = '" . $controller->planet->system . "', ";
				$QryInsertFleet .= "`fleet_start_planet` = '" . $controller->planet->planet . "', ";
				$QryInsertFleet .= "`fleet_start_type` = '" . $controller->planet->planet_type . "', ";
				$QryInsertFleet .= "`fleet_end_time` = '" . (($duration * 2) + time()) . "', ";
				$QryInsertFleet .= "`fleet_end_galaxy` = '" . $Galaxy . "', ";
				$QryInsertFleet .= "`fleet_end_system` = '" . $System . "', ";
				$QryInsertFleet .= "`fleet_end_planet` = '" . $Planet . "', ";
				$QryInsertFleet .= "`fleet_end_type` = '" . $TypePl . "', ";

				if ($Mode == 6 && isset($HeDBRec['id']))
				{
					$QryInsertFleet .= "`fleet_target_owner` = '" . $HeDBRec['id'] . "', ";
					$QryInsertFleet .= "`fleet_target_owner_name` = '" . $target['name'] . "', ";
				}

				$QryInsertFleet .= "`start_time` = '" . time() . "', `fleet_time` = '" . ($duration + time()) . "';";
				$controller->db->query($QryInsertFleet);

				$controller->db->query("UPDATE game_planets SET " . $FleetSubQRY . " deuterium = deuterium - " . $consumption . " WHERE `id` = '" . $controller->planet->id . "'");

				$tutorial = $controller->db->query("SELECT id, quest_id FROM game_users_quests WHERE user_id = ".$controller->user->getId()." AND finish = '0' AND stage = 0")->fetch();

				if (isset($tutorial['id']))
				{
					Lang::includeLang('tutorial');

					$quest = _getText('tutorial', $tutorial['quest_id']);

					foreach ($quest['TASK'] AS $taskKey => $taskVal)
					{
						if ($taskKey == 'FLEET_MISSION' && $taskVal == $Mode)
						{
							$controller->db->query("UPDATE game_users_quests SET stage = 1 WHERE id = " . $tutorial['id'] . ";");
						}
					}
				}

				die("Флот отправлен на координаты [" . $Galaxy . ":" . $System . ":" . $Planet . "] с миссией " . _getText('type_mission', $Mode) . " и прибудет к цели в " . $controller->game->datezone("H:i:s", ($duration + time())) . "");
			}
		}
	}
}

?>