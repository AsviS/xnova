<?php

namespace App\Battle\Models;

use App\Battle\CombatObject\PhysicShot;
use App\Battle\CombatObject\ShipsCleaner;
use Exception;

class ShipType extends Type
{
	private $originalPower;
	private $originalShield;

	private $singleShield;
	private $singleLife;
	private $singlePower;

	private $fullShield;
	private $fullLife;
	private $fullPower;

	protected $currentShield;
	protected $currentLife;

	private $weapons_tech = 0;
	private $shields_tech = 0;
	private $armour_tech = 0;

	private $rf;
	protected $lastShots;
	protected $lastShipHit;
	private $cost;

	/**
	 * ShipType::__construct()
	 *
	 * @param int $id
	 * @param int $count
	 * @param array $rf
	 * @param int $shield
	 * @param array $cost
	 * @param int $power
	 * @param int $weapons_tech
	 * @param int $shields_tech
	 * @param int $armour_tech
	 * @throws Exception
	 */
	public function __construct($id, $count, $rf, $shield, array $cost, $power, $weapons_tech = null, $shields_tech = null, $armour_tech = null)
	{
		parent::__construct($id, 0);

		$this->rf = $rf;
		$this->lastShots = 0;
		$this->lastShipHit = 0;
		$this->cost = $cost;

		$this->originalShield = $shield;
		$this->originalPower = $power;

		$this->singleShield = $shield;
		$this->singleLife = COST_TO_ARMOUR * array_sum($cost);
		$this->singlePower = $power;

		$this->increment($count);
		$this->setWeaponsTech($weapons_tech);
		$this->setArmourTech($armour_tech);
		$this->setShieldsTech($shields_tech);
	}

	/**
	 * ShipType::setWeaponsTech()
	 * Set new weapon techs level.
	 * @param int $level
	 * @throws Exception
	 */
	public function setWeaponsTech($level)
	{
		if (!is_numeric($level) || $level <= 0)
			return;

		$diff = $level - $this->weapons_tech;

		if ($diff < 0)
			throw new Exception('Trying to decrease tech');

		$this->weapons_tech = $level;
		$incr = 1 + WEAPONS_TECH_INCREMENT_FACTOR * $diff;
		$this->singlePower *= $incr;
		$this->fullPower *= $incr;
	}

	/**
	 * ShipType::setShieldsTech()
	 * Set new shield techs level.
	 * @param int $level
	 * @throws Exception
	 */
	public function setShieldsTech($level)
	{
		if (!is_numeric($level) || $level <= 0)
			return;

		$diff = $level - $this->shields_tech;

		if ($diff < 0)
			throw new Exception('Trying to decrease tech');

		$this->shields_tech = $level;
		$incr = 1 + SHIELDS_TECH_INCREMENT_FACTOR * $diff;
		$this->singleShield *= $incr;
		$this->fullShield *= $incr;
		$this->currentShield *= $incr;
	}

	/**
	 * ShipType::setArmourTech()
	 * Set new armour techs level
	 * @param int $level
	 * @throws Exception
	 */
	public function setArmourTech($level)
	{
		if (!is_numeric($level) || $level <= 0)
			return;
		
		$diff = $level - $this->armour_tech;

		if ($diff < 0)
			throw new Exception('Trying to decrease tech');

		$this->armour_tech = $level;
		$incr = 1 + ARMOUR_TECH_INCREMENT_FACTOR * $diff;
		$this->singleLife *= $incr;
		$this->fullLife *= $incr;
		$this->currentLife *= $incr;
	}

	/**
	 * ShipType::increment()
	 * Increment the amount of ships of this type.
	 * @param int $number : the amount of ships to add.
	 * @param mixed $newLife : the life of new ships added, default = full health
	 * @param mixed $newShield : the shield of new ships added, default = full shield
	 * @return void
	 */
	public function increment($number, $newLife = null, $newShield = null)
	{
		parent::increment($number);
		if ($newLife == null)
		{
			$newLife = $this->singleLife;
		}
		if ($newShield == null)
		{
			$newShield = $this->singleShield;
		}
		$this->fullLife += $this->singleLife * $number;
		$this->fullPower += $this->singlePower * $number;
		$this->fullShield += $this->singleShield * $number;

		$this->currentLife += $newLife * $number;
		$this->currentShield += $newShield * $number;
	}

