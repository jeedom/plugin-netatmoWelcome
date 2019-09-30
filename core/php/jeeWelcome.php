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
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'netatmoWelcome')) {
	echo __('Vous n\'etes pas autorisé à effectuer cette action', __FILE__);
	die();
}
$data = is_json(file_get_contents("php://input"),false);
log::add('netatmoWelcome','debug','Push call data : '.json_encode($data));
if($data == false){
	netatmoWelcome::refresh_info();
	die();
}

if(in_array($data['push_type'],array('webhook_activation','topology_changed'))){
	die();
}

if(in_array($data['push_type'],array('NOC-human','NOC-vehicle'))){
	$eqLogic = eqLogic::byLogicalId($data['device_id'], 'netatmoWelcome');
	if (!is_object($eqLogic)) {
		die();
	}
	$eqLogic->checkAndUpdateCmd('lastOneEvent',date('Y-m-d H:i:s').' - '.$data['message']);
	$cmd = $eqLogic->getCmd('info','lastEvent');
	if(is_object($cmd)){
		$message = '<span title="" data-tooltip-content="<img height=\'500\' class=\'img-responsive\' src=\''.netatmoWelcome::downloadSnapshot($data['snapshot']['url']).'\'/>">'.date('Y-m-d H:i:s') . ' - ' . $data['message'] . '</span><br/>';
		$message .= $cmd->execCmd();
		$eqLogic->checkAndUpdateCmd('lastEvent',date('Y-m-d H:i:s').' - '.$message);
	}
	die();
}
netatmoWelcome::refresh_info();
