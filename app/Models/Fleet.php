<?php

namespace Xnova\Models;

/**
 * @author AlexPro
 * @copyright 2008 - 2019 XNova Game Group
 * Telegram: @alexprowars, Skype: alexprowars, Email: alexprowars@gmail.com
 */

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/**
 * @property $id
 * @property $owner
 * @property $owner_name
 * @property $mission
 * @property $amount
 * @property $fleet_array

 * @property $start_time
 * @property $start_galaxy
 * @property $start_system
 * @property $start_planet
 * @property $start_type

 * @property $end_time
 * @property $end_stay
 * @property $end_galaxy
 * @property $end_system
 * @property $end_planet
 * @property $end_type

 * @property $resource_metal
 * @property $resource_crystal
 * @property $resource_deuterium

 * @property $target_owner
 * @property $target_owner_name
 * @property $group_id
 * @property $mess
 * @property $create_time
 * @property $update_time
 * @property $raunds
 * @property $won
 */
class Fleet extends Model
{
	public $timestamps = false;
	public $table = 'fleets';

	public $username = '';

	public function splitStartPosition ()
	{
		return $this->start_galaxy.':'.$this->start_system.':'.$this->start_planet;
	}

	public function splitTargetPosition ()
	{
		return $this->end_galaxy.':'.$this->end_system.':'.$this->end_planet;
	}

	public function getStartAdressLink ($FleetType = '')
	{
		$uri = URL::route('galaxy', [
			'galaxy' => $this->start_galaxy,
			'system' => $this->start_system,
		], false);

		$uri = str_replace('/api', '', $uri);

		return '<a href="'.$uri.'" '.$FleetType.'>['.$this->splitStartPosition().']</a>';
	}

	public function getTargetAdressLink ($FleetType = '')
	{
		$uri = URL::route('galaxy', [
			'galaxy' => $this->end_galaxy,
			'system' => $this->end_system,
		], false);

		$uri = str_replace('/api', '', $uri);

		return '<a href="'.$uri.'" '.$FleetType.'>['.$this->splitTargetPosition().']</a>';
	}

	public function getTotalShips ()
	{
		$result = 0;

		$data = $this->getShips();

		foreach ($data as $type)
			$result += $type['count'];

		return $result;
	}

	public function getShips ($fleets = false)
	{
		if (!$fleets)
			$fleets = $this->fleet_array;

		$result = [];

		if (!is_array($fleets))
			$fleets = json_decode($fleets, true);

		if (!is_array($fleets))
			return [];

		foreach ($fleets as $fleet)
		{
			if (!isset($fleet['id']))
				continue;

			$fleetId = (int) $fleet['id'];

			$result[$fleetId] = [
				'id' => $fleetId,
				'count' => isset($fleet['count']) ? (int) $fleet['count'] : 0
			];

			if (isset($fleet['target']))
				$result[$fleetId]['target'] = (int) $fleet['target'];
		}

		return $result;
	}

	public function beforeSave ()
	{
		if (is_array($this->fleet_array))
			$this->fleet_array = json_encode(array_values($this->fleet_array));
	}

	public function canBack ()
	{
		return ($this->mess == 0 || ($this->mess == 3 && $this->mission != 15) && $this->mission != 20 && $this->target_owner != 1);
	}
}