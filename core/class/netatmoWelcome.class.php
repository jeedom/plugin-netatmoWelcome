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
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
if (!class_exists('NAWelcomeApiClient')) {
	require_once dirname(__FILE__) . '/../../3rdparty/Netatmo-API-PHP/Clients/NAWelcomeApiClient.php';
}

class netatmoWelcome extends eqLogic {
	/*     * *************************Attributs****************************** */

	private static $_client = null;
	public static $_widgetPossibility = array('custom' => true);

	/*     * ***********************Methode static*************************** */

	public function getClient($_scope = NAScopes::SCOPE_READ_CAMERA, $_force = false) {
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

	public function createCamera() {
		$client = self::getClient(NAScopes::SCOPE_READ_CAMERA . ' ' . NAScopes::SCOPE_ACCESS_CAMERA, true);
		$response = $client->getData(NULL, 1);
		$homes = $response->getData();
		foreach ($homes as $home) {
			$cameras = $home->getCameras();
			foreach ($cameras as $camera) {
				$camera_array = utils::o2a($camera);
				$camera_array = $camera_array['object'];
				$url = $camera->getVpnUrl();
				if ($camera->isLocal()) {
					try {
						$request_http = new com_http($url . '/command/ping');
						$result = json_decode(trim($request_http->exec(1, 2)), true);
						$url = $result['local_url'];
					} catch (Exception $e) {

					}
				}
				$url .= '/live/snapshot_720.jpg';
				$url_parse = parse_url($url);
				$plugin = plugin::byId('camera');
				$camera_jeedom = eqLogic::byLogicalId($camera_array['id'], 'camera');
				if (!is_object($camera_jeedom)) {
					$camera_jeedom = new camera();
					$camera_jeedom->setDisplay('height', '240px');
					$camera_jeedom->setDisplay('width', '320px');
				}
				$camera_jeedom->setName($camera->getName());
				$camera_jeedom->setIsEnable(1);
				$camera_jeedom->setIsVisible(1);
				$camera_jeedom->setConfiguration('ip', $url_parse['host']);
				$camera_jeedom->setConfiguration('urlStream', $url_parse['path']);
				$camera_jeedom->setEqType_name('camera');
				$camera_jeedom->setConfiguration('protocole', $url_parse['scheme']);
				if ($url_parse['scheme'] == 'https') {
					$camera_jeedom->setConfiguration('port', 443);
				} else {
					$camera_jeedom->setConfiguration('port', 80);
				}
				$camera_jeedom->setLogicalId($camera_array['id']);
				$camera_jeedom->save();
			}
		}
	}

	public function syncWithNetatmo() {
		$client = self::getClient();
		$response = $client->getData(NULL, 1);
		$homes = $response->getData();
		foreach ($homes as $home) {
			$eqLogic = eqLogic::byLogicalId($home->getVar('id'), 'netatmoWelcome');
			if (!is_object($eqLogic)) {
				$eqLogic = new netatmoWelcome();
				$eqLogic->setEqType_name('netatmoWelcome');
				$eqLogic->setIsEnable(1);
				$eqLogic->setName($home->getVar('name'));
				$eqLogic->setLogicalId($home->getVar('id'));
				$eqLogic->setCategory('security', 1);
				$eqLogic->setIsVisible(1);
				$eqLogic->save();
			}
			$list_person = array();
			$persons = $home->getPersons();
			foreach ($persons as $person) {
				$person_array = utils::o2a($person);
				$person_array = $person_array['object'];
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
				$camera_array = $camera_array['object'];
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
				$cmd = $eqLogic->getCmd('info', 'movement' . $camera_array['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('movement' . $camera_array['id']);
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Mouvement', __FILE__) . ' ' . $camera_array['name']);
					$cmd->setConfiguration('returnStateTime', 1);
					$cmd->setConfiguration('returnStateValue', 0);
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
			$client->dropWebhook();
		} catch (Exception $e) {

		}
		$client->subscribeToWebhook(network::getNetworkAccess('external') . '/plugins/netatmoWelcome/core/php/jeeWelcome.php?apikey=' . config::byKey('api'));
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
				$client = self::getClient();
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
				$persons = $home->getPersons();
				foreach ($persons as $person) {
					$person_array = utils::o2a($person);
					$person_array = $person_array['object'];
					$cmd = $eqLogic->getCmd('info', 'isHere' . $person_array['id']);
					$here = ($person_array['out_of_sight'] == 1) ? 0 : 1;
					if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue($here)) {
						$cmd->event($here);
					}
					$cmd = $eqLogic->getCmd('info', 'lastSeen' . $person_array['id']);
					if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue(date('Y-m-d H:i:s', $person_array['last_seen']))) {
						$cmd->event(date('Y-m-d H:i:s', $person_array['last_seen']));
					}
				}
				$cameras = $home->getCameras();
				foreach ($cameras as $camera) {
					$camera_array = utils::o2a($camera);
					$camera_array = $camera_array['object'];
					$state = ($camera_array['status'] == 'on') ? 1 : 0;
					$cmd = $eqLogic->getCmd('info', 'state' . $camera_array['id']);
					if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue($state)) {
						$cmd->event($state);
					}
					$state = ($camera_array['sd_status'] == 'on') ? 1 : 0;
					$cmd = $eqLogic->getCmd('info', 'stateSd' . $camera_array['id']);
					if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue($state)) {
						$cmd->event($state);
					}
					$state = ($camera_array['alim_status'] == 'on') ? 1 : 0;
					$cmd = $eqLogic->getCmd('info', 'stateAlim' . $camera_array['id']);
					if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue($state)) {
						$cmd->event($state);
					}
				}
				$events = $home->getEvents();

