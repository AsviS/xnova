<?php

namespace Xnova\Missions;

/**
 * @author AlexPro
 * @copyright 2008 - 2018 XNova Game Group
 * Telegram: @alexprowars, Skype: alexprowars, Email: alexprowars@gmail.com
 */

use Phalcon\Tag;
use Xnova\Battle\Core\Battle;
use Xnova\Battle\Core\Round;
use Xnova\Battle\LangImplementation;
use Xnova\Battle\Models\Defense;
use Xnova\Battle\Models\HomeFleet;
use Xnova\Battle\Models\Player;
use Xnova\Battle\Models\PlayerGroup;
use Xnova\Battle\Models\Ship;
use Xnova\Battle\Models\ShipType;
use Xnova\Battle\Models\Fleet;
use Xnova\Battle\Utils\LangManager;
use Xnova\FleetEngine;
use Xnova\Format;
use Xnova\Galaxy;
use Xnova\Models\Planet;
use Xnova\Models;
use Xnova\Queue;
use Xnova\User;
use Xnova\Models\User as UserModel;
use Xnova\Models\Fleet as FleetModel;
use Xnova\Vars;

class MissionCaseAttack extends FleetEngine implements Mission
{
	public $usersTech = [];
	public $usersInfo = [];

	public function TargetEvent()
	{
		$target = Planet::findByCoords($this->_fleet->end_galaxy, $this->_fleet->end_system, $this->_fleet->end_planet, $this->_fleet->end_type);

		if (!isset($target->id) || !$target->id_owner || $target->destruyed > 0)
		{
			$this->ReturnFleet();

			return false;
		}

		$owner = UserModel::findFirst((int) $this->_fleet->owner);

		if (!$owner)
		{
			$this->ReturnFleet();

			return false;
		}

		$targetUser = UserModel::findFirst((int) $target->id_owner);

		if (!$targetUser)
		{
			$this->ReturnFleet();

			return false;
		}

		$target->assignUser($targetUser);
		$target->resourceUpdate($this->_fleet->start_time);

		$queueManager = new Queue($targetUser, $target);
		$queueManager->checkUnitQueue();

		LangManager::getInstance()->setImplementation(new LangImplementation());

		$attackers = new PlayerGroup();
		$defenders = new PlayerGroup();

		$this->getGroupFleet($this->_fleet, $attackers);

		if ($this->_fleet->group_id != 0)
		{
			$fleets = FleetModel::find(['id != ?0 AND group_id = ?1', 'bind' => [$this->_fleet->id, $this->_fleet->group_id]]);

			foreach ($fleets as $fleet)
			{
				$this->getGroupFleet($fleet, $attackers);
			}

			unset($fleets);
		}

		$def = FleetModel::find(['end_galaxy = :galaxy: AND end_system = :system: AND end_type = :type: AND end_planet = :planet: AND mess = 3', 'bind' => ['galaxy' => $this->_fleet->end_galaxy, 'system' => $this->_fleet->end_system, 'planet' => $this->_fleet->end_planet, 'type' => $this->_fleet->end_type]]);

		foreach ($def as $fleet)
		{
			$this->getGroupFleet($fleet, $defenders);
		}

		unset($def);

		$res = [];

		$units = Vars::getItemsByType([Vars::ITEM_TYPE_FLEET, Vars::ITEM_TYPE_DEFENSE]);

		foreach ($units as $i)
		{
			if ($target->getUnitCount($i) > 0)
			{
				$res[$i] = $target->getUnitCount($i);

				$l = $i > 400 ? ($i - 50) : ($i + 100);

				if ($targetUser->getTechLevel($l) > 0)
					$res[$l] = $targetUser->getTechLevel($l);
			}
		}

		foreach (Vars::getItemsByType(Vars::ITEM_TYPE_TECH) AS $techId)
		{
			$level = $targetUser->getTechLevel($techId);

			if ($targetUser->rpg_komandir > time() && in_array(Vars::getName($techId), ['military_tech', 'defence_tech', 'shield_tech']))
				$level += 2;

			if ($level > 0)
				$res[$techId] = $level;
		}

		$this->usersTech[$targetUser->id] = $res;

		$homeFleet = new HomeFleet(0);

		foreach ($units as $i)
		{
			if ($target->getUnitCount($i) > 0)
			{
				$shipType = $this->getShipType($i, $target->getUnitCount($i), $res);

				if ($targetUser->rpg_ingenieur && $shipType->getType() == 'Ship')
					$shipType->setRepairProb(0.8);

				$homeFleet->addShipType($shipType);
			}
		}

		if (!$defenders->existPlayer($targetUser->id))
		{
			$player = new Player($targetUser->id, [$homeFleet]);
			$player->setTech(0, 0, 0);
			$player->setName($targetUser->username);
			$defenders->addPlayer($player);

			if (!isset($this->usersInfo[$targetUser->id]))
				$this->usersInfo[$targetUser->id] = [];

			$this->usersInfo[$targetUser->id][0] = [
				'galaxy' => $target->galaxy,
				'system' => $target->system,
				'planet' => $target->planet
			];
		}
		else
			$defenders->getPlayer($targetUser->id)->addDefense($homeFleet);

		if (!$this->_fleet->raunds)
			$this->_fleet->raunds = 6;

		$engine = new Battle($attackers, $defenders, $this->_fleet->raunds);
		$report = $engine->getReport();
		$result = ['version' => 2, 'time' => time(), 'rw' => []];

		$attackUsers 	= $this->convertPlayerGroupToArray($report->getResultAttackersFleetOnRound('START'));
		$defenseUsers 	= $this->convertPlayerGroupToArray($report->getResultDefendersFleetOnRound('START'));

		for ( $_i = 0; $_i <= $report->getLastRoundNumber(); $_i++)
		{
			$result['rw'][] = $this->convertRoundToArray($report->getRound($_i));
		}

		$result['won'] = 0;

		if ($report->attackerHasWin())
			$result['won'] = 1;
		if ($report->defenderHasWin())
			$result['won'] = 2;
		if ($report->isAdraw())
			$result['won'] = 0;

		$result['lost'] = ['att' => $report->getTotalAttackersLostUnits(), 'def' => $report->getTotalDefendersLostUnits()];

		$debris = $report->getDebris();

		$result['debree']['att'] = $debris;
		$result['debree']['def'] = [0, 0];

		$attackFleets 	= $this->getResultFleetArray($report->getPresentationAttackersFleetOnRound('START'), $report->getAfterBattleAttackers());
		$defenseFleets 	= $this->getResultFleetArray($report->getPresentationDefendersFleetOnRound('START'), $report->getAfterBattleDefenders());

		$repairFleets = [];

		foreach ($report->getDefendersRepaired() as $_player)
		{
			foreach ($_player as $_idFleet => $_fleet)
			{
				/**
				 * @var ShipType $_ship
				 */
				foreach($_fleet as $_shipID => $_ship)
				{
					$repairFleets[$_idFleet][$_shipID] = $_ship->getCount();
				}
			}
		}

		$fleetToUser = [];

		foreach ($report->getPresentationAttackersFleetOnRound('START') as $idPlayer => $player)
		{
			/**
			 * @var $player Player
			 */
			foreach ($player->getIterator() as $idFleet => $fleet)
			{
				$fleetToUser[$idFleet] = $idPlayer;
			}
		}

		$steal = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];

