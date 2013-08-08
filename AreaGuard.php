<?php

/*
__PocketMine Plugin__
name=AreaGuard
description=Protect your stuff
version=1.0
author=wiezz
class=AreaGuard
apiversion=9
*/

class AreaGuard implements Plugin{

	private $api, $players, $path, $areas, $pos1, $pos2;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->pos1 = array();
		$this->pos2 = array();
		$this->players = array();
		$this->areas = array();
	}
	
	public function init(){
		$this->path = $this->api->plugin->configPath($this);
		$this->api->addHandler("player.move", array($this, "playermove"));
		$this->api->addHandler("player.block.place", array($this, "block"));
		$this->api->addHandler("player.block.break", array($this, "block"));
		$this->api->addHandler("player.block.touch", array($this, "blocktouch"));
		$this->api->addHandler("player.interact", array($this, "playerinteract"));
		$this->config = new Config($this->path."config.yml", CONFIG_YAML, array(
			'WhitelistCommands' => true,
			'Maxareas' => 2,
			'Maxareasize' => 100,
			'Defaults' => array(
				'Build' => true,
				'PvP' => true,
				'Entry' => true,
				'ChestAcces' => true,
			),
		));
		$this->config = $this->api->plugin->readYAML($this->path . "config.yml");
		$this->api->console->register("ag", "AreaProtect commands", array($this, "command"));
		if($this->config['WhitelistCommands'] === true) $this->api->ban->cmdWhitelist("ag");
		$this->areas = array();
		if(file_exists($this->path . "areas.data")){
			$file = file_get_contents($this->path . "areas.data");
			$this->areas = json_decode($file, true);
		}
	}
	
	
	public function playermove($data){
		$username = $data->player->username;
		foreach($this->areas as $name => $area){
			if($data->player->level->getName() == $area['level']){
				$x = $data->x;
				$y = $data->y;
				$z = $data->z;
				if(($area['pos1'][0] <= $x and $x <= $area['pos2'][0]) and ($area['pos1'][1] <= $y and $y <= $area['pos2'][1]) and ($area['pos1'][2] <= $z and $z <= $area['pos2'][2])){
					if(($area['entry'] == false) and ($area['owner'] != $username) and (!in_array($username, $area['members'])) and !$this->api->ban->isOp($username)){
						$data->player->sendChat("This area is protected");
						return false;
					}elseif(isset($this->players[$username])){
						if($this->players[$username] != $name){
							$this->players[$username] = $name;
							$data->player->sendChat($area['greetingmsg']);
						}
					}
					return;
				}
			}
		}
		if(isset($this->players[$username]) and ($this->players[$username] != 'default')){
			$name = $this->players[$username];
			$data->player->sendChat($this->areas[$name]['farewellmsg']);
		}
		$this->players[$username] = 'default';		
	}
	
	public function block($data, $event){
		$block = $data["target"];
		$username = $data["player"]->username;
		foreach($this->areas as $name => $area){
			if($area == "") return;
			if(($event == 'player.block.touch') and ($area['chestacces'] == false)) return;
			if(($area['build'] === false) and ($area['owner'] != $username) and ($data["player"]->level->getName() === $area['level']) and (!in_array($username, $area['members'])) and !$this->api->ban->isOp($username)){
				$x = $block->x;
				if($event == 'player.block.place'){
					$y = ($block->y + 1);
				}else{
					$y = $block->y;
				}
				$z = $block->z;
				if(($area['pos1'][0] <= $x and $x <= $area['pos2'][0]) and ($area['pos1'][1] <= $y and $y <= $area['pos2'][1]) and ($area['pos1'][2] <= $z and $z <= $area['pos2'][2])){
					$data['player']->sendChat("This area is protected");
					return false;
				}
			}
		}
	}
	
	public function blocktouch($data){
		if($data['target']->getID() == 54){
			$username = $data['player']->username;
			foreach($this->areas as $name => $area){
				if(($data['player']->level->getName() == $area['level']) and ($area['chestacces'] == false) and ($area['owner'] != $username) and (!in_array($username, $area['members'])) and !$this->api->ban->isOp($username)){
					$x = $data['target']->x;
					$y = $data['target']->y;
					$z = $data['target']->z;
					if(($area['pos1'][0] <= $x and $x <= $area['pos2'][0]) and ($area['pos1'][1] <= $y and $y <= $area['pos2'][1]) and ($area['pos1'][2] <= $z and $z <= $area['pos2'][2])){
						return false;
					}
				}
			}
		}
	}
	
	public function playerinteract($data){
		$username = $data['entity']->player->username;
		foreach($this->areas as $name => $area){
			if(($area['pvp'] == false) and ($area['owner'] != $username) and ($data['entity']->player->level->getName() == $area['level']) and !$this->api->ban->isOp($username)){
				$x = $data['targetentity']->x;
				$y = $data['targetentity']->y;
				$z = $data['targetentity']->z;
				if(($area['pos1'][0] <= $x and $x <= $area['pos2'][0]) and ($area['pos1'][1] <= $y and $y <= $area['pos2'][1]) and ($area['pos1'][2] <= $z and $z <= $area['pos2'][2])){
					$data["entity"]->player->sendChat("You can't hit anybody in this area");
					return false;
				}
			}
		}
		
	}
	
	public function command($cmd, $args, $issuer){
		if($issuer === 'console'){
			$output .= "Run this command ingame";
			return $output;
		}
		$username = $issuer->username;
		switch($args[0]){
			case 'pos1':	$x = round($issuer->entity->x - 0.5);
							$y = round($issuer->entity->y);
							$z = round($issuer->entity->z - 0.5);
							$this->pos1[$username] = array($x, $y, $z, $issuer->level->getName());
							$output .= "Defined location 1";
							break;
							
			case 'pos2':	$x = round($issuer->entity->x - 0.5);
							$y = round($issuer->entity->y);
							$z = round($issuer->entity->z - 0.5);
							$this->pos2[$username] = array($x, $y, $z, $issuer->level->getName());
							$output .= "Defined location 2";
							break;
							
			case 'create':	if(!isset($this->pos1[$username]) || !isset($this->pos2[$username])){
								$output .= "Make a selection first. Usage: /ag <pos1 | pos2>";
								break;
							}elseif($this->pos1[$username][3] !== $this->pos2[$username][3]){
								$output .= "The selection points exist on another world!";
								break;
							}elseif(!isset($args[1])){
								$output .= "Usage: /ag create <name>";
								break;
							}
							$namearea = $args[1];
							if(isset($this->areas[$name])){
								$output .= "Their already exist a area with that name";
								break;
							}
							$pos1 = $this->pos1[$username];
							$pos2 = $this->pos2[$username];
							$min[0] = min($pos1[0], $pos2[0]);
							$max[0] = max($pos1[0], $pos2[0]);
							$min[1] = min($pos1[1], $pos2[1]);
							$max[1] = max($pos1[1], $pos2[1]);
							$min[2] = min($pos1[2], $pos2[2]);
							$max[2]= max($pos1[2], $pos2[2]);
							$temp[0] = $this->getarea($min[0], $min[1], $min[2]);
							$temp[1] = $this->getarea($max[0], $max[1], $max[2]);
							if(($temp[0] != false) and ($temp[1] != false)){
								$output .= "Your area is inside another area";
								break;
							}
							$temp[3] = true;
							foreach($this->areas as $name => $area){
								if(($min[0] <= $area['pos1'][0] and $min[0] <= $area['pos2'][0]) and ($min[1] <= $area['pos1'][1] and $min[1] <= $area['pos2'][1]) and ($min[2] <= $area['pos1'][2] and $min[2] <= $area['pos2'][2])) $temp[3] = false;
								if(($max[0] <= $area['pos1'][0] and $max[0] <= $area['pos2'][0]) and ($max[1] <= $area['pos1'][1] and $max[1] <= $area['pos2'][1]) and ($max[2] <= $area['pos1'][2] and $max[2] <= $area['pos2'][2])) $temp[3] = false;
							}
							if($temp[3] === false){
								$output .= "Your area is inside another area";
								break;
							}
							if((($max[0] - $min[0]) * ($max[2] - $min[2])) > $this->config['Maxareasize'] and !$this->api->ban->isOp($username)){
								$output .= "Your area is to big, the max size is ".$this->config['Maxareasize'].' blocks';
								break;
							}
							$numareas = 0;
							foreach($this->areas as $name => $area){
								if($area['owner'] === $username) ++$numareas;
							}
							if(($numareas >= $this->config['Maxareas']) and !$this->api->ban->isOp($username)){
								$output .= 'You can only make '.$this->config['Maxareas'].' areas';
								break;
							}
							$this->areas[$namearea] = array(
								'pos1' => $min,
								'pos2' => $max,
								'level' => $pos1[3],
								'owner' => $username,
								'pvp' => $this->config['Defaults']['PvP'],
								'build' => $this->config['Defaults']['Build'],
								'chestacces' => $this->config['Defaults']['ChestAcces'],
								'entry' => $this->config['Defaults']['Entry'],
								'members' => array(),
								'greetingmsg' => "Welcome to ".$namearea." owned by ".$username,
								'farewellmsg' => "You left ".$namearea." owned by ".$username,
							);
							$file = json_encode($this->areas);
							file_put_contents($this->path . "areas.data", $file);
							$output .= "You protected this area";
							break;
							
			case 'remove':	$name = $this->getarea($issuer->entity->x, $issuer->entity->y, $issuer->entity->z, $username);
							if($name === false) return;
							unset($this->areas[$name]);
							$file = json_encode($this->areas);
							file_put_contents($this->path . "areas.data", $file);
							$output .= "area is removed";
							break;
							
			case 'flag':	$name = $this->getarea($issuer->entity->x, $issuer->entity->y, $issuer->entity->z, $username);
							if($name === false) return;
							if($args[1] === 'pvp'){
								if($this->areas[$name]['pvp'] === false){
									$this->areas[$name]['pvp'] = true;
									$output .= "You allowed pvp in your area";
								}else{
									$this->areas[$name]['pvp'] = false;
									$output .= "You disallowed pvp in your area";
								}
							}elseif($args[1] === 'build'){
								if($this->areas[$name]['build'] === false){
									$this->areas[$name]['build'] = true;
									$output .= "You allowed building in your area";
								}else{
									$this->areas[$name]['build'] = false;
									$output .= "You disallowed building in your area";
								}
							}elseif($args[1] === 'entry'){
								if($this->areas[$name]['entry'] === false){
									$this->areas[$name]['entry'] = true;
									$output .= "You allowed players to enter your area";
								}else{
									$this->areas[$name]['entry'] = false;
									$output .= "You disallowed players to enter your area";
								}
							}elseif($args[1] === 'chestacces'){
								if($this->areas[$name]['chestacces'] === false){
									$this->areas[$name]['chestacces'] = true;
									$output .= "You allowed players to open chests";
								}else{
									$this->areas[$name]['chestacces'] = false;
									$output .= "You disallowed players to open chests";
								}
							}elseif($args[1] === 'greeting'){
								$msg = implode(" ", $args);
								$msg = substr($msg, 14);
								$this->areas[$name]['greetingmsg'] = $msg;
								$output .= "You changed the greeting message";
							}elseif($args[1] === 'farewell'){
								$msg = implode(" ", $args);
								$msg = substr($msg, 14);
								$this->areas[$name]['farewellmsg'] = $msg;
								$output .= "You changed the farewell message";
							}else{
								$output .= 'Usage : /ag flag <pvp, build, entry, greeting, farewell, chestacces>';
								break;
							}
							$file = json_encode($this->areas);
							file_put_contents($this->path . "areas.data", $file);
							break;
							
			case 'rename':	$name = $this->getarea($issuer->entity->x, $issuer->entity->y, $issuer->entity->z, $username);
							if($name === false) return;
							if(!isset($args[1])){
								$output .= "Usage: /ag rename <name>";
								break;
							}
							$area = $this->areas[$name];
							unset($this->areas[$name]);
							$newname = $args[1];
							$this->areas[$newname] = $area;
							$file = json_encode($this->areas);
							file_put_contents($this->path . 'areas.data', $file);
							$output .= 'You renamed your area';
							break;
							
			case 'member':
							$name = $this->getarea($issuer->entity->x, $issuer->entity->y, $issuer->entity->z, $username);
							if($name === false) return;
							if($args[1] === 'list'){
								$output = "Members: " . implode(", ", $this->areas[$name]['members']);
								break;
							}elseif(!isset($args[2])){
								$output .= 'Usage: /ag member <add | remove | list> (player)';
								break;
							}elseif($args[1] === 'add'){
								array_push($this->areas[$name]['members'], $args[2]);
								$output .= $args[2].' is now a member';
							}elseif($args[1] === 'remove'){
								$key = array_search($args[2], $this->areas[$name]['members']);
								if($key === false){
									$output .= "Member doesn't exist";
									break;
								}else{
									$output .= $args[2].' is no longer a member';
									break;
								}
							}
							$file = json_encode($this->areas);
							file_put_contents($this->path . 'areas.data', $file);
							break;
							
			case 'help':	$issuer->sendChat('===[Areaguard Commands]===');
							$issuer->sendChat('info');
							$issuer->sendChat('pos1 | pos2');
							$issuer->sendChat('create <name>');
							$issuer->sendChat('remove');
							$issuer->sendChat('flag <pvp, build, entry, chestacces,');
							$issuer->sendChat('      greeting, farewell>');
							$issuer->sendChat('rename <newname>');
							$issuer->sendChat('member <add | remove> <player>');
							$issuer->sendChat('member <list>');
							$output .= '';
							break;
							
			case 'info':	$name = $this->getarea($issuer->entity->x, $issuer->entity->y, $issuer->entity->z);
							if($name === false){
								$output .= "You've to stand in a area";
								break;
							}
							$issuer->sendChat('===[area: '.$name.']===');
							$issuer->sendChat('Owner: '.$this->areas[$name]['owner']);
							$boolean = ($this->areas[$name]['build']) ? 'true' : 'false';
							$issuer->sendChat('Build: '.$boolean);
							$boolean = ($this->areas[$name]['pvp']) ? 'true' : 'false';
							$issuer->sendChat('PvP: '.$boolean);
							$boolean = ($this->areas[$name]['entry']) ? 'true' : 'false';
							$issuer->sendChat('Entry: '.$boolean);
							$boolean = ($this->areas[$name]['chestacces']) ? 'true' : 'false';
							$issuer->sendChat('ChestAcces: '.$boolean);
							return;
							
			case 'expand':	$name = $this->getarea($issuer->entity->x, $issuer->entity->y, $issuer->entity->z, $username);
							if($name === false) return;
							if(!isset($args[1]) or !isset($args[2])){
								$output .= 'Usage: /ag expand <up | down> <height>';
								break;
							}elseif(!is_numeric($args[2])){
								$output .= 'Usage: /ag expand <up | down> <height>';
								break;
							}
							$height = preg_replace("/[^0-9]/", "", $args[2]);
							if($args[1] === 'up'){
								$this->areas[$name]['pos2'][1] = $this->areas[$name]['pos2'][1] + $height;
								$file = json_encode($this->areas);
								file_put_contents($this->path . "areas.data", $file);
								$output .= 'You expanded the area '.$height.' blocks up';
							}elseif($args[1] === 'down'){
								$this->areas[$name]['pos1'][1] = $this->areas[$name]['pos1'][1] - $height;
								$file = json_encode($this->areas);
								file_put_contents($this->path . "areas.data", $file);
								$output .= 'You expanded the area '.$height.' blocks down';
							}else{
								$output .= 'Usage: /ag expand <up | down> <height>';
								break;
							}
							break;
							
			default:		$output .= 'AreaGuard v1.0 by Wiezz';
							break;
		}
		return $output;
	}
	
	public function getarea($x, $y, $z, $username = false){
		foreach($this->areas as $name => $area){
			if(($area['pos1'][0] <= $x and $x <= $area['pos2'][0]) and ($area['pos1'][1] <= $y and $y <= $area['pos2'][1]) and ($area['pos1'][2] <= $z and $z <= $area['pos2'][2])){
				if(($username != false) and ($this->areas[$name]['owner'] != $username) and !$this->api->ban->isOp($username)){
					$this->api->chat->sendTo(false, "You're not the owner of this area", $username);
					return false;
				}else{
					return $name;
				}
			}
		}
		if($username != false){
			$this->api->chat->sendTo(false, "You've to stand in a area", $username);
		}
		return false;
	}
	
	public function __destruct(){

	}
}