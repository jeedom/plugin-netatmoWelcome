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
if (!class_exists('NAWelcomeApiClient')) {
	require_once __DIR__ . '/../../3rdparty/Netatmo-API-PHP/Clients/NAWelcomeApiClient.php';
}

class netatmoWelcome extends eqLogic {
	/*     * *************************Attributs****************************** */
	
	private static $_client = null;
	public static $_widgetPossibility = array('custom' => true);
	
	/*     * ***********************Methode static*************************** */
	
	public static function cronDaily(){
		shell_exec("cd '__DIR__.'/../../data;  ls -1tr *.jpg | head -n -10 | xargs -d '\n' rm -f --");
	}
	
	public static function getClient($_scope = Netatmo\Common\NAScopes::SCOPE_READ_CAMERA, $_force = false) {
		if (self::$_client == null || $_force) {
			self::$_client = new NAWelcomeApiClient(array(
				'client_id' => config::byKey('client_id', 'netatmoWelcome'),
				'client_secret' => config::byKey('client_secret', 'netatmoWelcome'),
				'username' => config::byKey('username', 'netatmoWelcome'),
				'password' => config::byKey('password', 'netatmoWelcome'),
				'scope' => $_scope,
			));
			self::$_client->getAccessToken();
		}
		return self::$_client;
	}
	
	public static function updateCameraInfo($_cameras,$_cmd_logicalId,$_value){
		if(count($_cameras) == 0){
			return;
		}
		foreach ($_cameras as $camera) {
			$camera->checkAndUpdateCmd($_cmd_logicalId, $_value);
		}
	}
	
