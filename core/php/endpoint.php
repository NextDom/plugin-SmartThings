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
require_once __DIR__ . "/../../../../core/php/core.inc.php";
$headers = apache_request_headers();
$testG = file_get_contents('php://input');
$body = json_decode(file_get_contents('php://input'), true);
$challenge = $body['pingData']['challenge'];
$lifecycle = $body['lifecycle'];

if ($lifecycle == "PING") {
	log::add('smartthings', 'debug', "got a PING. challenge = ".$challenge);
	$reply = array();
	$reply['statusCode'] = "200";
	$reply['pingData'] = $body['pingData'];
	log::add('smartthings', 'debug', 'Reply : ' . json_encode($reply, true));
	header('HTTP/1.1 200 OK');
	echo json_encode($reply);

}

if (isset($body['originalDetectIntentRequest']) && isset($body['originalDetectIntentRequest']['payload']) && isset($body['originalDetectIntentRequest']['payload']['user']) & isset($body['originalDetectIntentRequest']['payload']['user']['accessToken'])) {
	$plugin = plugin::byId('smartthings');
	if (!$plugin->isActive()) {
		header('HTTP/1.1 401 Unauthorized');
		echo json_encode(array());
		die();
	}
	if ($body['originalDetectIntentRequest']['payload']['user']['accessToken'] != config::byKey('OAuthAccessTokendf', 'smartthings') || config::byKey('OAuthAccessTokendf', 'smartthings') == '') {
		header('HTTP/1.1 401 Unauthorized');
		echo json_encode(array());
		die();
	}
	if (config::byKey('dialogflow::authkey', 'smartthings') == '' || !isset($headers['authkey']) || config::byKey('dialogflow::authkey', 'smartthings') != $headers['authkey']) {
		header('HTTP/1.1 401 Unauthorized');
		echo json_encode(array());
		die();
	}
	if (!isset($body['queryResult']) || !isset($body['queryResult']['queryText'])) {
		header('HTTP/1.1 401 Unauthorized');
		echo json_encode(array());
		die();
	}
	$query = $body['queryResult']['queryText'];
	$params = array('plugin' => 'smartthings', 'reply_cmd' => null);
	$response = interactQuery::tryToReply(trim($query), $params);
	header('Content-type: application/json');
	log::add('smartthings', 'debug', json_encode(smartthings::buildDialogflowResponse($body, $response)));
	echo json_encode(smartthings::buildDialogflowResponse($body, $response));
	die();
} else if (isset($headers['Authorization'])) {
	header('Content-type: application/json');
	if (config::byKey('smartthings::authkey', 'smartthings') == '' || init('secure') != config::byKey('smartthings::authkey', 'smartthings')) {
		header('HTTP/1.1 401 Unauthorized');
		echo json_encode(array());
		die();
	}
	$matches = array();
	preg_match('/Bearer (.*)/', $headers['Authorization'], $matches);
	if (!isset($matches[1]) || $matches[1] != config::byKey('OAuthAccessTokensh', 'smartthings') || config::byKey('OAuthAccessTokensh', 'smartthings') == '') {
		header('HTTP/1.1 401 Unauthorized');
		echo json_encode(array());
		die();
	}
	$plugin = plugin::byId('smartthings');
	if (!$plugin->isActive()) {
		header('HTTP/1.1 401 Unauthorized');
		echo json_encode(array());
		die();
	}
	log::add('smartthings', 'debug', 'Ask : ' . json_encode($body, true));
	$reply = array();
	$reply['requestId'] = $body['requestId'];
	foreach ($body['inputs'] as $input) {
		if ($input['intent'] == 'action.devices.EXECUTE') {
			$reply['payload'] = smartthings::exec(array('data' => $input['payload']));
		} else if ($input['intent'] == 'action.devices.QUERY') {
			$reply['payload'] = smartthings::query($input['payload']);
		} else if ($input['intent'] == 'action.devices.SYNC') {
			$reply['payload'] = array();
			$reply['payload']['agentUserId'] = config::byKey('smartthings::useragent', 'smartthings');
			$reply['payload']['devices'] = smartthings::sync();
		}
	}
	log::add('smartthings', 'debug', 'Reply : ' . json_encode($reply, true));
	header('HTTP/1.1 200 OK');
	echo json_encode($reply);
	die();
}

if (init('apikey') != '') {
	$apikey = init('apikey');
	if(isset($apikey) && strpos($apikey,'-') !== false){
		$apikey = substr($apikey, 0, strpos($apikey, '-'));
	}
	if (!jeedom::apiAccess($apikey, 'smartthings')) {
		echo __('Vous n\'etes pas autorisé à effectuer cette action. Clef API invalide. Merci de corriger la clef API sur votre page profils du market et d\'attendre 24h avant de réessayer.', __FILE__);
		die();
	} else {
		echo __('Configuration OK', __FILE__);
		die();
	}
}
header('Content-type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if(isset($body['apikey']) && strpos($body['apikey'],'-') !== false){
	$body['apikey'] = substr($body['apikey'], 0, strpos($body['apikey'], '-'));
}
if (!isset($body['apikey']) || !jeedom::apiAccess($body['apikey'], 'smartthings')) {
	echo json_encode(array(
		'status' => 'ERROR',
		'errorCode' => 'authFailure',
	));
	die();
}

$plugin = plugin::byId('smartthings');
if (!$plugin->isActive()) {
	echo json_encode(array(
		'status' => 'ERROR',
		'errorCode' => 'authFailure',
	));
	die();
}
log::add('smartthings', 'debug', 'Request : '.json_encode($body));
if ($body['action'] == 'exec') {
	$result = json_encode(smartthings::exec($body));
	log::add('smartthings', 'debug', 'Exec result : '.$result);
	echo $result;
	die();
} else if ($body['action'] == 'query') {
	$result = json_encode(smartthings::query($body));
	log::add('smartthings', 'debug','Query result : '. $result);
	echo $result;
	die();
} else if ($body['action'] == 'interact') {
	if (isset($data['queryResult']['languageCode']) && method_exists('translate', 'setLanguage') && str_replace('_', '-', strtolower(translate::getLanguage())) != $data['queryResult']['languageCode']) {
		if (strpos($data['queryResult']['languageCode'], 'en-') !== false) {
			translate::setLanguage('en_US');
		} elseif (strpos($data['queryResult']['languageCode'], 'fr-') !== false) {
			translate::setLanguage('fr_FR');
		}
	}
	$query = $body['data']['queryResult']['queryText'];
	$params = array('plugin' => 'smartthings', 'reply_cmd' => null);
	$response = interactQuery::tryToReply(trim($query), $params);
	header('Content-type: application/json');
	log::add('smartthings', 'debug', json_encode(smartthings::buildDialogflowResponse($body['data'], $response)));
	echo json_encode(smartthings::buildDialogflowResponse($body['data'], $response));
	die();
}

echo json_encode(array(
	'status' => 'SUCCESS',
));
die();
