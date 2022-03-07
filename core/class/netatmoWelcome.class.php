<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__ . '/../../../../core/php/core.inc.php';
if (!class_exists('netatmoApi')) {
	require_once __DIR__ . '/netatmoApi.class.php';
}

class netatmoWelcome extends eqLogic {
	/*     * *************************Attributs****************************** */
	
	private static $_client = null;
	public static $_encryptConfigKey = array('password','client_secret');
	
	/*     * ***********************Methode static*************************** */
	
	public static function cronDaily(){
		if(file_exist(__DIR__."/../../data")){
			shell_exec("cd ".__DIR__."/../../data;  ls -1tr *.jpg | head -n -35 | xargs -d '\n' rm -f --");
		}
	}
	
	public static function backup(){
		if(file_exist(__DIR__."/../../data")){
			shell_exec("cd ".__DIR__."/../../data;  ls -1tr *.jpg | head -n -35 | xargs -d '\n' rm -f --");
		}
	}
	
	public static function getClient() {
		if (self::$_client == null) {
			self::$_client = new netatmoApi(array(
				'client_id' => config::byKey('client_id', 'netatmoWelcome'),
				'client_secret' => config::byKey('client_secret', 'netatmoWelcome'),
				'username' => config::byKey('username', 'netatmoWelcome'),
				'password' => config::byKey('password', 'netatmoWelcome'),
				'scope' => 'read_camera access_camera read_presence access_presence read_smokedetector',
			));
		}
		return self::$_client;
	}
	
	public static function createCamera($_datas = null) {
		if(!class_exists('camera')){
			return;
		}
		if($_datas == null){
			$_datas =	self::getClient()->api('gethomedata');
		}
		foreach ($_datas['homes'] as $home) {
			foreach ($home['cameras'] as $camera) {
				$eqLogic = eqLogic::byLogicalId($camera['id'], 'netatmoWelcome');
				if(!is_object($eqLogic)){
					continue;
				}
				log::add('netatmoWelcome','debug',json_encode($camera));
				$url_parse = parse_url($eqLogic->getCache('vpnUrl'). '/live/snapshot_720.jpg');
				log::add('netatmoWelcome','debug','VPN URL : '.json_encode($url_parse));
				if (!isset($url_parse['host']) || $url_parse['host'] == '') {
					continue;
				}
				$plugin = plugin::byId('camera');
				$camera_jeedom = eqLogic::byLogicalId($camera['id'], 'camera');
				if (!is_object($camera_jeedom)) {
					$camera_jeedom = new camera();
					$camera_jeedom->setIsEnable(1);
					$camera_jeedom->setIsVisible(1);
					$camera_jeedom->setName($camera['name']);
				}
				$camera_jeedom->setConfiguration('home_id',$home['id']);
				if($eqLogic->getConfiguration('disableIpCamUpdate',0) == 0){
					$camera_jeedom->setConfiguration('ip', $url_parse['host']);
				}
				$camera_jeedom->setConfiguration('urlStream', $url_parse['path']);
				$camera_jeedom->setConfiguration('cameraStreamAccessUrl', 'http://#ip#'.str_replace('snapshot_720.jpg','index.m3u8',$url_parse['path']));
				if ($camera['type'] == 'NOC') {
					$camera_jeedom->setConfiguration('device', 'presence');
				} else {
					$camera_jeedom->setConfiguration('device', 'welcome');
				}
				$camera_jeedom->setEqType_name('camera');
				$camera_jeedom->setConfiguration('protocole', $url_parse['scheme']);
				if ($url_parse['scheme'] == 'https') {
					$camera_jeedom->setConfiguration('port', 443);
				} else {
					$camera_jeedom->setConfiguration('port', 80);
				}
				$camera_jeedom->setLogicalId($camera['id']);
				$camera_jeedom->save(true);
				if(is_object($eqLogic)){
					foreach ($eqLogic->getCmd('info') as $cmdEqLogic) {
						if(!in_array($cmdEqLogic->getLogicalId(),array('lastOneEvent','lastEvents'))){
							continue;
						}
						$cmd = $camera_jeedom->getCmd('info', $cmdEqLogic->getLogicalId());
						if (!is_object($cmd)) {
							$cmd = new CameraCmd();
							$cmd->setEqLogic_id($camera_jeedom->getId());
							$cmd->setLogicalId($cmdEqLogic->getLogicalId());
							$cmd->setType('info');
							$cmd->setSubType($cmdEqLogic->getSubType());
							$cmd->setName($cmdEqLogic->getName());
							$cmd->setIsVisible(0);
						}
						$cmd->save();
					}
				}
			}
		}
	}
	