		if ($result['won'] == 1)
		{
			$max_resources = 0;
			$max_fleet_res = [];

			foreach ($attackFleets AS $fleet => $arr)
			{
				$max_fleet_res[$fleet] = 0;

				foreach ($arr as $Element => $amount)
				{
					if ($Element == 210)
						continue;

					$capacity = $this->registry->CombatCaps[$Element]['capacity'] * $amount;

					$max_resources += $capacity;
					$max_fleet_res[$fleet] += $capacity;
				}
			}

			$res_correction = $max_resources;
			$res_procent = [];

			if ($max_resources > 0)
			{
				foreach ($max_fleet_res AS $id => $res)
				{
					$res_procent[$id] = $max_fleet_res[$id] / $res_correction;
				}
			}

			$steal = $this->getSteal($target, $max_resources);
		}

		$totalDebree = $result['debree']['def'][0] + $result['debree']['def'][1] + $result['debree']['att'][0] + $result['debree']['att'][1];

		if ($totalDebree > 0)
		{
			$this->db->updateAsDict('game_planets',
			[
				'+debris_metal' 	=> ($result['debree']['att'][0] + $result['debree']['def'][0]),
				'+debris_crystal' 	=> ($result['debree']['att'][1] + $result['debree']['def'][1])
			],
			"galaxy = ".$target->galaxy." AND system = ".$target->system." AND planet = ".$target->planet." AND planet_type != 3");
		}
		
