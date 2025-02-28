<?php
class Creeper extends Monster{
	const TYPE = MOB_CREEPER;
	const EXPL_TIME = 30;
	public $timeUntilExplode;
	function __construct(Level $level, $eid, $class, $type = 0, $data = []){
		parent::__construct($level, $eid, $class, $type, $data);
		$this->setHealth(isset($this->data["Health"]) ? $this->data["Health"] : 16, "generic");
		$this->setName("Creeper");
		$this->ignited = 0;
		$this->setSize(0.6, 1.7);
		$this->setSpeed(0.25);
		$this->update();
		$this->timeUntilExplode = $this->isIgnited() ? self::EXPL_TIME : 0;
	}
	
	
	public function setIgnited($v = null){
		$this->setState($v === null ? !$this->getState() : $v);	
	}
	
	/**
	 * @return boolean
	 */
	public function isIgnited(){
		return (boolean)$this->getState();
	}
	
	public function interactWith(Entity $e, $action){
		if($e->isPlayer() && $action === InteractPacket::ACTION_HOLD){
			$slot = $e->player->getHeldItem();
			if($slot->getID() === FLINT_AND_STEEL && !$this->isIgnited()){
				if($slot->useOn($e) and $slot->getMetadata() >= $slot->getMaxDurability()){
					$e->player->removeItem($slot->getID(), $slot->getMetadata(), $slot->count, true);
				}
				$this->ignite();
				return true;
			}
		}
		return parent::interactWith($e, $action);
	}
	
	public function ignite(){
		$this->setIgnited(1);
		$this->timeUntilExplode = self::EXPL_TIME;
	}
	
	public function update(){
		if($this->timeUntilExplode === 1){
			$this->explode();
		}
		if($this->timeUntilExplode >= 0){
			--$this->timeUntilExplode;
		}
		parent::update();
	}

	public function explode()
	{
		if($this->closed){
			return false;
		}
		$this->setIgnited(0);
		$explosion = new Explosion($this, 3);
		$explosion->explode();
		$this->close();
	}
	
	public function getDrops(){
		return [
			[GUNPOWDER, 0, mt_rand(0,2)]
		];
	}
}