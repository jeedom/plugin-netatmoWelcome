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

	/*     * ***********************Methode static*************************** */

	public function getClient($_scope = NAScopes::SCOPE_READ_CAMERA) {
		if (self::$_client == null) {
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

	public function syncWithNetatmo() {
		$client = self::getClient();
		$response = $client->getData(NULL, 10);
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
			$persons = $home->getPersons();
			foreach ($persons as $person) {
				$person_array = utils::o2a($person);
				$person_array = $person_array['object'];
				$cmd = $eqLogic->getCmd('info', 'isHere' . $person_array['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('isHere' . $person_array['id']);
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Présence', __FILE__) . ' ' . $person_array['pseudo']);
					$cmd->setEventOnly(1);
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
					$cmd->setEventOnly(1);
					$cmd->save();
				}
			}
			$cameras = $home->getCameras();
			foreach ($cameras as $camera) {
				$camera_array = utils::o2a($camera);
				$camera_array = $camera_array['object'];
				$cmd = $eqLogic->getCmd('info', 'state' . $camera_array['id']);
				if (!is_object($cmd)) {
					$cmd = new netatmoWelcomeCmd();
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId('state' . $camera_array['id']);
					$cmd->setType('info');
					$cmd->setSubType('binary');
					$cmd->setName(__('Status', __FILE__) . ' ' . $camera_array['name']);
					$cmd->setEventOnly(1);
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
					$cmd->setEventOnly(1);
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
					$cmd->setEventOnly(1);
					$cmd->save();
				}
			}
			$cmd = $eqLogic->getCmd('info', 'lastEvent');
			if (!is_object($cmd)) {
				$cmd = new netatmoWelcomeCmd();
				$cmd->setEqLogic_id($eqLogic->getId());
				$cmd->setLogicalId('lastEvent');
				$cmd->setType('info');
				$cmd->setSubType('string');
				$cmd->setName(__('Dernier évènement', __FILE__));
				$cmd->setEventOnly(1);
				$cmd->save();
			}
		}
		try {
			$client->dropWebhook();
		} catch (Exception $e) {

		}
		$client->subscribeToWebhook(network::getNetworkAccess('external') . '/plugins/netatmoWelcome/core/php/jeeWelcome.php?apikey=' . config::byKey('api'));
	}

	public static function cron15() {
		try {
			try {
				$client = self::getClient();
				if (config::byKey('numberFailed', 'netatmoWelcome', 0) > 0) {
					config::save('numberFailed', 0, 'netatmoWelcome');
				}
			} catch (NAClientException $e) {
				if (config::byKey('numberFailed', 'netatmoWelcome', 0) > 3) {
					log::add('netatmoWelcome', 'error', __('Erreur sur synchro netatmo weather ', __FILE__) . ' (' . config::byKey('numberFailed', 'netatmoWelcome', 0) . ') ' . $e->getMessage());
				} else {
					config::save('numberFailed', config::byKey('numberFailed', 'netatmoWelcome', 0) + 1, 'netatmoWelcome');
				}
				return;
			}
			$response = $client->getData(NULL, 5);
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
				$message = '';
				foreach ($events as $event) {
					$message .= date('Y-m-d H:i:s', $event->getTime()) . ' - ' . $event->getMessage() . '<br/>';
				}
				$cmd = $eqLogic->getCmd('info', 'lastEvent');
				if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue($message)) {
					$cmd->event($message);
				}
			}
		} catch (Exception $e) {

		}
	}

	/*     * *********************Methode d'instance************************* */

}

class netatmoWelcomeCmd extends cmd {
	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	/*     * *********************Methode d'instance************************* */

	public function execute($_options = array()) {
		if ($this->getLogicalId() == 'refresh') {
			netatmoWelcome::cron15();
		}
	}

	/*     * **********************Getteur Setteur*************************** */
}

?>