				if ($events[0]->getTime() > (strtotime('now') - 60) && $events[0]->getEventType() == 'movement') {
					$cmd = $eqLogic->getCmd('info', 'movement' . $events[0]->getCameraId());
					if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue(1)) {
						$cmd->event(1);
					}
				}

				$message = date('Y-m-d H:i:s', $events[0]->getTime()) . ' - ' . $events[0]->getMessage();
				$cmd = $eqLogic->getCmd('info', 'lastOneEvent');
				if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue($message)) {
					$cmd->event($message);
				}
				$message = '';
				foreach ($events as $event) {
					$message .= date('Y-m-d H:i:s', $event->getTime()) . ' - ' . $event->getMessage() . '<br/>';
				}
				$cmd = $eqLogic->getCmd('info', 'lastEvent');
				if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue($message)) {
					$cmd->event($message);
				}
				$mc = cache::byKey('netatmoWelcomeWidgetmobile' . $eqLogic->getId());
				$mc->remove();
				$mc = cache::byKey('netatmoWelcomeWidgetdashboard' . $eqLogic->getId());
				$mc->remove();
				$eqLogic->toHtml('mobile');
				$eqLogic->toHtml('dashboard');
				$eqLogic->refreshWidget();
			}
		} catch (Exception $e) {

		}
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
		$mc = cache::byKey('netatmoWelcomeWidgetmobile' . $this->getId());
		$mc->remove();
		$mc = cache::byKey('netatmoWelcomeWidgetdashboard' . $this->getId());
		$mc->remove();
		$this->toHtml('mobile');
		$this->toHtml('dashboard');
		$this->refreshWidget();
	}

	public function toHtml($_version = 'dashboard') {
		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
			return $replace;
		}
		$version = jeedom::versionAlias($_version);
		$refresh = $this->getCmd('action', 'refresh');
		if (is_object($refresh)) {
			$replace['#' . $refresh->getLogicalId() . '_id#'] = $refresh->getId();
		}
		$event = $this->getCmd('info', 'lastEvent');
		if (is_object($event)) {
			$replace['#' . $event->getLogicalId() . '#'] = $event->execCmd();
			if ($version == 'mobile') {
				$replace['#' . $event->getLogicalId() . '#'] = str_replace(' - ', '<br/>', $replace['#' . $event->getLogicalId() . '#']);
			}
		}
		foreach ($this->getCmd('action') as $cmd) {
			$replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
		}
		$replace['#user#'] = '';
		foreach ($this->getConfiguration('user_list') as $id => $pseudo) {
			$cmd = $this->getCmd('info', 'isHere' . $id);
			if ($cmd->getIsVisible() == 0) {
				continue;
			}
			$replace_user = array(
				'#name#' => $pseudo,
				'#uid#' => 'netatmoWelcome' . $id . self::UIDDELIMITER . mt_rand() . self::UIDDELIMITER,
				'#lastSeen#' => '',
				'#isHere#' => '',
			);
			if (is_object($cmd)) {
				$replace_user['#isHere#'] = $cmd->execCmd();
			}
			$cmd = $this->getCmd('info', 'lastSeen' . $id);
			if (is_object($cmd)) {
				$replace_user['#lastSeen#'] = $cmd->execCmd();
			}
			$replace['#user#'] .= template_replace($replace_user, getTemplate('core', $version, 'person', 'netatmoWelcome'));
		}
		$replace['#camera#'] = '';
		foreach ($this->getConfiguration('camera_list') as $id => $name) {
			$cmd = $this->getCmd('info', 'state' . $id);
			if ($cmd->getIsVisible() == 0) {
				continue;
			}
			$replace_camera = array(
				'#name#' => $name,
				'#uid#' => 'netatmoWelcome' . $id . self::UIDDELIMITER . mt_rand() . self::UIDDELIMITER,
				'#lastSeen#' => '',
				'#isHere#' => '',
			);
			if (is_object($cmd)) {
				$replace_camera['#state#'] = $cmd->execCmd();
			}
			$cmd = $this->getCmd('info', 'stateSd' . $id);
			if (is_object($cmd)) {
				$replace_camera['#stateSd#'] = $cmd->execCmd();
			}
			$cmd = $this->getCmd('info', 'stateAlim' . $id);
			if (is_object($cmd)) {
				$replace_camera['#stateAlim#'] = $cmd->execCmd();
			}
			$replace['#camera#'] .= template_replace($replace_camera, getTemplate('core', $version, 'camera', 'netatmoWelcome'));
		}
		$html = template_replace($replace, getTemplate('core', $version, 'welcome', 'netatmoWelcome'));
		cache::set('widgetHtml' . $version . $this->getId(), $html, 0);
		return $html;
	}

}

class netatmoWelcomeCmd extends cmd {
	/*     * *************************Attributs****************************** */

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