		foreach ($attackFleets as $fleetID => $attacker)
		{
			$fleetArray = [];

			foreach ($attacker as $element => $amount)
			{
				if (!is_numeric($element) || !$amount)
					continue;

				$fleetArray[] = [
					'id' => (int) $element,
					'count' => (int) $amount
				];
			}

			if (!count($fleetArray))
				$this->KillFleet($fleetID);
			else
			{
				$update = [
					'fleet_array' 	=> json_encode($fleetArray),
					'@update_time' 	=> 'end_time',
					'mess'			=> 1,
					'group_id'		=> 0,
					'won'			=> $result['won']
				];

				if ($result['won'] == 1 && ($steal['metal'] > 0 || $steal['crystal'] > 0 || $steal['deuterium'] > 0))
				{
					if (isset($res_procent) && isset($res_procent[$fleetID]))
					{
						$update['+resource_metal'] 		= round($res_procent[$fleetID] * $steal['metal']);
						$update['+resource_crystal'] 	= round($res_procent[$fleetID] * $steal['crystal']);
						$update['+resource_deuterium'] 	= round($res_procent[$fleetID] * $steal['deuterium']);
					}
				}

				$this->db->updateAsDict($this->_fleet->getSource(), $update, "id = ".$fleetID);
			}
		}

		foreach ($defenseFleets as $fleetID => $defender)
		{
			if ($fleetID != 0)
			{
				$fleetArray = [];

				foreach ($defender as $element => $amount)
				{
					if (!is_numeric($element) || !$amount)
						continue;

					$fleetArray[] = [
						'id' => (int) $element,
						'count' => (int) $amount
					];
				}

				if (!count($fleetArray))
					$this->KillFleet($fleetID);
				else
				{
					$this->db->updateAsDict([
						'fleet_array' 	=> json_encode($fleetArray),
						'@update_time' 	=> 'end_time'
					],
					"id = ".$fleetID);
				}
			}
			else
			{
				if ($steal['metal'] > 0 || $steal['crystal'] > 0 || $steal['deuterium'] > 0)
				{
					$target->metal -= $steal['metal'];
					$target->crystal -= $steal['crystal'];
					$target->deuterium -= $steal['deuterium'];
				}

				foreach ($units as $i)
				{
					if (isset($defender[$i]) && $target->getUnitCount($i) > 0)
						$target->setUnit($i, $defender[$i]);
				}

				$target->update();
			}
		}
		
		$moonChance = $report->getMoonProb(($targetUser->rpg_admiral > time() ? 10 : 0));

		if ($target->planet_type != 1)
			$moonChance = 0;

		$userChance = mt_rand(1, 100);

		if ($this->_fleet->end_type == 5)
			$userChance = 0;

		if ($target->parent_planet == 0 && $userChance && $userChance <= $moonChance)
		{
			$galaxy = new Galaxy();

			$TargetPlanetName = $galaxy->createMoon($this->_fleet->end_galaxy, $this->_fleet->end_system, $this->_fleet->end_planet, $target->id_owner, $moonChance);

			if ($TargetPlanetName)
				$GottenMoon = sprintf(_getText('sys_moonbuilt'), $this->_fleet->end_galaxy, $this->_fleet->end_system, $this->_fleet->end_planet);
			else
				$GottenMoon = 'Предпринята попытка образования луны, но данные координаты уже заняты другой луной';
		}
		else
			$GottenMoon = '';

		// Очки военного опыта
		$warPoints 		= round($totalDebree / 25000);
		$AddWarPoints 	= ($result['won'] != 2) ? $warPoints : 0;
		// Сборка массива ID участников боя
		$FleetsUsers = [];

		$tmp = [];

		foreach ($attackUsers AS $info)
		{
			if (!in_array($info['tech']['id'], $tmp))
				$tmp[] = $info['tech']['id'];
		}

		$realAttackersUsers = count($tmp);
		unset($tmp);