	/**
	 * ShipType::decrement()
	 * Decrement the amount of ships of this type.
	 * @param int $number : the amount of ships to be removed.
	 * @param mixed $remainLife : the life of removed ships, default = full health
	 * @param mixed $remainShield : the shield of removed ships, default = full shield
	 * @return void
	 */
	public function decrement($number, $remainLife = null, $remainShield = null)
	{
		parent::decrement($number);
		if ($remainLife == null)
		{
			$remainLife = $this->singleLife;
		}
		if ($remainShield == null)
		{
			$remainShield = $this->singleShield;
		}
		$this->fullLife -= $this->singleLife * $number;
		$this->fullPower -= $this->singlePower * $number;
		$this->fullShield -= $this->singleShield * $number;

		$this->currentLife -= $remainLife * $number;
		$this->currentShield -= $remainShield * $number;
	}

	/**
	 * ShipType::setCount()
	 * Set the amount of ships of this type.
	 * @param int $number : the amount of ships.
	 * @param mixed $life : the life of ships, default = full health
	 * @param mixed $shield : the life of ships, default = full health
	 * @return void
	 */
	public function setCount($number, $life = null, $shield = null)
	{
		parent::setCount($number);
		$diff = $number - $this->getCount();
		if ($diff > 0)
		{
			$this->increment($diff, $life, $shield);
		}
		elseif ($diff < 0)
		{
			$this->decrement($diff, $life, $shield);
		}
	}

	/**
	 * ShipType::getCost()
	 * Get the array of cost to build this type of ship.
	 * @return array
	 */
	public function getCost()
	{
		return $this->cost;
	}

	/**
	 * ShipType::getWeaponsTech()
	 * Get the level of current weapon tech.
	 * @return int
	 */
	public function getWeaponsTech()
	{
		return $this->weapons_tech;
	}

	/**
	 * ShipType::getShieldsTech()
	 * Get the level of current shield tech.
	 * @return int
	 */
	public function getShieldsTech()
	{
		return $this->shields_tech;
	}

	/**
	 * ShipType::getArmourTech()
	 * Get the level of current armour tech.
	 * @return int
	 */
	public function getArmourTech()
	{
		return $this->armour_tech;
	}

	/**
	 * ShipType::getRfTo()
	 * Get the propability of this shipType to shot again given shipType
	 * @param ShipType $other
	 * @return int
	 */
	public function getRfTo(ShipType $other)
	{
		return (isset($this->rf[$other->getId()])) ? $this->rf[$other->getId()] : 0;
	}


	/**
	 * ShipType::getRF()
	 * Get an array of rapid fire
	 * @return array
	 */
	public function getRF()
	{
		return $this->rf;
	}

	/**
	 * ShipType::getShield()
	 * Get the shield value of a single ship of this type.
	 * @return int
	 */
	public function getShield()
	{
		return $this->singleShield;
	}

	/**
	 * ShipType::getShieldCellValue()
	 * Get the shield cell value of a single ship of this type.
	 * @return int
	 */
	public function getShieldCellValue()
	{
		if ($this->isShieldDisabled())
		{
			return 0;
		}
		return $this->singleShield / SHIELD_CELLS;
	}

	/**
	 * ShipType::getHull()
	 * Get the hull value of a single ship of this type.
	 * @return int
	 */
	public function getHull()
	{
		return $this->singleLife;
	}

	/**
	 * ShipType::getPower()
	 * Get the power value of a single ship of this type.
	 * @return int
	 */
	public function getPower()
	{
		return $this->singlePower;
	}

	/**
	 * ShipType::getCurrentShield()
	 * Get the current shield value of a all ships of this type.
	 * @return int
	 */
	public function getCurrentShield()
	{
		return $this->currentShield;
	}