	public static function createCamera() {
		if(!class_exists('camera')){
			return;
		}
		$client = self::getClient(Netatmo\Common\NAScopes::SCOPE_READ_CAMERA . ' ' . Netatmo\Common\NAScopes::SCOPE_READ_PRESENCE . ' ' . Netatmo\Common\NAScopes::SCOPE_ACCESS_CAMERA . ' ' . Netatmo\Common\NAScopes::SCOPE_ACCESS_PRESENCE, true);
		$response = $client->getData(NULL, 1);
		$homes = $response->getData();
		foreach ($homes as $home) {
			$cameras = $home->getCameras();
			foreach ($cameras as $camera) {
				$camera_array = utils::o2a($camera);
				log::add('netatmoWelcome','debug',json_encode($camera_array));
				$url = $camera->getVpnUrl();
				if ($camera->isLocal()) {
					try {
						$request_http = new com_http($url . '/command/ping');
						$result = json_decode(trim($request_http->exec(5, 1)), true);
						$url = $result['local_url'];
					} catch (Exception $e) {
						log::add('netatmoWelcome','debug','Local error : '.$e->getMessage());
					}
				}
				$url .= '/live/snapshot_720.jpg';
				$url_parse = parse_url($url);
				if ($url_parse['host'] == "") {
					$url_parse = parse_url($camera->getVpnUrl() . '/live/snapshot_720.jpg');
				}
				$plugin = plugin::byId('camera');
				$camera_jeedom = eqLogic::byLogicalId($camera_array['id'], 'camera');
				if (!is_object($camera_jeedom)) {
					$camera_jeedom = new camera();
				}
				$camera_jeedom->setConfiguration('home_id',$home->getVar('id'));
				$camera_jeedom->setName($camera->getName());
				$camera_jeedom->setIsEnable(1);
				$camera_jeedom->setIsVisible(1);
				$camera_jeedom->setConfiguration('ip', $url_parse['host']);
				$camera_jeedom->setConfiguration('urlStream', $url_parse['path']);
				if ($camera_array['type'] == 'NOC') {
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
				$camera_jeedom->setLogicalId($camera_array['id']);
				$camera_jeedom->save();
				
				$eqLogic = eqLogic::byLogicalId($home->getVar('id'), 'netatmoWelcome');
				if(is_object($eqLogic)){
					foreach ($eqLogic->getCmd('info') as $cmdEqLogic) {
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
	
	public static function syncWithNetatmo() {
		$client = self::getClient('read_camera read_presence access_camera access_presence', true);
		$response = $client->getData(NULL, 1);
		$homes = $response->getData();
		log::add('netatmoWelcome', 'debug', print_r($homes, true));
		foreach ($homes as $home) {
			$eqLogic = eqLogic::byLogicalId($home->getVar('id'), 'netatmoWelcome');
			if (!is_object($eqLogic)) {
				$eqLogic = new netatmoWelcome();
				$eqLogic->setEqType_name('netatmoWelcome');
				$eqLogic->setIsEnable(1);
				$eqLogic->setName($home->getVar('name'));
				$eqLogic->setCategory('security', 1);
				$eqLogic->setIsVisible(1);
			}
			$eqLogic->setLogicalId($home->getVar('id'));
			$eqLogic->save();
			$list_person = array();
			$persons = $home->getPersons();
			foreach ($persons as $person) {
				$person_array = utils::o2a($person);
				if (!isset($person_array['pseudo']) || $person_array['pseudo'] == '') {
					continue;
				}
				$list_person[$person_array['id']] = $person_array['pseudo'];
				$cmd = $eqLogic->getCmd('info', 'isHere' . $person_array['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('isHere' . $person_array['id']);
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Présence', __FILE__) . ' ' . $person_array['pseudo']);
					$cmd->save();
				}
				$cmd = $eqLogic->getCmd('info', 'lastSeen' . $person_array['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('lastSeen' . $person_array['id']);
					$cmd->setType('info');
					$cmd->setSubType('string');
					$cmd->setName(__('Derniere fois', __FILE__) . ' ' . $person_array['pseudo']);
					$cmd->save();
				}
			}
			$eqLogic->setConfiguration('user_list', $list_person);
			$list_camera = array();
			$cameras = $home->getCameras();
			foreach ($cameras as $camera) {
				$camera_array = utils::o2a($camera);
				$list_camera[$camera_array['id']] = $camera_array['name'];
				$cmd = $eqLogic->getCmd('info', 'state' . $camera_array['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('state' . $camera_array['id']);
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Status', __FILE__) . ' ' . $camera_array['name']);
					$cmd->save();
				}
				$cmd = $eqLogic->getCmd('info', 'stateSd' . $camera_array['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('stateSd' . $camera_array['id']);
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Status SD', __FILE__) . ' ' . $camera_array['name']);
					$cmd->save();
				}
				$cmd = $eqLogic->getCmd('info', 'stateAlim' . $camera_array['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('stateAlim' . $camera_array['id']);
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Status alim', __FILE__) . ' ' . $camera_array['name']);
					$cmd->save();
				}
			}
			$eqLogic->setConfiguration('camera_list', $list_camera);
			$eqLogic->save();
			$cmd = $eqLogic->getCmd('info', 'lastEvent');
			if (!is_object($cmd)) {
				$cmd = new netatmoWelcomeCmd();
				$cmd->setEqLogic_id($eqLogic->getId());
				$cmd->setLogicalId('lastEvent');
				$cmd->setType('info');
				$cmd->setSubType('string');
				$cmd->setName(__('Derniers évènements', __FILE__));
				$cmd->save();
			}
			
			$cmd = $eqLogic->getCmd('info', 'lastOneEvent');
			if (!is_object($cmd)) {
				$cmd = new netatmoWelcomeCmd();
				$cmd->setEqLogic_id($eqLogic->getId());
				$cmd->setLogicalId('lastOneEvent');
				$cmd->setType('info');
				$cmd->setSubType('string');
				$cmd->setName(__('Evènement', __FILE__));
				$cmd->save();
			}
		}
		try {
			$client->dropSubscribeWebhook();
		} catch (Exception $e) {
			
		}
		$client->subscribeToWebhook(network::getNetworkAccess('external') . '/plugins/netatmoWelcome/core/php/jeeWelcome.php?apikey=' . jeedom::getApiKey('netatmoWelcome'),'jeedom');
		self::refresh_info();
		try {
			self::createCamera();
		} catch (Exception $e) {
			
		}
	}
	
	public static function cron15() {
		self::refresh_info();
	}
	
	public static function refresh_info() {
		try {
			try {
				$client = self::getClient('read_camera read_presence access_camera access_presence', true);
				if (config::byKey('numberFailed', 'netatmoWelcome', 0) > 0) {
					config::save('numberFailed', 0, 'netatmoWelcome');
				}
			} catch (NAClientException $e) {
				if (config::byKey('numberFailed', 'netatmoWelcome', 0) > 3) {
					log::add('netatmoWelcome', 'error', __('Erreur sur synchro netatmo welcome ', __FILE__) . ' (' . config::byKey('numberFailed', 'netatmoWelcome', 0) . ') ' . $e->getMessage());
				} else {
					config::save('numberFailed', config::byKey('numberFailed', 'netatmoWelcome', 0) + 1, 'netatmoWelcome');
				}
				return;
			}
			$response = $client->getData(NULL, 10);
			$homes = $response->getData();
			foreach ($homes as $home) {
				$eqLogic = eqLogic::byLogicalId($home->getVar('id'), 'netatmoWelcome');
				if (!is_object($eqLogic)) {
					continue;
				}
				$cameras_jeedom = eqLogic::searchConfiguration('"home_id":"'.$home->getVar('id').'"', 'camera');
				$persons = $home->getPersons();
				foreach ($persons as $person) {
					$person_array = utils::o2a($person);
					$here = ($person_array['out_of_sight'] == 1) ? 0 : 1;
					$eqLogic->checkAndUpdateCmd('isHere' . $person_array['id'], $here);
					self::updateCameraInfo($cameras_jeedom,'isHere' . $person_array['id'], $here);
					$eqLogic->checkAndUpdateCmd('lastSeen' . $person_array['id'], date('Y-m-d H:i:s', $person_array['last_seen']));
					self::updateCameraInfo($cameras_jeedom,'lastSeen' . $person_array['id'], $person_array['last_seen']);
				}
				$cameras = $home->getCameras();
				foreach ($cameras as $camera) {
					$camera_array = utils::o2a($camera);
					$state = ($camera_array['status'] == 'on') ? 1 : 0;
					$eqLogic->checkAndUpdateCmd('state' . $camera_array['id'], $state);
					self::updateCameraInfo($cameras_jeedom,'state' . $camera_array['id'], $state);
					$state = ($camera_array['sd_status'] == 'on') ? 1 : 0;
					$eqLogic->checkAndUpdateCmd('stateSd' . $camera_array['id'], $state);
					self::updateCameraInfo($cameras_jeedom,'stateSd' . $camera_array['id'], $state);
					$state = ($camera_array['alim_status'] == 'on') ? 1 : 0;
					$eqLogic->checkAndUpdateCmd('stateAlim' . $camera_array['id'], $state);
					self::updateCameraInfo($cameras_jeedom,'stateAlim' . $camera_array['id'], $state);
					if ($camera_array['type'] == 'NOC') {
						self::createCamera();
					}
				}
				$events = $home->getEvents();
				if($events[0] == null){
					$eventList == null;
				}else{
					$eventList = $events[0]->getVar('event_list');
				}
				if ($eventList != null) {
					foreach ($eventList as $event) {
						
						$message = '<span title="" data-tooltip-content="<img height=\'250\' class=\'img-responsive\' src=\''.self::downloadSnapshot($event['snapshot']['url']).'\'/>">'.date('Y-m-d H:i:s', $event['time']) . ' - ' . $event['message']. '</span>';
						$eqLogic->checkAndUpdateCmd('lastOneEvent', $message);
						self::updateCameraInfo($cameras_jeedom,'lastOneEvent', $message);
					}
				} else {
					if ($events[0] != null){
						$message = '<span title="" data-tooltip-content="<img height=\'250\' class=\'img-responsive\' src=\''.self::downloadSnapshot($event[0]->getSnapshot()).'\'/>">'.date('Y-m-d H:i:s', $events[0]->getTime()) . ' - ' . $events[0]->getMessage(). '</span>';
						$eqLogic->checkAndUpdateCmd('lastOneEvent', $message);
						self::updateCameraInfo($cameras_jeedom,'lastOneEvent', $message);
					}
				}
				$message = '';
				foreach ($events as $event) {
					if ($event->getVar('event_list') != null) {
						foreach ($event->getVar('event_list') as $eventList) {
							if(!isset($eventList['snapshot']['url'])){
								$eventList['snapshot']['url'] = '';
							}
							$message .= '<span title="" data-tooltip-content="<img height=\'250\' class=\'img-responsive\' src=\''.self::downloadSnapshot($eventList['snapshot']['url']).'\'/>">'.date('Y-m-d H:i:s', $eventList['time']) . ' - ' . $eventList['message'] . '</span><br/>';
						}
					} else {
						$message .= '<span title="" data-tooltip-content="<img height=\'250\' class=\'img-responsive\' src=\''.self::downloadSnapshot($event->getSnapshot()).'\'/>">'.date('Y-m-d H:i:s', $event->getTime()) . ' - ' . $event->getMessage() . '</span><br/>';
					}
				}
				$eqLogic->checkAndUpdateCmd('lastEvent', $message);
				self::updateCameraInfo($cameras_jeedom,'lastEvent', $message);
				$eqLogic->refreshWidget();
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
		if(file_exists(__DIR__.'/../../data/'.$filename)){
			return 'plugins/netatmoWelcome/data/'.$filename;
		}
		file_put_contents(__DIR__.'/../../data/'.$filename,file_get_contents($_snapshot));
		return 'plugins/netatmoWelcome/data/'.$filename;
	}
	
	/*     * *********************Methode d'instance************************* */
	
	public function postSave() {
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
	
	
}

class netatmoWelcomeCmd extends cmd {
	/*     * *************************Attributs****************************** */
	
	public static $_widgetPossibility = array('custom' => false);
	
	/*     * ***********************Methode static*************************** */
	
	/*     * *********************Methode d'instance************************* */
	
	public function execute($_options = array()) {
		if ($this->getLogicalId() == 'refresh') {
			netatmoWelcome::refresh_info();
		}
	}
	
	/*     * **********************Getteur Setteur*************************** */
}

?>