	public static function getFromThermostat() {
		$client_id = config::byKey('client_id', 'netatmoThermostat');
		$client_secret = config::byKey('client_secret', 'netatmoThermostat');
		$username = config::byKey('username', 'netatmoThermostat');
		$password = config::byKey('password', 'netatmoThermostat');
		return (array($client_id, $client_secret, $username, $password));
	}
	
	public static function getFromWeather() {
		$client_id = config::byKey('client_id', 'netatmoWeather');
		$client_secret = config::byKey('client_secret', 'netatmoWeather');
		$username = config::byKey('username', 'netatmoWeather');
		$password = config::byKey('password', 'netatmoWeather');
		return (array($client_id, $client_secret, $username, $password));
	}
	
	public static function syncWithNetatmo($_datas = null) {
		if($_datas == null){
			$_datas =	self::getClient()->api('gethomedata');
		}
		log::add('netatmoWelcome', 'debug', json_encode($_datas));
		foreach ($_datas['homes'] as &$home) {
			$eqLogic = eqLogic::byLogicalId($home['id'], 'netatmoWelcome');
			if(!isset($home['name']) || trim($home['name']) == ''){
				$home['name'] = $home['id'];
			}
			if (!is_object($eqLogic)) {
				$eqLogic = new netatmoWelcome();
				$eqLogic->setEqType_name('netatmoWelcome');
				$eqLogic->setIsEnable(1);
				$eqLogic->setName($home['name']);
				$eqLogic->setCategory('security', 1);
				$eqLogic->setIsVisible(1);
			}
			$eqLogic->setLogicalId($home['id']);
			$eqLogic->setConfiguration('homeName',$home['name']);
			$eqLogic->save();
			foreach ($home['persons'] as $person) {
				if (!isset($person['pseudo']) || $person['pseudo'] == '') {
					continue;
				}
				$cmd = $eqLogic->getCmd('info', 'isHere' . $person['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('isHere' . $person['id']);
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(substr(__('Présence', __FILE__) . ' ' . $person['pseudo'].' - '.$person['id'],0,44));
					$cmd->save();
				}
				$cmd = $eqLogic->getCmd('info', 'lastSeen' . $person['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('lastSeen' . $person['id']);
					$cmd->setType('info');
					$cmd->setSubType('string');
					$cmd->setName(substr(__('Derniere fois', __FILE__) . ' ' . $person['pseudo'].' - '.$person['id'],0,44));
					$cmd->save();
				}
			}
			foreach ($home['cameras'] as &$camera) {
				$eqLogic = eqLogic::byLogicalId($camera['id'], 'netatmoWelcome');
				if(!isset($camera['name']) || trim($camera['name']) == ''){
					$camera['name'] = $camera['id'];
				}
				if (!is_object($eqLogic)) {
					$eqLogic = new netatmoWelcome();
					$eqLogic->setEqType_name('netatmoWelcome');
					$eqLogic->setIsEnable(1);
					$eqLogic->setName($camera['name']);
					$eqLogic->setCategory('security', 1);
					$eqLogic->setIsVisible(1);
				}
				$eqLogic->setConfiguration('type', $camera['type']);
				$eqLogic->setLogicalId($camera['id']);
				$eqLogic->setConfiguration('homeId',$home['id']);
				$eqLogic->setConfiguration('homeName',$home['name']);
				$eqLogic->save();
				if ($camera['type'] == 'NOC') {
					$cmd = $eqLogic->getCmd('action', 'lighton');
					if (!is_object($cmd)) {
						$cmd = new netatmoWelcomeCmd();
						$cmd->setEqLogic_id($eqLogic->getId());
						$cmd->setLogicalId('lighton');
						$cmd->setType('action');
						$cmd->setSubType('other');
						$cmd->setName(__('Lumière ON', __FILE__));
						$cmd->setConfiguration('mode','on');
						$cmd->save();
					}
					$cmd = $eqLogic->getCmd('action', 'lightoff');
					if (!is_object($cmd)) {
						$cmd = new netatmoWelcomeCmd();
						$cmd->setEqLogic_id($eqLogic->getId());
						$cmd->setLogicalId('lightoff');
						$cmd->setType('action');
						$cmd->setSubType('other');
						$cmd->setName(__('Lumière OFF', __FILE__));
						$cmd->setConfiguration('mode','off');
						$cmd->save();
					}
					$cmd = $eqLogic->getCmd('action', 'lightauto');
					if (!is_object($cmd)) {
						$cmd = new netatmoWelcomeCmd();
						$cmd->setEqLogic_id($eqLogic->getId());
						$cmd->setLogicalId('lightauto');
						$cmd->setType('action');
						$cmd->setSubType('other');
						$cmd->setName(__('Lumière AUTO', __FILE__));
						$cmd->setConfiguration('mode','auto');
						$cmd->save();
					}
					$cmd = $eqLogic->getCmd('action', 'lightintensity');
					if (!is_object($cmd)) {
						$cmd = new netatmoWelcomeCmd();
						$cmd->setEqLogic_id($eqLogic->getId());
						$cmd->setLogicalId('lightintensity');
						$cmd->setType('action');
						$cmd->setSubType('slider');
						$cmd->setName(__('Lumière Variation', __FILE__));
						$cmd->setConfiguration('action','on');
						$cmd->save();
					}
				}
				$cmd = $eqLogic->getCmd('info', 'state');
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('state');
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Status', __FILE__));
					$cmd->save();
				}
				$cmd = $eqLogic->getCmd('info', 'stateSd');
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('stateSd');
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Status SD', __FILE__));
					$cmd->save();
				}
				$cmd = $eqLogic->getCmd('info', 'stateAlim' );
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('stateAlim');
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Status alim', __FILE__));
					$cmd->save();
				}
				$cmd = $eqLogic->getCmd('action', 'monitoringOn');
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('monitoringOn');
					$cmd->setType('action');
					$cmd->setSubType('other');
					$cmd->setIsVisible(0);
					$cmd->setName(__('Activer surveillance', __FILE__));
					$cmd->save();
				}
				$cmd = $eqLogic->getCmd('action', 'monitoringOff');
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('monitoringOff');
					$cmd->setType('action');
					$cmd->setSubType('other');
					$cmd->setIsVisible(0);
					$cmd->setName(__('Désactiver surveillance', __FILE__));
					$cmd->save();
				}
				if(isset($camera['modules'])){
					foreach ($camera['modules'] as &$module) {
						$eqLogic = eqLogic::byLogicalId($module['id'], 'netatmoWelcome');
						if(!isset($module['name']) || trim($module['name']) == ''){
							$module['name'] = $module['id'];
						}
						if (!is_object($eqLogic)) {
							$eqLogic = new netatmoWelcome();
							$eqLogic->setEqType_name('netatmoWelcome');
							$eqLogic->setIsEnable(1);
							$eqLogic->setName($module['name']);
							$eqLogic->setCategory('security', 1);
							$eqLogic->setIsVisible(1);
						}
						$eqLogic->setConfiguration('type', $module['type']);
						$eqLogic->setLogicalId($module['id']);
						$eqLogic->setConfiguration('homeId',$home['id']);
						$eqLogic->setConfiguration('homeName',$home['name']);
						$eqLogic->save();
						if($module['type'] == 'NACamDoorTag'){
							$cmd = $eqLogic->getCmd('info', 'state');
							if (!is_object($cmd)) {
								$cmd = new netatmoWelcomeCmd();
								$cmd->setEqLogic_id($eqLogic->getId());
								$cmd->setLogicalId('state');
								$cmd->setType('info');
								$cmd->setSubType('binary');
								$cmd->setIsVisible(0);
								$cmd->setName(__('Etat', __FILE__));
								$cmd->save();
							}
						}else if($module['type'] == 'NIS'){
							$cmd = $eqLogic->getCmd('info', 'state');
							if (!is_object($cmd)) {
								$cmd = new netatmoWelcomeCmd();
								$cmd->setEqLogic_id($eqLogic->getId());
								$cmd->setLogicalId('state');
								$cmd->setType('info');
								$cmd->setSubType('string');
								$cmd->setIsVisible(0);
								$cmd->setName(__('Etat', __FILE__));
								$cmd->save();
							}
							$cmd = $eqLogic->getCmd('info', 'monitoring');
							if (!is_object($cmd)) {
								$cmd = new netatmoWelcomeCmd();
								$cmd->setEqLogic_id($eqLogic->getId());
								$cmd->setLogicalId('monitoring');
								$cmd->setType('info');
								$cmd->setSubType('string');
								$cmd->setIsVisible(0);
								$cmd->setName(__('Surveillance', __FILE__));
								$cmd->save();
							}
							$cmd = $eqLogic->getCmd('info', 'alim');
							if (!is_object($cmd)) {
								$cmd = new netatmoWelcomeCmd();
								$cmd->setEqLogic_id($eqLogic->getId());
								$cmd->setLogicalId('alim');
								$cmd->setType('info');
								$cmd->setSubType('string');
								$cmd->setIsVisible(0);
								$cmd->setName(__('Alimentation', __FILE__));
								$cmd->save();
							}
						}
					}
				}
			}
			foreach ($home['smokedetectors'] as &$smokedetectors) {
				$eqLogic = eqLogic::byLogicalId($smokedetectors['id'], 'netatmoWelcome');
				if(!isset($smokedetectors['name']) || trim($smokedetectors['name']) == ''){
					$smokedetectors['name'] = $smokedetectors['id'];
				}
				if (!is_object($eqLogic)) {
					$eqLogic = new netatmoWelcome();
					$eqLogic->setEqType_name('netatmoWelcome');
					$eqLogic->setIsEnable(1);
					$eqLogic->setName($smokedetectors['name']);
					$eqLogic->setCategory('security', 1);
					$eqLogic->setIsVisible(1);
				}
				$eqLogic->setConfiguration('type', $smokedetectors['type']);
				$eqLogic->setLogicalId($smokedetectors['id']);
				$eqLogic->setConfiguration('homeId',$home['id']);
				$eqLogic->setConfiguration('homeName',$home['name']);
				$eqLogic->save();
			}
		}
		self::refresh_info($_datas);
		try {
			self::getClient()->api('dropwebhook','POST',array('app_types' => 'jeedom'));
		} catch (\Exception $e) {
			
		}
		try {
			self::getClient()->api('addwebhook','POST',array('url' => network::getNetworkAccess('external') . '/plugins/netatmoWelcome/core/php/jeeWelcome.php?apikey=' . jeedom::getApiKey('netatmoWelcome')));
		} catch (\Exception $e) {
			
		}
	}
	
	public static function cron15() {
		self::refresh_info();
	}
	
	public static function refresh_info($_datas = null) {
		try{
			if($_datas == null){
				try {
					$_datas =	self::getClient()->api('gethomedata');
					if (config::byKey('numberFailed', 'netatmoWelcome', 0) > 0) {
						config::save('numberFailed', 0, 'netatmoWelcome');
					}
				} catch (NAClientException $e) {
					if (config::byKey('numberFailed', 'netatmoWelcome', 0) > 3) {
						log::add('netatmoWelcome', 'error', __('Erreur sur synchro netatmo welcome ', __FILE__) . ' (' . config::byKey('numberFailed', 'netatmoWelcome', 0) . ') ' . $e->getMessage());
						return;
					}
					config::save('numberFailed', config::byKey('numberFailed', 'netatmoWelcome', 0) + 1, 'netatmoWelcome');
					return;
				}
			}
			try {
				self::createCamera($_datas);
			} catch (\Exception $e) {
				
			}
			foreach ($_datas['homes'] as $home) {
				$eqLogic = eqLogic::byLogicalId($home['id'], 'netatmoWelcome');
				if (!is_object($eqLogic)) {
					continue;
				}
				foreach ($home['persons'] as $person) {
					$eqLogic->checkAndUpdateCmd('isHere' . $person['id'], ($person['out_of_sight'] != 1));
					$eqLogic->checkAndUpdateCmd('lastSeen' . $person['id'], date('Y-m-d H:i:s', $person['last_seen']));
				}
				$events = $home['events'];
				if ($events[0] != null && isset($events[0]['event_list'])) {
					$details = $events[0]['event_list'][0];
					$message = date('Y-m-d H:i:s', $details['time']) . ' - ' . $details['message'];
					$eqLogic->checkAndUpdateCmd('lastOneEvent', $message);
				}
				$message = '';
				$eventsByEqLogic = array();
				foreach ($events as $event) {
					if(isset($event['module_id'])){
						$eventsByEqLogic[$event['module_id']][] = $event;
					}else{
						$eventsByEqLogic[$event['device_id']][] = $event;
					}
					if (!isset($event['event_list']) || !isset($event['event_list'][0])) {
						continue;
					}
					$details = $event['event_list'][0];
					if(!isset($details['snapshot']['url'])){
						$details['snapshot']['url'] = '';
					}
					$message .= '<span title="" data-tooltip-content="<img height=\'500\' class=\'img-responsive\' src=\''.self::downloadSnapshot($details['snapshot']['url']).'\'/>">'.date('Y-m-d H:i:s', $details['time']) . ' - ' . $details['message'] . '</span><br/>';
				}
				$eqLogic->checkAndUpdateCmd('lastEvent', $message);
				
				foreach ($eventsByEqLogic as $id => $events) {
					$eqLogic = eqLogic::byLogicalId($id, 'netatmoWelcome');
					if(!is_object($eqLogic)){
						continue;
					}
					$camera_jeedom = eqLogic::byLogicalId($id, 'camera');
					if(isset($events[0]['message'])){
						$eqLogic->checkAndUpdateCmd('lastOneEvent',$events[0]['message']);
					}else if ($events[0] != null && isset($events[0]['event_list'])) {
						$details = $events[0]['event_list'][0];
						$message = date('Y-m-d H:i:s', $details['time']) . ' - ' . $details['message'];
						$eqLogic->checkAndUpdateCmd('lastOneEvent', $message);
						if(is_object($camera_jeedom)){
							$camera_jeedom->checkAndUpdateCmd('lastOneEvent', $message);
						}
					}
					$message = '';
					foreach ($events as $event) {
						if(isset($event['message'])){
							$message .= $event['message'].'<br/>';
							continue;
						}
						if (!isset($event['event_list']) || !isset($event['event_list'][0])) {
							continue;
						}
						$details = $event['event_list'][0];
						if(!isset($details['snapshot']['url'])){
							$details['snapshot']['url'] = '';
						}
						$message .= '<span title="" data-tooltip-content="<img height=\'500\' class=\'img-responsive\' src=\''.self::downloadSnapshot($details['snapshot']['url']).'\'/>">'.date('Y-m-d H:i:s', $details['time']) . ' - ' . $details['message'] . '</span><br/>';
					}
					if($message != ''){
						$eqLogic->checkAndUpdateCmd('lastEvent',$message);
						if(is_object($camera_jeedom)){
							$camera_jeedom->checkAndUpdateCmd('lastEvent', $message);
						}
					}
				}
				foreach ($home['cameras'] as &$camera) {
					if(!isset($camera['vpn_url']) || $camera['vpn_url'] == ''){
						continue;
					}
					$eqLogic = eqLogic::byLogicalId($camera['id'], 'netatmoWelcome');
					if (!is_object($eqLogic)) {
						continue;
					}
					$url = $camera['vpn_url'];
					try {
						$request_http = new com_http($camera['vpn_url'] . '/command/ping');
						$result = json_decode(trim($request_http->exec(5, 1)), true);
						log::add('netatmoWelcome','debug',json_encode($result));
						$url = $result['local_url'];
					} catch (Exception $e) {
						log::add('netatmoWelcome','debug','Local error : '.$e->getMessage());
					}
					$eqLogic->setCache('vpnUrl',str_replace(',,','',$url));
					$eqLogic->checkAndUpdateCmd('state', ($camera['status'] == 'on'));
					$eqLogic->checkAndUpdateCmd('stateSd', ($camera['sd_status'] == 'on'));
					$eqLogic->checkAndUpdateCmd('stateAlim', ($camera['alim_status'] == 'on'));
				}
				foreach ($home['cameras'] as &$camera) {
					if(isset($camera['modules'])){
						foreach ($camera['modules'] as $module) {
							$eqLogic = eqLogic::byLogicalId($module['id'], 'netatmoWelcome');
							if (!is_object($eqLogic)) {
								continue;
							}
							if($module['type'] == 'NACamDoorTag'){
								$eqLogic->checkAndUpdateCmd('state', ($module['status'] == 'open'));
							}else if($module['type'] == 'NIS'){
								$eqLogic->checkAndUpdateCmd('state', $module['status']);
								$eqLogic->checkAndUpdateCmd('alim', $module['alim_source']);
								$eqLogic->checkAndUpdateCmd('monitoring', $module['monitoring']);
							}
							if(isset($module['battery_percent'])){
								$eqLogic->batteryStatus($module['battery_percent']);
							}
						}
					}
				}
			}
		} catch (Exception $e) {
			
		}
	}
	
	
	public static function downloadSnapshot($_snapshot){
		if($_snapshot == ''){
			return 'core/img/no_image.gif';
		}
		if(!file_exists(__DIR__.'/../../data')){
			mkdir(__DIR__.'/../../data');
		}
		$parts  = parse_url($_snapshot);
		$filename = basename($parts['path']).'.jpg';
		if($filename == 'getcamerapicture'){
			return 'core/img/no_image.gif';
		}
		if(!file_exists(__DIR__.'/../../data/'.$filename)){
			file_put_contents(__DIR__.'/../../data/'.$filename,file_get_contents($_snapshot));
		}
		return 'plugins/netatmoWelcome/data/'.$filename;
	}
	
	/*     * *********************Methode d'instance************************* */
	
	public function postSave() {
		$cmd = $this->getCmd('info', 'lastEvent');
		if (!is_object($cmd)) {
			$cmd = new netatmoWelcomeCmd();
			$cmd->setEqLogic_id($this->getId());
			$cmd->setLogicalId('lastEvent');
			$cmd->setType('info');
			$cmd->setSubType('string');
			$cmd->setName(__('Derniers évènements', __FILE__));
			$cmd->save();
		}
		
		$cmd = $this->getCmd('info', 'lastOneEvent');
		if (!is_object($cmd)) {
			$cmd = new netatmoWelcomeCmd();
			$cmd->setEqLogic_id($this->getId());
			$cmd->setLogicalId('lastOneEvent');
			$cmd->setType('info');
			$cmd->setSubType('string');
			$cmd->setName(__('Evènement', __FILE__));
			$cmd->save();
		}
		$refresh = $this->getCmd(null, 'refresh');
		if (!is_object($refresh)) {
			$refresh = new netatmoWelcomeCmd();
			$refresh->setName(__('Rafraichir', __FILE__));
		}
		$refresh->setEqLogic_id($this->getId());
		$refresh->setLogicalId('refresh');
		$refresh->setType('action');
		$refresh->setSubType('other');
		$refresh->save();
		$this->refreshWidget();
	}
	
	public function setMonitoring($_id,$_mode='on'){
		$url = $this->getCache('vpnUrl').'/command/changestatus?status='.$_mode;
		$request_http = new com_http($url);
		$result = json_decode(trim($request_http->exec(5, 1)), true);
		log::add('netatmoWelcome','debug','Set monitoring mode : '.json_encode($result));
	}
	
	public function getImage() {
		return 'plugins/netatmoWelcome/core/img/' . $this->getConfiguration('type') . '.jpg';
	}
	
}