		foreach ($attackUsers AS $info)
		{
			if (!in_array($info['tech']['id'], $FleetsUsers))
			{
				$FleetsUsers[] = (int) $info['tech']['id'];

				if ($this->_fleet->mission != 6)
				{
					$update = ['+raids' => 1];

					if ($result['won'] == 1)
						$update['+raids_win'] = 1;
					elseif ($result['won'] == 2)
						$update['+raids_lose'] = 1;

					if ($AddWarPoints > 0)
						$update['+xpraid'] = ceil($AddWarPoints / $realAttackersUsers);

					$this->db->updateAsDict('game_users', $update, "id = ".$info['tech']['id']);
				}
			}
		}
		foreach ($defenseUsers AS $info)
		{
			if (!in_array($info['tech']['id'], $FleetsUsers))
			{
				$FleetsUsers[] = (int) $info['tech']['id'];

				if ($this->_fleet->mission != 6)
				{
					$update = ['+raids' => 1];

					if ($result['won'] == 2)
						$update['+raids_win'] = 1;
					elseif ($result['won'] == 1)
						$update['+raids_lose'] = 1;

					$this->db->updateAsDict('game_users', $update, "id = ".$info['tech']['id']);
				}
			}
		}

		$raport = json_encode([$result, $attackUsers, $defenseUsers, $steal, $moonChance, $GottenMoon, $repairFleets]);
		// Уничтожен в первой волне
		$no_contact = (count($result['rw']) <= 2 && $result['won'] == 2) ? 1 : 0;

		$this->db->insertAsDict('game_rw',
		[
			'time' 			=> time(),
			'id_users' 		=> json_encode($FleetsUsers),
			'no_contact' 	=> $no_contact,
			'raport' 		=> $raport,
		]);
		// Ключи авторизации доклада
		$ids = $this->db->lastInsertId();

		if ($this->_fleet->group_id != 0)
		{
			$this->db->delete('game_aks', 'id = ?', [$this->_fleet->group_id]);
			$this->db->delete('game_aks_user', 'aks_id = ?', [$this->_fleet->group_id]);
		}

		$lost = $result['lost']['att'] + $result['lost']['def'];

		if ($lost >= $this->config->game->get('hallPoints', 1000000))
		{
			$sab = 0;

			$UserList = [];

			foreach ($attackUsers AS $info)
			{
				if (!in_array($info['username'], $UserList))
					$UserList[] = $info['username'];
			}

			if (count($UserList) > 1)
				$sab = 1;

			$title_1 = implode(',', $UserList);

			$UserList = [];

			foreach ($defenseUsers AS $info)
			{
				if (!in_array($info['username'], $UserList))
					$UserList[] = $info['username'];
			}

			if (count($UserList) > 1)
				$sab = 1;

			$title_2 = implode(',', $UserList);

			$title = '' . $title_1 . ' vs ' . $title_2 . ' (П: ' . Format::number($lost) . ')';

			$battleLog = new Models\BattleLog();

			$battleLog->user_id = 0;
			$battleLog->title = $title;
			$battleLog->log = $raport;

			if ($battleLog->create())
			{
				$this->db->insertAsDict('game_hall', [
					'title' 	=> $title,
					'debris' 	=> floor($lost / 1000),
					'time' 		=> time(),
					'won' 		=> $result['won'],
					'sab' 		=> $sab,
					'log' 		=> $battleLog->id
				]);
			}
		}

		$raport = "<center>";
		$raport .= Tag::renderAttributes('<a', [
			'href' => '/rw/'.$ids.'/'.md5($this->config->application->encryptKey.$ids).'/',
			'target' => $this->config->view->get('openRaportInNewWindow', 0) == 1 ? '_blank' : ''
		]);

		$raport .= "><font color=\"#COLOR#\">"._getText('sys_mess_attack_report') . " [" . $this->_fleet->end_galaxy . ":" . $this->_fleet->end_system . ":" . $this->_fleet->end_planet . "]</font></a>";

