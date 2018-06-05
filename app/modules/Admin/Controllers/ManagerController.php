<?php

namespace Admin\Controllers;

use Admin\Controller;
use Friday\Core\Lang;
use Xnova\Models\User;

/**
 * @RoutePrefix("/admin/manager")
 * @Route("/")
 * @Route("/{action}/")
 * @Route("/{action}{params:(/.*)*}")
 * @Private
 */
class ManagerController extends Controller
{
	const CODE = 'manager';

	public function initialize ()
	{
		parent::initialize();

		if (!$this->access->canReadController(self::CODE, 'admin'))
			throw new \Exception('Access denied');

		Lang::includeLang('main', 'xnova');
	}

	public static function getMenu ()
	{
		return [[
			'code'	=> 'manager',
			'title' => 'Редактор',
			'icon'	=> 'magic-wand',
			'sort'	=> 70,
			'childrens' => [
				[
					'code'	=> 'ip',
					'title' => 'Поиск по IP',
				],
				[
					'code'	=> 'data',
					'title' => 'Статистика',
				],
				[
					'code'	=> 'level',
					'title' => 'Смена прав',
				]
			]
		]];
	}

	public function indexAction ()
	{
		$this->tag->setTitle(_getText('panel_mainttl'));
	}

	public function ipAction ()
	{
		if ($this->request->has('send'))
		{
			$Pattern = addslashes($_POST['ip']);
			$SelUser = $this->db->query("SELECT * FROM game_users WHERE ip = INET_ATON('" . $Pattern . "');");
			$parse = [];
			$parse['adm_this_ip'] = $Pattern;
			$parse['adm_plyer_lst'] = '';

			while ($Usr = $SelUser->fetch())
			{
				$UsrMain = $this->db->query("SELECT name FROM game_planets WHERE id = '" . $Usr['planet_id'] . "';")->fetch();
				$parse['adm_plyer_lst'] .= "<tr><th>" . $Usr['username'] . "</th><th>[" . $Usr['galaxy'] . ":" . $Usr['system'] . ":" . $Usr['planet'] . "] " . $UsrMain['name'] . "</th></tr>";
			}

			$this->view->pick('manager/adminpanel_ans2');
			$this->view->setVar('parse', $parse);
		}
	}