class netatmoWelcomeCmd extends cmd {
	/*     * *************************Attributs****************************** */
	
	/*     * ***********************Methode static*************************** */
	
	/*     * *********************Methode d'instance************************* */
	
	public function execute($_options = array()) {
		$eqLogic = $this->getEqLogic();
		if(strpos($this->getLogicalId(),'monitoringOff') !== false){
			$eqLogic->setMonitoring($this->getConfiguration('cameraId'),'off');
		}else if(strpos($this->getLogicalId(),'monitoringOn') !== false){
			$eqLogic->setMonitoring($this->getConfiguration('cameraId'),'on');
		}else if(strpos($this->getLogicalId(),'light') !== false){
			$vpn = $eqLogic->getCache('vpnUrl');
			$command = '/command/floodlight_set_config?config=';
			if($this->getSubType() == 'slider'){
				$config = '{"mode":"on","intensity":"'.$_options['slider'].'"}';
			}else{
				if($this->getConfiguration('mode')=='on'){
					$config = '{"mode":"on","intensity":"100"}';
				}else if($this->getConfiguration('mode')=='auto'){
					$config = '{"mode":"auto"}';
				}else{
					$config = '{"mode":"off","intensity":"0"}';
				}
			}
			$url = $vpn.$command.urlencode($config);
			try {
				$request_http = new com_http($url);
				$result = json_decode(trim($request_http->exec(5, 1)), true);
				log::add('netatmoWelcome','debug','Set light : '.json_encode($result));
			} catch (Exception $e) {
			}
		}
		netatmoWelcome::refresh_info();
	}
	
	/*     * **********************Getteur Setteur*************************** */
}

?>