		$raport2  = '<br><br><font color=\'red\'>' . _getText('sys_perte_attaquant') . ': ' . Format::number($result['lost']['att']) . '</font><font color=\'green\'>   ' . _getText('sys_perte_defenseur') . ': ' . Format::number($result['lost']['def']) . '</font><br>';
		$raport2 .= _getText('sys_gain') . ' м: <font color=\'#adaead\'>' . Format::number($steal['metal']) . '</font>, к: <font color=\'#ef51ef\'>' . Format::number($steal['crystal']) . '</font>, д: <font color=\'#f77542\'>' . Format::number($steal['deuterium']) . '</font><br>';
		$raport2 .= _getText('sys_debris') . ' м: <font color=\'#adaead\'>' . Format::number($result['debree']['att'][0] + $result['debree']['def'][0]) . '</font>, к: <font color=\'#ef51ef\'>' . Format::number($result['debree']['att'][1] + $result['debree']['def'][1]) . '</font></center>';

		$UserList = [];

		foreach ($attackUsers AS $info)
		{
			if (!in_array($info['tech']['id'], $UserList))
				$UserList[] = $info['tech']['id'];
		}

		$attackersReport = $raport.$raport2;

		$color = 'orange';

		if ($result['won'] == 1)
			$color = 'green';
		elseif ($result['won'] == 2)
			$color = 'red';

		$attackersReport = str_replace('#COLOR#', $color, $attackersReport);

		foreach ($UserList AS $info)
			User::sendMessage($info, 0, time(), 3, 'Боевой доклад', $attackersReport);

		$UserList = [];

		foreach ($defenseUsers AS $info)
		{
			if (!in_array($info['tech']['id'], $UserList))
				$UserList[] = $info['tech']['id'];
		}

		$defendersReport = $raport;

		$color = 'orange';

		if ($result['won'] == 1)
			$color = 'red';
		elseif ($result['won'] == 2)
			$color = 'green';

		$defendersReport = str_replace('#COLOR#', $color, $defendersReport);

		foreach ($UserList AS $info)
			User::sendMessage($info, 0, time(), 3, 'Боевой доклад', $defendersReport);

		$this->db->insertAsDict('game_log_attack',
		[
			'uid' 			=> $this->_fleet->owner,
			'time'			=> time(),
			'planet_start' 	=> 0,
			'planet_end'	=> $target->id,
			'fleet' 		=> is_array($this->_fleet->fleet_array) ? json_encode($this->_fleet->fleet_array) : $this->_fleet->fleet_array,
			'battle_log'	=> $ids
		]);

		return true;
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

	public function getGroupFleet (FleetModel $fleet, PlayerGroup $playerGroup)
	{
		$fleetData = $fleet->getShips();

		if (!count($fleetData))
		{
			if ($fleet->mission == 1 || ($fleet->mission == 2 && count($fleetData) == 1 && isset($fleetData[210])))
				$this->ReturnFleet([], $fleet->id);

			return;
		}

		if (!isset($this->usersInfo[$fleet->owner]))
			$this->usersInfo[$fleet->owner] = [];

		$this->usersInfo[$fleet->owner][$fleet->id] = [
			'galaxy' => $fleet->start_galaxy,
			'system' => $fleet->start_system,
			'planet' => $fleet->start_planet
		];

		$res = [];

		foreach ($fleetData as $shipId => $shipArr)
		{
			if (Vars::getItemType($shipId) != Vars::ITEM_TYPE_FLEET)
				continue;

			$res[$shipId] = $shipArr['count'];
		}

		if (!isset($this->usersTech[$fleet->owner]))
		{
			$user = UserModel::findFirst($fleet->owner);

			$playerObj = new Player($fleet->owner);
			$playerObj->setName($user->username);
			$playerObj->setTech(0, 0, 0);

			$info = [
				'military_tech' => $user->getTechLevel('military'),
				'defence_tech' 	=> $user->getTechLevel('defence'),
				'shield_tech' 	=> $user->getTechLevel('shield'),
				'laser_tech' 	=> $user->getTechLevel('laser'),
				'ionic_tech' 	=> $user->getTechLevel('ionic'),
				'buster_tech' 	=> $user->getTechLevel('buster'),
			];

			if ($user->rpg_komandir > time())
			{
				$info['military_tech'] 	+= 2;
				$info['defence_tech'] 	+= 2;
				$info['shield_tech'] 	+= 2;
			}

			foreach (Vars::getItemsByType(Vars::ITEM_TYPE_TECH) AS $techId)
			{
				if (isset($info[Vars::getName($techId)]) && $info[Vars::getName($techId)] > 0)
					$res[$techId] = $info[Vars::getName($techId)];
			}

			$this->usersTech[$fleet->owner] = $res;
		}
		else
		{
			$playerObj = $playerGroup->getPlayer($fleet->owner);

			if ($playerObj === false)
			{
				$info = $this->db->query('SELECT id, username FROM game_users WHERE id = ' . $fleet->owner)->fetch();

				$playerObj = new Player($fleet->owner);
				$playerObj->setName($info['username']);
				$playerObj->setTech(0, 0, 0);
			}

			foreach ($this->usersTech[$fleet->owner] AS $rId => $rVal)
			{
				if (!isset($res[$rId]))
					$res[$rId] = $rVal;
			}
		}

		$fleetObj = new Fleet($fleet->id);

		foreach ($fleetData as $shipId => $shipArr)
		{
			if (Vars::getItemType($shipId) != Vars::ITEM_TYPE_FLEET || !$shipArr['count'])
				continue;

			$fleetObj->addShipType($this->getShipType($shipId, $shipArr['count'], $res));
		}

		if (!$fleetObj->isEmpty())
			$playerObj->addFleet($fleetObj);

		if (!$playerGroup->existPlayer($fleet->owner))
			$playerGroup->addPlayer($playerObj);
	}

