<?php

class PlayerAPI{

	private $server;

	function __construct(){
		$this->server = ServerAPI::request();
	}

	public function init(){
		$this->server->schedule(20 * 15, [$this, "handle"], 1, true, "server.regeneration");
		$this->server->addHandler("player.death", [$this, "handle"], 1);
		$this->registerCmd("list");
		$this->registerCmd("kill", "<player>");
		$this->registerCmd("gamemode", "<mode> [player]", [$this, "commandHandler"]);
		$this->registerCmd("tp", "[target player] <destination player|w:world> OR /tp [target player] <x> <y> <z>");
		$this->registerCmd("spawnpoint", "[player] [x] [y] [z]");
		$this->registerCmd("spawn");
		$this->registerCmd("ping");
		$this->server->api->console->alias("lag", "ping");
				$this->server->api->console->alias("gm", "gamemode");
		$this->server->api->console->alias("who", "list");
		$this->server->api->console->alias("suicide", "kill");
		$this->server->api->console->alias("tppos", "tp");
		$this->server->api->console->cmdWhitelist("list");
		$this->server->api->console->cmdWhitelist("ping");
		$this->server->api->console->cmdWhitelist("spawn");
		$this->server->preparedSQL->selectPlayersToHeal = $this->server->database->prepare("SELECT EID FROM entities WHERE class = " . ENTITY_PLAYER . " AND health < 20;");
	}

	public function registerCmd($cmd, $help = ""){
		$this->server->api->console->register($cmd, $help, [$this, "commandHandler"]);
	}

	public function handle($data, $event){
		switch($event){
			case "server.regeneration":
				if($this->server->difficulty === 0){
					$result = $this->server->preparedSQL->selectPlayersToHeal->execute();
					if($result !== false){
						while(($player = $result->fetchArray()) !== false){
							if(($player = $this->server->api->entity->get($player["EID"])) !== false){
								if($player->getHealth() <= 0){
									continue;
								}
								$player->setHealth(min(20, $player->getHealth() + $data), "regeneration");
							}
						}
						return true;
					}
				}
				break;
			case "player.death":
				if(is_numeric($data["cause"])){
					$e = $this->server->api->entity->get($data["cause"]);
					if($e instanceof Entity){
						switch($e->class){
							case ENTITY_PLAYER:
								$message = " was killed by " . $e->name;
								break;
							default:
								$message = " was killed";
								break;
						}
					}
				}else{
					switch($data["cause"]){
						case "cactus":
							$message = " was pricked to death";
							break;
						case "lava":
							$message = " tried to swim in lava";
							break;
						case "fire":
							$message = " went up in flames";
							break;
						case "burning":
							$message = " burned to death";
							break;
						case "suffocation":
							$message = " suffocated in a wall";
							break;
						case "water":
							$message = " drowned";
							break;
						case "void":
							$message = " fell out of the world";
							break;
						case "fall":
							$message = " hit the ground too hard";
							break;
						case "explosion":
							$message = " blew up";
							break;
						default:
							$message = " died";
							break;
					}
				}
				$this->server->api->chat->broadcast($data["player"]->username . $message);
				return true;
				break;
		}
	}