	public function dataAction ()
	{
		if ($this->request->has('send'))
		{
			$username = $this->request->get('username');

			$SelUser = $this->db->query("SELECT u.*, ui.* FROM game_users u, game_users_info ui WHERE ui.id = u.id AND ".(is_numeric($username) ? "u.id = '" . $username . "'" : "u.username = '" . $username . "'")." LIMIT 1;")->fetch();

			if (!isset($SelUser['id']))
				$this->message('Такого игрока не существует', 'Ошибка', '/admin/manager/', 2);

			$user = User::findFirst((int) $SelUser['id']);

			$parse = [];
			$parse['answer1'] = $SelUser['id'];
			$parse['answer2'] = $SelUser['username'];
			$parse['answer3'] = long2ip($SelUser['ip']);
			$parse['answer4'] = $SelUser['email'];
			$parse['answer6'] = Lang::getText('admin', 'adm_usr_genre', $SelUser['sex']);
			$parse['answer7'] = date('d.m.Y H:i:s', $SelUser['vacation']);
			$parse['answer9'] = date('d.m.Y H:i:s', $SelUser['create_time']);
			$parse['answer8'] = "[" . $SelUser['galaxy'] . ":" . $SelUser['system'] . ":" . $SelUser['planet'] . "] ";

			$parse['planet_list'] = [];
			$parse['planet_fields'] = $this->registry->resource;

			$parse['planet_list'] = $this->db->fetchAll("SELECT * FROM game_planets WHERE id_owner = '" . $SelUser['id'] . "' ORDER BY id ASC");

			$parse['history_actions'] = [
				1 => 'Постройка здания',
				2 => 'Снос здания',
				3 => 'Отмена постройки',
				4 => 'Отмена сноса',
				5 => 'Исследование',
				6 => 'Отмена исследования',
				7 => 'Постройка обороны/флота',
			];

			$parse['list_transfer'] = [];
			$parse['list_transfer_income'] = [];

			$transfers = $this->db->query("SELECT t.*, u.username AS target FROM game_log_transfers t LEFT JOIN game_users u ON u.id = t.target_id WHERE (t.user_id = '" . $SelUser['id'] . "' OR t.target_id = '" . $SelUser['id'] . "') ORDER BY id DESC");

			while ($transfer = $transfers->fetch())
			{
				$data = json_decode($transfer['data'], true);

				if (!is_array($data))
					continue;

				$row = [
					'time' => $transfer['time'],
					'start' => '<a href="'.$this->url->get('galaxy/'.$data['planet']['galaxy'].'/'.$data['planet']['system'].'/', null, null, $this->config->application->baseUri).'" target="_blank">'.$data['planet']['galaxy'].':'.$data['planet']['system'].':'.$data['planet']['planet'].' ('._getText('type_planet', $data['planet']['type']).')</a>',
					'end' => '<a href="'.$this->url->get('galaxy/'.$data['target']['galaxy'].'/'.$data['target']['system'].'/', null, null, $this->config->application->baseUri).'" target="_blank">'.$data['target']['galaxy'].':'.$data['target']['system'].':'.$data['target']['planet'].' ('._getText('type_planet', $data['target']['type']).')</a>',
					'metal' => $data['resources']['metal'],
					'crystal' => $data['resources']['crystal'],
					'deuterium' => $data['resources']['deuterium'],
					'target' => $transfer['target'],
				];

				if ($transfer['user_id'] == $SelUser['id'])
					$parse['list_transfer'][] = $row;
				else
					$parse['list_transfer_income'][] = $row;
			}

			$parse['list_history'] = $this->db->fetchAll("SELECT * FROM game_log_history WHERE user_id = ".$SelUser['id']." AND time > ".(time() - 86400 * 7)." ORDER BY time DESC");

			$parse['list_tech'] = [];

			foreach ($this->registry->reslist['tech'] AS $Item)
			{
				if (isset($this->registry->resource[$Item]))
					$parse['list_tech'][$Item] = $user->getTechLevel($Item);
			}

			$logs = $this->db->query("SELECT ip, time FROM game_log_ip WHERE id = " . $SelUser['id'] . " ORDER BY time DESC");

			$parse['list_ip'] = [];

			while ($log = $logs->fetch())
			{
				$parse['list_ip'][] = [
					'ip' => long2ip($log['ip']),
					'date' => $this->game->datezone("d.m.Y H:i", $log['time'])
				];
			}

			$logs = $this->db->query("SELECT time, credits, type FROM game_log_credits WHERE uid = " . $SelUser['id'] . " ORDER BY time DESC");

			$parse['list_credits'] = [];

			while ($log = $logs->fetch())
			{
				$parse['list_credits'][] = [
					'date' => $this->game->datezone("d.m.Y H:i", $log['time']),
					'credits' => $log['credits'],
					'type' => Lang::getText('admin', 'adm_credits_type', $log['type'])
				];
			}

			$logs = $this->db->query("SELECT time, planet_start, planet_end, fleet, battle_log FROM game_log_attack WHERE uid = " . $SelUser['id'] . " ORDER BY time DESC");

			$parse['list_attacks'] = [];

			while ($log = $logs->fetch())
			{
				$parse['list_attacks'][] = [
					'date' => $this->game->datezone("d.m.Y H:i", $log['time']),
					'start' => $log['planet_start'],
					'end' => $log['planet_end'],
					'url' => $this->url->get("rw/".$log['battle_log']."/".md5($this->config->application->encryptKey . $log['battle_log']).'/', null, null, $this->config->application->baseUri),
					'fleet' => $log['fleet']
				];
			}

			$logs = $this->db->query("SELECT ip FROM game_log_ip WHERE id = " . $SelUser['id'] . " GROUP BY ip");

			$parse['list_mult'] = [];

			while ($log = $logs->fetch())
			{
				$ips = $this->db->query("SELECT u.id, u.username, l.time FROM game_log_ip l LEFT JOIN game_users u ON u.id = l.id WHERE l.ip = " . $log['ip'] . " AND l.id != " . $SelUser['id'] . " GROUP BY l.id;");

				while ($ip = $ips->fetch())
				{
					$parse['list_mult'] = [
						'date' => $this->game->datezone("d.m.Y H:i", $ip['time']),
						'ip' => long2ip($log['ip']),
						'user_id' => $ip['id'],
						'user_name' => $ip['username']
					];
				}
			}

			$logs = $this->db->query("SELECT u_id, a_id, text, time FROM game_private WHERE u_id = " . $SelUser['id'] . " ORDER BY time DESC");

			$parse['list_ld'] = [];

			while ($log = $logs->fetch())
			{
				$parse['list_ld'][] = [
					'date' => $this->game->datezone("d.m.Y H:i", $log['time']),
					'user_id' => $log['a_id'],
					'text' => $log['text']
				];
			}

			$this->view->pick('manager/adminpanel_ans1');
			$this->view->setVar('parse', $parse);
		}
	}
}