	public function getShipType($id, $count, $res)
	{
		$attDef 	= (isset($res[111]) ? $res[111] : 0) * 0.05;
		$attTech 	= (isset($res[109]) ? $res[109] : 0) * 0.05;

		if ($this->registry->CombatCaps[$id]['type_gun'] == 1)
			$attTech += (isset($res[120]) ? $res[120] : 0) * 0.05;
		elseif ($this->registry->CombatCaps[$id]['type_gun'] == 2)
			$attTech += (isset($res[121]) ? $res[121] : 0) * 0.05;
		elseif ($this->registry->CombatCaps[$id]['type_gun'] == 3)
			$attTech += (isset($res[122]) ? $res[122] : 0) * 0.05;

		$price = Vars::getItemPrice($id);

		$cost = [$price['metal'], $price['crystal']];

		if (Vars::getItemType($id) == Vars::ITEM_TYPE_FLEET)
			return new Ship($id, $count, $this->registry->CombatCaps[$id]['sd'], $this->registry->CombatCaps[$id]['shield'], $cost, $this->registry->CombatCaps[$id]['attack'], $attTech, ((isset($res[110]) ? $res[110] : 0) * 0.05), $attDef);

		return new Defense($id, $count, $this->registry->CombatCaps[$id]['sd'], $this->registry->CombatCaps[$id]['shield'], $cost, $this->registry->CombatCaps[$id]['attack'], $attTech, ((isset($res[110]) ? $res[110] : 0) * 0.05), $attDef);
	}

	public function convertPlayerGroupToArray (PlayerGroup $_playerGroup)
	{
		$result = [];

		foreach ($_playerGroup as $_player)
		{
			/** @var Player $_player */
			$result[$_player->getId()] = [
				'username' 	=> $_player->getName(),
				'fleet' 	=> $this->usersInfo[$_player->getId()],
				'tech' 		=> [
					'id' 			=> $_player->getId(),
					'military_tech' => isset($this->usersTech[$_player->getId()][109]) ? $this->usersTech[$_player->getId()][109] : 0,
					'shield_tech' 	=> isset($this->usersTech[$_player->getId()][110]) ? $this->usersTech[$_player->getId()][110] : 0,
					'defence_tech' 	=> isset($this->usersTech[$_player->getId()][111]) ? $this->usersTech[$_player->getId()][111] : 0,
					'laser_tech'	=> isset($this->usersTech[$_player->getId()][120]) ? $this->usersTech[$_player->getId()][120] : 0,
					'ionic_tech'	=> isset($this->usersTech[$_player->getId()][121]) ? $this->usersTech[$_player->getId()][121] : 0,
					'buster_tech'	=> isset($this->usersTech[$_player->getId()][122]) ? $this->usersTech[$_player->getId()][122] : 0
				],
				'flvl' => $this->usersTech[$_player->getId()],
			];
		}

		return $result;
	}

