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

if (!class_exists('NetatmoCameraAPI')) {
	require_once __DIR__ . '/netatmoCameraApi.php';
}

class netatmoWelcome extends eqLogic {
	/*     * *************************Attributs****************************** */
	
	private static $_client = null;
	public static $_widgetPossibility = array('custom' => true);
	private $_netatmoCameraApi = null;
	
	/*     * ***********************Methode static*************************** */
	
	public static function cronDaily(){
		shell_exec("cd '__DIR__.'/../../data;  ls -1tr *.jpg | head -n -10 | xargs -d '\n' rm -f --");
	}
	
	public static function backup(){
		shell_exec("cd '__DIR__.'/../../data;  ls -1tr *.jpg | head -n -10 | xargs -d '\n' rm -f --");
	}
	
	public static function getClient() {
		if (self::$_client == null) {
			self::$_client = new netatmoApi(array(
				'client_id' => config::byKey('client_id', 'netatmoWelcome'),
				'client_secret' => config::byKey('client_secret', 'netatmoWelcome'),
				'username' => config::byKey('username', 'netatmoWelcome'),
				'password' => config::byKey('password', 'netatmoWelcome'),
				'scope' => 'read_camera access_camera read_presence access_presence',
			));
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
	
	public static function createCamera($_datas = null) {
		if(!class_exists('camera')){
			return;
		}
		if($_datas == null){
			$_datas =	self::getClient()->api('gethomedata');
		}
		foreach ($_datas['homes'] as $home) {
			$eqLogic = eqLogic::byLogicalId($home['id'], 'netatmoWelcome');
			foreach ($home['cameras'] as $camera) {
				log::add('netatmoWelcome','debug',json_encode($camera));
				$url_parse = parse_url($eqLogic->getCache('vpnUrl'.$camera['id']). '/live/snapshot_720.jpg');
				log::add('netatmoWelcome','debug','VPN URL : '.json_encode($url_parse));
				if ($url_parse['host'] == '') {
					continue;
				}
				log::add('netatmoWelcome','debug','Local : '.$camera['is_local']);
				$plugin = plugin::byId('camera');
				$camera_jeedom = eqLogic::byLogicalId($camera['id'], 'camera');
				if (!is_object($camera_jeedom)) {
					$camera_jeedom = new camera();
					$camera_jeedom->setIsEnable(1);
					$camera_jeedom->setIsVisible(1);
					$camera_jeedom->setName($camera['name']);
				}
				$camera_jeedom->setConfiguration('home_id',$home['id']);
				$camera_jeedom->setConfiguration('ip', $url_parse['host']);
				$camera_jeedom->setConfiguration('urlStream', $url_parse['path']);
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
				$camera_jeedom->save();
				
				$eqLogic = eqLogic::byLogicalId($home['id'], 'netatmoWelcome');
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
	
	public static function syncWithNetatmo($_datas = null) {
		if($_datas == null){
			$_datas =	self::getClient()->api('gethomedata');
		}
		log::add('netatmoWelcome', 'debug', json_encode($_datas));
		foreach ($_datas['homes'] as $home) {
			$eqLogic = eqLogic::byLogicalId($home['id'], 'netatmoWelcome');
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
			$list_person = array();
			foreach ($home['persons'] as $person) {
				if (!isset($person['pseudo']) || $person['pseudo'] == '') {
					continue;
				}
				$list_person[$person['id']] = $person['pseudo'];
				$cmd = $eqLogic->getCmd('info', 'isHere' . $person['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('isHere' . $person['id']);
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Présence', __FILE__) . ' ' . $person['pseudo']);
					$cmd->save();
				}
				$cmd = $eqLogic->getCmd('info', 'lastSeen' . $person['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('lastSeen' . $person['id']);
					$cmd->setType('info');
					$cmd->setSubType('string');
					$cmd->setName(__('Derniere fois', __FILE__) . ' ' . $person['pseudo']);
					$cmd->save();
				}
			}
			$eqLogic->setConfiguration('user_list', $list_person);
			$list_camera = array();
			foreach ($home['cameras'] as $camera) {
				$list_camera[$camera['id']] = $camera['name'];
				$cmd = $eqLogic->getCmd('info', 'state' . $camera['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('state' . $camera['id']);
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Status', __FILE__) . ' ' . $camera['name']);
					$cmd->save();
				}
				$cmd = $eqLogic->getCmd('info', 'stateSd' . $camera['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('stateSd' . $camera['id']);
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Status SD', __FILE__) . ' ' . $camera['name']);
					$cmd->save();
				}
				$cmd = $eqLogic->getCmd('info', 'stateAlim' . $camera['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('stateAlim' . $camera['id']);
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Status alim', __FILE__) . ' ' . $camera['name']);
					$cmd->save();
				}
				
				$cmd = $eqLogic->getCmd('action', 'monitoringOn' . $camera['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('monitoringOn' . $camera['id']);
					$cmd->setConfiguration('cameraId',$camera['id']);
					$cmd->setType('action');
					$cmd->setSubType('other');
					$cmd->setIsVisible(0);
					$cmd->setName(__('Activer surveillance', __FILE__) . ' ' . $camera['name']);
					$cmd->save();
				}
				
				$cmd = $eqLogic->getCmd('action', 'monitoringOff' . $camera['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('monitoringOff' . $camera['id']);
					$cmd->setConfiguration('cameraId',$camera['id']);
					$cmd->setType('action');
					$cmd->setSubType('other');
					$cmd->setIsVisible(0);
					$cmd->setName(__('Désactiver surveillance', __FILE__) . ' ' . $camera['name']);
					$cmd->save();
				}
			}
			$cmd = $eqLogic->getCmd('action', 'humanOutAlert');
			if (!is_object($cmd)) {
				$cmd = new netatmoWelcomeCmd();
			}
			$cmd->setEqLogic_id($eqLogic->getId());
			$cmd->setLogicalId('humanOutAlert');
			$cmd->setType('action');
			$cmd->setSubType('select');
			$cmd->setIsVisible(0);
			$cmd->setConfiguration('listValue','ignore|Ignorer;record|Enregistrement;record_and_notify|Enregistrement et notification');
			$cmd->setName(__('Alerte humain', __FILE__));
			$cmd->save();
			
			$cmd = $eqLogic->getCmd('info', 'humanOutAlertInfo');
			if (!is_object($cmd)) {
				$cmd = new netatmoWelcomeCmd();
			}
			$cmd->setEqLogic_id($eqLogic->getId());
			$cmd->setLogicalId('humanOutAlertInfo');
			$cmd->setType('info');
			$cmd->setSubType('string');
			$cmd->setIsVisible(0);
			$cmd->setName(__('Alerte humain status', __FILE__));
			$cmd->save();
			
			$cmd = $eqLogic->getCmd('action', 'animalOutAlert');
			if (!is_object($cmd)) {
				$cmd = new netatmoWelcomeCmd();
			}
			$cmd->setEqLogic_id($eqLogic->getId());
			$cmd->setLogicalId('animalOutAlert');
			$cmd->setType('action');
			$cmd->setSubType('select');
			$cmd->setIsVisible(0);
			$cmd->setConfiguration('listValue','ignore|Ignorer;record|Enregistrement;record_and_notify|Enregistrement et notification');
			$cmd->setName(__('Alerte animal', __FILE__));
			$cmd->save();
			
			$cmd = $eqLogic->getCmd('info', 'animalOutAlertInfo');
			if (!is_object($cmd)) {
				$cmd = new netatmoWelcomeCmd();
			}
			$cmd->setEqLogic_id($eqLogic->getId());
			$cmd->setLogicalId('animalOutAlertInfo');
			$cmd->setType('info');
			$cmd->setSubType('string');
			$cmd->setIsVisible(0);
			$cmd->setName(__('Alerte animal status', __FILE__));
			$cmd->save();
			
			$cmd = $eqLogic->getCmd('action', 'vehicleOutAlert');
			if (!is_object($cmd)) {
				$cmd = new netatmoWelcomeCmd();
			}
			$cmd->setEqLogic_id($eqLogic->getId());
			$cmd->setLogicalId('vehicleOutAlert');
			$cmd->setType('action');
			$cmd->setSubType('select');
			$cmd->setIsVisible(0);
			$cmd->setConfiguration('listValue','ignore|Ignorer;record|Enregistrement;record_and_notify|Enregistrement et notification');
			$cmd->setName(__('Alerte véhicule', __FILE__));
			$cmd->save();
			
			$cmd = $eqLogic->getCmd('info', 'vehicleOutAlertInfo');
			if (!is_object($cmd)) {
				$cmd = new netatmoWelcomeCmd();
			}
			$cmd->setEqLogic_id($eqLogic->getId());
			$cmd->setLogicalId('vehicleOutAlertInfo');
			$cmd->setType('info');
			$cmd->setSubType('string');
			$cmd->setIsVisible(0);
			$cmd->setName(__('Alerte véhicule status', __FILE__));
			$cmd->save();
			
			$cmd = $eqLogic->getCmd('action', 'otherOutAlert');
			if (!is_object($cmd)) {
				$cmd = new netatmoWelcomeCmd();
			}
			$cmd->setEqLogic_id($eqLogic->getId());
			$cmd->setLogicalId('otherOutAlert');
			$cmd->setType('action');
			$cmd->setSubType('select');
			$cmd->setIsVisible(0);
			$cmd->setConfiguration('listValue','ignore|Ignorer;record|Enregistrement;record_and_notify|Enregistrement et notification');
			$cmd->setName(__('Alerte autre', __FILE__));
			$cmd->save();
			
			$cmd = $eqLogic->getCmd('info', 'otherOutAlertInfo');
			if (!is_object($cmd)) {
				$cmd = new netatmoWelcomeCmd();
				$cmd->setEqLogic_id($eqLogic->getId());
				$cmd->setLogicalId('otherOutAlertInfo');
				$cmd->setType('info');
				$cmd->setSubType('string');
				$cmd->setIsVisible(0);
				$cmd->setName(__('Alerte autre status', __FILE__));
				$cmd->save();
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
		self::refresh_info($_datas);
		try {
			self::createCamera($_datas);
		} catch (Exception $e) {
			
		}
		self::getClient()->api('dropwebhook','POST',array('app_types' => 'jeedom'));
		self::getClient()->api('addwebhook','POST',array('url' => network::getNetworkAccess('external') . '/plugins/netatmoWelcome/core/php/jeeWelcome.php?apikey=' . jeedom::getApiKey('netatmoWelcome')));
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
			foreach ($_datas['homes'] as $home) {
				$eqLogic = eqLogic::byLogicalId($home['id'], 'netatmoWelcome');
				if (!is_object($eqLogic)) {
					continue;
				}
				$eqLogic->checkAndUpdateCmd('humanOutAlertInfo', $eqLogic->getNetatmoCameraAPI()->home['presence_record_humans']);
				$eqLogic->checkAndUpdateCmd('animalOutAlertInfo', $eqLogic->getNetatmoCameraAPI()->home['presence_record_vehicles']);
				$eqLogic->checkAndUpdateCmd('vehicleOutAlertInfo', $eqLogic->getNetatmoCameraAPI()->home['presence_record_animals']);
				$eqLogic->checkAndUpdateCmd('otherOutAlertInfo', $eqLogic->getNetatmoCameraAPI()->home['presence_record_movements']);
				
				foreach ($home['cameras'] as &$camera) {
					if(!isset($camera['vpn_url']) || $camera['vpn_url'] == ''){
						continue;
					}
					$url = $camera['vpn_url'];
					try {
						$request_http = new com_http($url . '/command/ping');
						$result = json_decode(trim($request_http->exec(5, 1)), true);
						log::add('netatmoWelcome','debug',json_encode($result));
						$url = $result['local_url'];
					} catch (Exception $e) {
						log::add('netatmoWelcome','debug','Local error : '.$e->getMessage());
					}
					$url = str_replace(',,','',$url);
					$eqLogic->setCache('vpnUrl'.$camera['id'],$url);
				}
				$cameras_jeedom = eqLogic::searchConfiguration('"home_id":"'.$home['id'].'"', 'camera');
				foreach ($home['persons'] as $person) {
					$eqLogic->checkAndUpdateCmd('isHere' . $person['id'], ($person['out_of_sight'] != 1));
					self::updateCameraInfo($cameras_jeedom,'isHere' . $person['id'], ($person['out_of_sight'] != 1));
					$eqLogic->checkAndUpdateCmd('lastSeen' . $person['id'], date('Y-m-d H:i:s', $person['last_seen']));
					self::updateCameraInfo($cameras_jeedom,'lastSeen' . $person['id'], $person['last_seen']);
				}
				foreach ($home['cameras'] as $camera) {
					$eqLogic->checkAndUpdateCmd('state' . $camera['id'], ($camera['status'] == 'on'));
					self::updateCameraInfo($cameras_jeedom,'state' . $camera['id'], ($camera['status'] == 'on'));
					$eqLogic->checkAndUpdateCmd('stateSd' . $camera['id'], ($camera['sd_status'] == 'on'));
					self::updateCameraInfo($cameras_jeedom,'stateSd' . $camera['id'], ($camera['sd_status'] == 'on'));
					$eqLogic->checkAndUpdateCmd('stateAlim' . $camera['id'], ($camera['alim_status'] == 'on'));
					self::updateCameraInfo($cameras_jeedom,'stateAlim' . $camera['id'], ($camera['alim_status'] == 'on'));
					if ($camera['type'] == 'NOC') {
						self::createCamera($_datas);
					}
				}
				$events = $home['events'];
				if ($events[0] != null && isset($events[0]['event_list'])) {
					$details = $events[0]['event_list'][0];
					$message = date('Y-m-d H:i:s', $details['time']) . ' - ' . $details['message'];
					$eqLogic->checkAndUpdateCmd('lastOneEvent', $message);
					self::updateCameraInfo($cameras_jeedom,'lastOneEvent', $message);
				}
				
				$message = '';
				foreach ($events as $event) {
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
	
	public function setMonitoring($_id,$_mode='on'){
		$url = $this->getCache('vpnUrl'.$_id).'/command/changestatus?status='.$_mode;
		$request_http = new com_http($url);
		$result = json_decode(trim($request_http->exec(5, 1)), true);
		log::add('netatmoWelcome','debug','Set monitoring mode : '.json_encode($result));
	}
	
	public function getNetatmoCameraAPI(){
		if($this->_netatmoCameraApi == null){
			$this->_netatmoCameraApi = new NetatmoCameraAPI(
				config::byKey('username', 'netatmoWelcome'),
				config::byKey('password', 'netatmoWelcome'),
				$this->getLogicalId(),
				$this->getCache('csrf',null),
				$this->getCache('csrfName',null),
				$this->getCache('token',null)
			);
			$this->setCache('csrf',$this->_netatmoCameraApi->csrf);
			$this->setCache('csrfName',$this->_netatmoCameraApi->csrfName);
			$this->setCache('token',$this->_netatmoCameraApi->token);
		}
		return $this->_netatmoCameraApi;
	}
	
}

class netatmoWelcomeCmd extends cmd {
	/*     * *************************Attributs****************************** */
	
	public static $_widgetPossibility = array('custom' => false);
	
	/*     * ***********************Methode static*************************** */
	
	/*     * *********************Methode d'instance************************* */
	
	public function execute($_options = array()) {
		$eqLogic = $this->getEqLogic();
		if(strpos($this->getLogicalId(),'monitoringOff') !== false){
			$eqLogic->setMonitoring($this->getConfiguration('cameraId'),'off');
		}else if(strpos($this->getLogicalId(),'monitoringOn') !== false){
			$eqLogic->setMonitoring($this->getConfiguration('cameraId'),'on');
		}else if($this->getLogicalId() == 'humanOutAlert'){
			$eqLogic->getNetatmoCameraAPI()->setOutAlert('presence_record_humans',$_options['select']);
		}else if($this->getLogicalId() == 'animalOutAlert'){
			$eqLogic->getNetatmoCameraAPI()->setOutAlert('presence_record_animals',$_options['select']);
		}else if($this->getLogicalId() == 'vehicleOutAlert'){
			$eqLogic->getNetatmoCameraAPI()->setOutAlert('presence_record_vehicles',$_options['select']);
		}else if($this->getLogicalId() == 'otherOutAlert'){
			$eqLogic->getNetatmoCameraAPI()->setOutAlert('presence_record_movements',$_options['select']);
		}
		netatmoWelcome::refresh_info();
	}
	
	/*     * **********************Getteur Setteur*************************** */
}

?>