	public function commandHandler($cmd, $args, $issuer, $alias){
		$output = "";
		switch($cmd){
			case "spawnpoint":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command in-game.\n";
					break;
				}

				if(count($args) === 1 or count($args) === 4){
					$target = $this->server->api->player->get(array_shift($args));
				}else{
					$target = $issuer;
				}

				if(!($target instanceof Player)){
					$output .= "That player cannot be found.\n";
					break;
				}

				if(count($args) === 3){
					$spawn = new Position(floatval(array_shift($args)), floatval(array_shift($args)), floatval(array_shift($args)), $issuer->level);
				}else{
					$spawn = new Position($issuer->entity->x, $issuer->entity->y, $issuer->entity->z, $issuer->entity->level);
				}

				$target->setSpawn($spawn);
				$output .= "Spawnpoint set correctly!\n";
				break;
			case "spawn":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command in-game.\n";
					break;
				}
				$issuer->teleport($this->server->spawn);
				break;
			case "ping":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command in-game.\n";
					break;
				}
				$output .= "ping " . round($issuer->getLag(), 2) . "ms, packet loss " . round($issuer->getPacketLoss() * 100, 2) . "%, " . round($issuer->getBandwidth() / 1024, 2) . " KB/s\n";
				break;
			case "gamemode":
				$player = false;
				$setgm = false;
				$gms = [
					"0" => SURVIVAL,
					"survival" => SURVIVAL,
					"s" => SURVIVAL,
					"1" => CREATIVE,
					"creative" => CREATIVE,
					"c" => CREATIVE,
					"2" => ADVENTURE,
					"adventure" => ADVENTURE,
					"a" => ADVENTURE,
					"3" => VIEW,
					"view" => VIEW,
					"viewer" => VIEW,
					"spectator" => VIEW,
					"v" => VIEW,
				];
				if($issuer instanceof Player){
					$player = $issuer;
				}
				if(count($args) == 1){
					$setgm = $args[0];
				}elseif(isset($args[1])){
					if($this->server->api->player->get($args[1]) instanceof Player){
						$player = $this->server->api->player->get($args[1]);
						$setgm = $args[0];
					}elseif($this->server->api->player->get($args[0]) instanceof Player){
						$player = $this->server->api->player->get($args[0]);
						$setgm = $args[1];
					}else{
						$output .= "Usage: /$cmd <mode> [player] or /$cmd [player] <mode>\n";
						break;
					}
				}
				if(!($player instanceof Player) or !isset($gms[strtolower($setgm)])){
					$output .= "Usage: /$cmd <mode> [player] or /$cmd [player] <mode>\n";
					break;
				}
				if($player->setGamemode($gms[strtolower($setgm)])){
					$output .= "Gamemode of " . $player->username . " changed to " . $player->getGamemode() . "\n";
				}
				break;
			case "tp":
				if(count($args) <= 2 or substr($args[0], 0, 2) === "w:" or substr($args[1], 0, 2) === "w:"){
					if((!isset($args[1]) or substr($args[0], 0, 2) === "w:") and isset($args[0]) and ($issuer instanceof Player)){
						$name = $issuer->username;
						$target = implode(" ", $args);
					}elseif(isset($args[1]) and isset($args[0])){
						$name = array_shift($args);
						$target = implode(" ", $args);
					}else{
						$output .= "Usage: /$cmd [target player] <destination player>\n";
						break;
					}
					if($this->teleport($name, $target) !== false){
						$output .= "\"$name\" teleported to \"$target\"\n";
					}else{
						$output .= "Couldn't teleport.\n";
					}
				}else{
					if(!isset($args[3]) and isset($args[2]) and isset($args[1]) and isset($args[0]) and ($issuer instanceof Player)){
						$name = $issuer->username;
						$x = $args[0];
						$y = $args[1];
						$z = $args[2];
					}elseif(isset($args[3]) and isset($args[2]) and isset($args[1]) and isset($args[0])){
						$name = $args[0];
						$x = $args[1];
						$y = $args[2];
						$z = $args[3];
					}else{
						$output .= "Usage: /$cmd [player] <x> <y> <z>\n";
						break;
					}
					if($this->tppos($name, $x, $y, $z)){
						$output .= "\"$name\" teleported to ($x, $y, $z)\n";
					}else{
						$output .= "Couldn't teleport.\n";
					}
				}
				break;
			case "kill":
			case "suicide":
				if(!isset($args[0]) and ($issuer instanceof Player)){
					$player = $issuer;
				}else{
					$player = $this->get($args[0]);
				}
				if($player instanceof Player){
					$player->entity->harm(PHP_INT_MAX, "console", true);
					$player->sendChat("Ouch. That looks like it hurt.\n");
				}else{
					$output .= "Usage: /$cmd [player]\n";
				}
				break;
			case "list":
				$output .= "There are " . count($this->server->clients) . "/" . $this->server->maxClients . " players online:\n";
				if(count($this->server->clients) == 0){
					break;
				}
				foreach($this->server->clients as $c){
					$output .= $c->username . ", ";
				}
				$output = substr($output, 0, -2) . "\n";
				break;
		}
		return $output;
	}

	public function get($name, $alike = true, $multiple = false){
		$name = trim(strtolower($name));
		if($name === ""){
			return false;
		}
		$query = $this->server->query("SELECT ip,port,name FROM players WHERE name " . ($alike === true ? "LIKE '%" . $name . "%'" : "= '" . $name . "'") . ";");
		$players = [];
		if($query !== false and $query !== true){
			while(($d = $query->fetchArray(SQLITE3_ASSOC)) !== false){
				$CID = PocketMinecraftServer::clientID($d["ip"], $d["port"]);
				if(isset($this->server->clients[$CID])){
					$players[$CID] = $this->server->clients[$CID];
					if($multiple === false and $d["name"] === $name){
						return $players[$CID];
					}
				}
			}
		}

		if($multiple === false){
			if(count($players) > 0){
				return array_shift($players);
			}else{
				return false;
			}
		}else{
			return $players;
		}
	}

	public function teleport(&$name, &$target){
		if(substr($target, 0, 2) === "w:"){
			$lv = $this->server->api->level->get(substr($target, 2));
			if($lv instanceof Level){
				$origin = $this->get($name);
				if($origin instanceof Player){
					$name = $origin->username;
					return $origin->teleport($lv->getSafeSpawn());
				}
			}else{
				return false;
			}
		}
		$player = $this->get($target);
		if(($player instanceof Player) and ($player->entity instanceof Entity)){
			$target = $player->username;
			$origin = $this->get($name);
			if($origin instanceof Player){
				$name = $origin->username;
				return $origin->teleport($player->entity);
			}
		}
		return false;
	}

	public function tppos(&$name, &$x, &$y, &$z){
		$player = $this->get($name);
		if(($player instanceof Player) and ($player->entity instanceof Entity)){
			$name = $player->username;
			$x = $x[0] === "~" ? $player->entity->x + floatval(substr($x, 1)) : floatval($x);
			$y = $y[0] === "~" ? $player->entity->y + floatval(substr($y, 1)) : floatval($y);
			$z = $z[0] === "~" ? $player->entity->z + floatval(substr($z, 1)) : floatval($z);
			$player->teleport(new Vector3($x, $y, $z));
			return true;
		}
		return false;
	}

	public function broadcastPacket(array $players, RakNetDataPacket $packet){
		foreach($players as $p){
			$p->dataPacket(clone $packet);
		}
	}

	public function online(){
		$o = [];
		foreach($this->server->clients as $p){
			if($p->auth === true){
				$o[] = $p->username;
			}
		}
		return $o;
	}

	public function add($CID){
		if(isset($this->server->clients[$CID])){
			$player = $this->server->clients[$CID];
			$player->data = $this->getOffline($player->username);
			$player->gamemode = $player->data->get("gamemode");
			if(($player->level = $this->server->api->level->get($player->data->get("position")["level"])) === false){
				$player->level = $this->server->api->level->getDefault();
				$player->data->set("position", [
					"level" => $player->level->getName(),
					"x" => $player->level->getSpawn()->x,
					"y" => $player->level->getSpawn()->y,
					"z" => $player->level->getSpawn()->z,
				]);
			}
			$this->server->query("INSERT OR REPLACE INTO players (CID, ip, port, name) VALUES (" . $player->CID . ", '" . $player->ip . "', " . $player->port . ", '" . strtolower($player->username) . "');");
		}
	}

	public function getOffline($name, $create = true){
		$iname = strtolower($name);
		$default = [
			"caseusername" => $name,
			"position" => [
				"level" => $this->server->spawn->level->getName(),
				"x" => $this->server->spawn->x,
				"y" => $this->server->spawn->y,
				"z" => $this->server->spawn->z,
			],
			"spawn" => [
				"level" => $this->server->spawn->level->getName(),
				"x" => $this->server->spawn->x,
				"y" => $this->server->spawn->y,
				"z" => $this->server->spawn->z,
			],
			"inventory" => array_fill(0, PLAYER_SURVIVAL_SLOTS, [AIR, 0, 0]),
			"hotbar" => [0, -1, -1, -1, -1, -1, -1, -1, -1],
			"armor" => array_fill(0, 4, [AIR, 0]),
			"gamemode" => $this->server->gamemode,
			"health" => 20,
			"lastIP" => "",
			"lastID" => 0,
			"achievements" => [],
		];

		if(!file_exists(DATA_PATH . "players/" . $iname . ".yml")){
			if($this->server->extraprops->get("save-player-data") && $create){
				console("[NOTICE] Player data not found for \"" . $iname . "\", creating new profile");
				$data = new Config(DATA_PATH . "players/" . $iname . ".yml", CONFIG_YAML, $default);
				$data->save();
			}else{
				return false;
			}
		}

		$data = new Config(DATA_PATH . "players/" . $iname . ".yml", CONFIG_YAML, $default);

		if(($data->get("gamemode") & 0x01) === 1){
			$data->set("health", 20);
		}
		$this->server->handle("player.offline.get", $data);
		return $data;
	}

	public function spawnAllPlayers(Player $player){
		foreach($this->getAll() as $p){
			if($p !== $player and ($p->entity instanceof Entity)){
				$p->entity->spawn($player);
				if($p->level !== $player->level){
					$pk = new MoveEntityPacket_PosRot;
					$pk->eid = $p->entity->eid;
					$pk->x = -256;
					$pk->y = 128;
					$pk->z = -256;
					$pk->yaw = 0;
					$pk->pitch = 0;
					$player->dataPacket($pk);
				}
			}
		}
	}

	public function getAll($level = null){
		if($level instanceof Level){
			$clients = [];
			$l = $this->server->query("SELECT EID FROM entities WHERE level = '" . $level->getName() . "' AND class = '" . ENTITY_PLAYER . "';");
			if($l !== false and $l !== true){
				while(($e = $l->fetchArray(SQLITE3_ASSOC)) !== false){
					$e = $this->getByEID($e["EID"]);
					if($e instanceof Player){
						$clients[$e->CID] = $e;
					}
				}
			}			return $clients;

		}
		return $this->server->clients;
	}

	public function getByEID($eid){
		$eid = (int) $eid;
		$CID = $this->server->query("SELECT ip,port FROM players WHERE EID = '" . $eid . "';", true);
		$CID = PocketMinecraftServer::clientID($CID["ip"], $CID["port"]);
		if(isset($this->server->clients[$CID])){
			return $this->server->clients[$CID];
		}
		return false;
	}

	public function spawnToAllPlayers(Player $player){
		foreach($this->getAll() as $p){
			if($p !== $player and ($p->entity instanceof Entity) and ($player->entity instanceof Entity)){
				$player->entity->spawn($p);
				if($p->level !== $player->level){
					$pk = new MoveEntityPacket_PosRot;
					$pk->eid = $player->entity->eid;
					$pk->x = -256;
					$pk->y = 128;
					$pk->z = -256;
					$pk->yaw = 0;
					$pk->pitch = 0;
					$p->dataPacket($pk);
				}
			}
		}
	}

	public function remove($CID){
		if(isset($this->server->clients[$CID])){
			$player = $this->server->clients[$CID];
			unset($this->server->clients[$CID]);
			$player->close();
			if($player->username != "" and ($player->data instanceof Config)){
				$this->saveOffline($player->data);
			}
			$this->server->query("DELETE FROM players WHERE name = '" . $player->username . "';");
			if($player->entity instanceof Entity){
				unset($player->entity->player);
				//unset($player->entity);
			}
			$this->server->api->entity->remove($player->eid);
			$player = null;
			unset($player);
		}
	}

	public function saveOffline(Config $data){
		if($this->server->extraprops->get("save-player-data")){
			$this->server->handle("player.offline.save", $data);
			$data->save();
		}
	}
}