	public function convertRoundToArray(Round $round)
	{
		$result = [
			'attackers' 	=> [],
			'defenders' 	=> [],
			'attack'		=> ['total' => $round->getAttackersFirePower()],
			'defense' 		=> ['total' => $round->getDefendersFirePower()],
			'attackA' 		=> ['total' => $round->getAttackersFireCount()],
			'defenseA' 		=> ['total' => $round->getDefendersFireCount()]
		];

		$attackers = $round->getAfterBattleAttackers();
		$defenders = $round->getAfterBattleDefenders();

		foreach ($attackers as $_player)
		{
			foreach ($_player as $_idFleet => $_fleet)
			{
				/**
				 * @var ShipType $_ship
				 */
				foreach($_fleet as $_shipID => $_ship)
				{
					$result['attackers'][$_idFleet][$_shipID] = $_ship->getCount();

					if (!isset($result['attackA'][$_idFleet]['total']))
						$result['attackA'][$_idFleet]['total'] = 0;

					$result['attackA'][$_idFleet]['total'] += $_ship->getCount();
				}
			}
		}

		foreach ($defenders as $_player)
		{
			foreach ($_player as $_idFleet => $_fleet)
			{
				/**
				 * @var ShipType $_ship
				 */
				foreach($_fleet as $_shipID => $_ship)
				{
					$result['defenders'][$_idFleet][$_shipID] = $_ship->getCount();

					if (!isset($result['defenseA'][$_idFleet]['total']))
						$result['defenseA'][$_idFleet]['total'] = 0;

					$result['defenseA'][$_idFleet]['total'] += $_ship->getCount();
				}
			}
		}

		$result['attackShield'] = $round->getAttachersAssorbedDamage();
		$result['defShield'] 	= $round->getDefendersAssorbedDamage();

		return $result;
	}

	public function getResultFleetArray (PlayerGroup $playerGroupBeforeBattle, PlayerGroup $playerGroupAfterBattle)
	{
		$result = [];

		foreach ($playerGroupBeforeBattle->getIterator() as $idPlayer => $player)
		{
			/**
			 * @var $player Player
			 * @var $Xplayer Player
			 */
			$existPlayer = $playerGroupAfterBattle->existPlayer($idPlayer);

			$Xplayer = null;

			if ($existPlayer)
				$Xplayer = $playerGroupAfterBattle->getPlayer($idPlayer);

			foreach ($player->getIterator() as $idFleet => $fleet)
			{
				/**
				 * @var $fleet Fleet
				 * @var $Xfleet Fleet
				 */
				$existFleet = $existPlayer && $Xplayer->existFleet($idFleet);
				$Xfleet = null;

				$result[$idFleet] = [];

				if ($existFleet)
					$Xfleet = $Xplayer->getFleet($idFleet);

				foreach ($fleet as $idShipType => $fighters)
				{
					$existShipType 	= $existFleet && $Xfleet->existShipType($idShipType);

					if ($existShipType)
					{
						$XshipType = $Xfleet->getShipType($idShipType);
						/**
						 * @var $XshipType ShipType
						 */
						$result[$idFleet][$idShipType] = $XshipType->getCount();
					}
					else
						$result[$idFleet][$idShipType] = 0;
				}
			}
		}

		return $result;
	}

	private function getSteal (Planet $planet, $capacity = 0)
	{
		$steal = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];

		if ($capacity > 0)
		{
			$metal 		= $planet->metal / 2;
			$crystal 	= $planet->crystal / 2;
			$deuter 	= $planet->deuterium / 2;

			$steal['metal'] 	= min($capacity / 3, $metal);
			$capacity -= $steal['metal'];

			$steal['crystal'] 	= min($capacity / 2, $crystal);
			$capacity -= $steal['crystal'];

			$steal['deuterium'] = min($capacity, $deuter);
			$capacity -= $steal['deuterium'];

			if ($capacity > 0)
			{
				$oldStealMetal = $steal['metal'];

				$steal['metal'] += min(($capacity / 2), ($metal - $steal['metal']));
				$capacity -= $steal['metal'] - $oldStealMetal;

				$steal['crystal'] += min($capacity, ($crystal - $steal['crystal']));
			}
		}

		$steal['metal'] 	= max($steal['metal'], 0);
		$steal['crystal'] 	= max($steal['crystal'], 0);
		$steal['deuterium'] = max($steal['deuterium'], 0);

		return array_map('round', $steal);
	}
}