	/**
	 * ShipType::getCurrentLife()
	 * Get the current hull value of a all ships of this type.
	 * @return int
	 */
	public function getCurrentLife()
	{
		return $this->currentLife;
	}

	/**
	 * ShipType::getCurrentPower()
	 * Get the current attack power value of a all ships of this type.
	 * @return int
	 */
	public function getCurrentPower()
	{
		return $this->fullPower;
	}

	/**
	 * ShipType::inflictDamage()
	 * Inflict damage to all ships of this type.
	 * @param int $damage
	 * @param int $shotsToThisShipType
	 * @return PhysicShot|bool
	 * @throws Exception
	 */
	public function inflictDamage($damage, $shotsToThisShipType)
	{
		if ($shotsToThisShipType == 0)
			return false;
		if ($shotsToThisShipType < 0)
			throw new Exception("Negative amount of shotsToThisShipType!");

		\log_var('Defender single hull', $this->singleLife);
		\log_var('Defender count', $this->getCount());
		\log_var('currentShield before', $this->currentShield);
		\log_var('currentLife before', $this->currentLife);

		$this->lastShots += $shotsToThisShipType;
		$ps = new PhysicShot($this, $damage, $shotsToThisShipType);
		$ps->start();
		\log_var('$ps->getAssorbedDamage()', $ps->getAssorbedDamage());
		$this->currentShield -= $ps->getAssorbedDamage();

		if ($this->currentShield < 0 && $this->currentShield > -EPSILON)
		{
			\log_comment('fixing double number currentshield');
			$this->currentShield = 0;
		}

		$this->currentLife -= $ps->getHullDamage();

		if ($this->currentLife < 0 && $this->currentLife > -EPSILON)
		{
			\log_comment('fixing double number currentlife');
			$this->currentLife = 0;
		}

		\log_var('currentShield after', $this->currentShield);
		\log_var('currentLife after', $this->currentLife);
		$this->lastShipHit += $ps->getHitShips();
		\log_var('lastShipHit after', $this->lastShipHit);
		\log_var('lastShots after', $this->lastShots);

		if ($this->currentLife < 0)
			throw new Exception('Negative currentLife!');
		if ($this->currentShield < 0)
			throw new Exception('Negative currentShield!');
		if ($this->lastShipHit < 0)
			throw new Exception('Negative lastShipHit!');

		return $ps; //for web
	}

	/**
	 * ShipType::cleanShips()
	 * Start the task of explosion system.
	 * @return ShipsCleaner
	 */
	public function cleanShips()
	{
		\log_var('lastShipHit after', $this->lastShipHit);
		\log_var('lastShots after', $this->lastShots);
		\log_var('currentLife before', $this->currentLife);

		$sc = new ShipsCleaner($this, $this->lastShipHit, $this->lastShots);
		$sc->start();
		$this->decrement($sc->getExplodedShips(), $sc->getRemainLife(), 0);
		$this->lastShipHit = 0;
		$this->lastShots = 0;
		\log_var('currentLife after', $this->currentLife);
		return $sc;
	}

	/**
	 * ShipType::repairShields()
	 * Repair all shields.
	 * @return void
	 */
	public function repairShields()
	{
		$this->currentShield = $this->fullShield;
	}

	/**
	 * ShipType::isShieldDisabled()
	 * Return true if the current shield of each ships are almost zero.
	 * @return boolean
	 */
	public function isShieldDisabled()
	{
		return $this->currentShield / $this->getCount() < 0.01;
	}

	/**
	 * ShipType::cloneMe()
	 *
	 * @return $this
	 */
	public function cloneMe()
	{
		$class = get_class($this);
		$tmp = new $class($this->getId(), $this->getCount(), $this->rf, $this->originalShield, $this->cost, $this->originalPower, $this->weapons_tech, $this->shields_tech, $this->armour_tech);
		$tmp->currentShield = $this->currentShield;
		$tmp->currentLife = $this->currentLife;
		$tmp->lastShots = $this->lastShots;
		$tmp->lastShipHit = $this->lastShipHit;
		return $tmp;
	}
}
