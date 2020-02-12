<?php
## "capabilities":[{"id":"switch","version":1},{"id":"switchLevel","version":1},{"id":"colorControl","version":1},{"id":"colorTemperature","version":1},{"id":"refresh","version":1},{"id":"healthCheck","version":1}]}],
#
#
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


/** *************************** Includes ********************************** */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';


class smartthings extends eqLogic {

	/** *************************** Constantes ******************************** */

	const API_AUTH_URL = "/security/oauth/authorize"; //?client_id=XXX&redirect_uri=XXX&response_type=code&scope=XXX&state=XXX
	const API_TOKEN_URL = "/security/oauth/token"; //client_id=XXX&redirect_uri=XXX&grant_type=authorization_code&code=XXX
	const API_REQUEST_URL = "/api/Locations";

	const API_URL_DEVICES = "https://api.smartthings.com/v1/devices";
	const API_URL_LOCATIONS = "https://api.smartthings.com/v1/locations";


	/** *************************** Attributs ********************************* */

	public static $_widgetPossibility = array('custom' => true);

	/** *************************** Attributs statiques *********************** */



	/** *************************** Méthodes ********************************** */



	/** *************************** Méthodes statiques ************************ */
	public static function baseUrl() {
		if (config::byKey('demo_mode','smartthings')) {
			return "https://simulator.home-connect.com";
		} else {
			return	"https://api.home-connect.com";
		}
	}
	protected static function buildQueryString(array $params) {
		return http_build_query($params, null, '&', PHP_QUERY_RFC3986);
	}

	protected static function lastSegment($key) {
		if (strpos($key, '.') === false) {
			return '';
		}
		$parts = explode('.', $key);
		return $parts[count($parts) - 1];
	}

	protected static function firstSegment($key) {
		if (strpos($key, '.') === false) {
			return '';
		}
		$parts = explode('.', $key);
		return $parts[0];
	}

	public static function request($url, $payload = null, $method = 'POST', $headers = array()) {
		##log::add('smartthings', 'debug',"----call request (".$method.") : ".$url." -----");
		$ch = curl_init($url);

		// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

		$requestHeaders = [
			"Authorization: Bearer ".config::byKey('token','smartthings'),
		];

		if($method == 'POST' || $method == 'PUT') {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
			$requestHeaders[] = 'Content-Type: application/json';
			$requestHeaders[] = 'Content-Length: ' . strlen($payload);
		}

		if(count($headers) > 0) {
			$requestHeaders = array_merge($requestHeaders, $headers);
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
		// curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 7.0; SM-G930F Build/NRD90M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/64.0.3282.137 Mobile Safari/537.36');

		$result = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($code == '200' || $code == '204') {
			return $result;
		} else {
			// Traitement des erreurs
			log::add('smartthings','debug',"La requête $method	: $url a retourné un code d'erreur " . $code . " résultat = ".$result);
			switch ($code) {
				case 400:
					// "Bad Request", desc: "Error occurred (e.g. validation error - value is out of range)"
					break;
				case 401:
					// "Unauthorized", desc: "No or invalid access token"
					throw new \Exception(__("Le jeton d'authentification au serveur est absent ou invalide. Reconnectez-vous.",__FILE__));
					break;
				case 403:
					// Forbidden", desc: "Scope has not been granted or home appliance is not assigned to HC account"
					throw new \Exception(__("Accès à cette ressource non autorisé ou appareil non lié à cet utilisateur.",__FILE__));
					break;
				case 404:
					$result = json_decode($result, true);
					if ($result['error']['key'] == 'SDK.Error.NoProgramActive' || $result['error']['key'] == 'SDK.Error.NoProgramSelected') {
						return $result['error']['key'];
					}
					// Not Found", desc: "This resource is not available (e.g. no images on washing machine)"
					throw new \Exception(__("Cette ressource n'est pas disponible.",__FILE__));
					break;
				case 405:
					// "Method not allowed", desc: "The HTTP Method is not allowed for this resource" },
					throw new \Exception(__("La méthode $method n'est pas permise pour cette ressource.",__FILE__));
					break;
				case 406:
					// "Not Acceptable", desc: "The resource identified by the request is only capable of generating response entities which have content characteristics not acceptable according to the accept headers sent in the request."
					throw new \Exception(__("Impossible de fournir une réponse Les entêtes 'Accept' de la requête ne sont pas acceptés.",__FILE__));
					break;
				case 408:
					// "Request Timeout", desc: "API Server failed to produce an answer or has no connection to backend service"
					throw new \Exception(__("Le serveur n'a pas fourni de réponse dans le temps imparti.",__FILE__));
					break;
				case 409:
					// "Conflict", desc: "Command/Query cannot be executed for the home appliance, the error response contains the error details"
					$result = json_decode($result, true);
					$errorMsg = isset($result['error']['description']) ? $result['error']['description'] : '';
					throw new \Exception(__("Cette action ne peut pas être exécutée pour cet appareil",__FILE__) . ' ' . $errorMsg);
					break;
				case 415:
					// "Unsupported Media Type", desc: "The request's Content-Type is not supported"
					throw new \Exception(__("Le type de contenu de la requête n'est pas pris en charge.",__FILE__));
					break;
				case 429:
					//	"Too Many Requests", desc: "E.g. the number of requests for a specific endpoint exceeded the quota of the client"
					throw new \Exception(__("Vous avez dépassé le nombre de requêtes permises au serveur. Réessayez dans 24h.",__FILE__));
					break;
				case 500:
					// "Internal Server Error", desc: "E.g. in case of a server configuration error or any errors in resource files"
					throw new \Exception(__("Erreur interne du serveur.",__FILE__));
					break;
				case 503:
					// "Service Unavailable", desc: "E.g. if a required backend service is not available"
					throw new \Exception(__("Service indisponible.",__FILE__));
					break;
				default:
				   // Erreur inconnue
				   throw new \Exception(__("Erreur inconnue code $code.",__FILE__));
			}
			return false;
		}
	}

	public static function syncSmartThings() {
		log::add('smartthings', 'debug',"Fonction syncSmartThings()");
		if (empty(config::byKey('token','smartthings'))) {
			log::add('smartthings', 'debug', "[Erreur] : Jeton SmartThings (Token) vide");
			throw new Exception("Erreur : Veuillez fournir le Jeton SmartThings (Token) via le menu configuration du plugin.");
			return;
		}
		// Récupération des Devices
		$Rooms=self::GetRooms();
		$Capabilities=self::GetCapabilitiesDef();
		// Récupération des Rooms
		self::GetDevices($Rooms,$Capabilities);
		
	}

	public static function updateAppliances(){
	/**
	 * Lance la mise à jour des informations des appareils (lancement par cron).
	 *
	 * @param			|*Cette fonction ne retourne pas de valeur*|
	 * @return			|*Cette fonction ne retourne pas de valeur*|
	 */

		log::add('smartthings', 'debug',"Fonction updateAppliances()");

		##self::verifyToken();

		// MAJ du statut de connexion des appareils.
		self::majConnected();

		foreach (eqLogic::byType('smartthings') as $eqLogic) {
			// MAJ des programes en cours.
			$eqLogic->updateProgram();
			// MAJ des états
			$eqLogic->updateStates();
			// MAJ des réglages
			$eqLogic->updateSettings();
			if ($eqLogic->getIsEnable()) {
				$eqLogic->refreshWidget();
			}
		}
		log::add('smartthings', 'debug',"Fin de la fonction updateAppliances()");
	}

	private static function GetCapabilitiesDef() {
		$string_json = file_get_contents("../../3rdparty/capabilities.json");
		$json_capabilities = json_decode($string_json, true);
		return $json_capabilities[Capabilities];
	}
	
	private static function SetCmd($LocaleqLogic,$key,$val,$type) {
	       log::add('smartthings', 'debug', "SetCmd  - ".$key);
	       $cmd = $LocaleqLogic->getCmd(null, $key);
	       if ( ! is_object($cmd) ) {
	            $cmd = new smartthingsCmd();
	            $cmd->setName($key);
	            $cmd->setEqLogic_id($LocaleqLogic->getId());
	            $cmd->setType($type);
	            $cmd->setUnite("");
	            $cmd->setIsHistorized(0);
	            $cmd->setLogicalId($key);
	            // $cmd->setCollectDate('');
	            ##if ( $listValue != "" )
	            ##{
	            ##$cmd->setConfiguration('listValue', $listValue);
	            ##}
	            $cmd->setDisplay('invertBinary',$invertBinary);
	            $cmd->setConfiguration('maxValue',$maxValue);
		    $cmd->setDisplay('generic_type', "GENERIC_INFO");
		    $datatype=$val[schema][properties][value][type];
		    if ($datatype == "" ) {
			    $datatype="string";
		    }

	       	    if (in_array($datatype , array("number","integer"))){
	       		$SubType="numeric";
	       		$template_dashboard = "jauge";
	       		$template_mobile = "jauge";
	       	    }
	       	    if (in_array($datatype , array("string","array"))){
	       		$SubType="string";
	       		$template_dashboard = "badge";
	       		$template_mobile = "badge";
	       	    }
	       	    $cmd->setSubType($SubType);
	       	    $cmd->setTemplate('dashboard', $template_dashboard);
	       	    $cmd->setTemplate('mobile', $template_mobile);
	       	    log::add('smartthings', 'debug', "saving =  ".$key);
	       	    $cmd->save();
	       	    log::add('smartthings', 'debug', $key." saved");
	       	}
	}

	private static function SetCapabilitiesAttributesAndCommands($LocaleqLogic,$json_capabilities,$id,$version) {
		log::add('smartthings', 'debug', "SetCapabilitiesAttributesAndCommands  - ".$id);
		foreach($json_capabilities as $capability) {
		       if (($capability[id] == $id) && ($capability[version] == $version)){
		           log::add('smartthings', 'debug', "Seting up cmd  Main loop for ".$capability[id]);
			   foreach($capability[attributes] as $key => $val) {
		                log::add('smartthings', 'debug', "Seting up cmd  - ".$key);
		                self:: SetCmd($LocaleqLogic,$key,$val,"info");
			   }
			   foreach($capability[commands] as $key => $val) {
		               log::add('smartthings', 'debug', "Seting up Action - ".$key);
			       foreach($val[arguments] as $arguments) {
				     if (in_array($arguments[schema][type] , array("string","number","integer"))){
				            log::add('smartthings', 'debug', "arguments - ".$arguments[name]." type =".$arguments[schema][type]);
				            self:: SetCmd($LocaleqLogic,$key,$val,"action");
				     } else {
		                           log::add('smartthings', 'info', "[ERROR]== Action ".$key."/".$arguments[name]." not yet supported");
				     }
			       }
			   }
		    }
		}
		return array(null,null);
        }


	private static function GetRooms() {
                /* Get List of rooms                             */
                /* 1 - Get Locations                             */
		/* 1 - Get Rooms associated with Locations       */
		$RoomName=array();
		$response = self::request(self::API_URL_LOCATIONS, null, 'GET', array());
		$response = json_decode($response, true);
		foreach($response['items'] as $Locations) {
			$room_url=self::API_URL_LOCATIONS."/".$Locations[locationId]."/rooms";
		        $responseroom = self::request($room_url, null, 'GET', array());
		        $responseroom = json_decode($responseroom, true);
			foreach($responseroom['items'] as $room) {
				$RoomName[$room[roomId]]=$room["name"];
				log::add('smartthings', 'debug', "add room.".$RoomName[$room[roomId]]." for id ".$room[roomId]);
                        }
		}
		return $RoomName;

        }
	public static function DefineDevice($deviceId){
		log::add('smartthings', 'debug', "THIS FONCTION IS NOT CALLED");
		$device_url=self::API_URL_DEVICES."/".$deviceId;
		$devicecmd = self::request($device_url, null, 'GET', array());
		$devicecmd = json_decode($devicecmd, true);
		$status_url=self::API_URL_DEVICES."/".$deviceId."/status";
		$devicestatus = self::request($status_url, null, 'GET', array());
		log::add('smartthings', 'debug', "devicestatus - ".$devicestatus);
		$devicestatus = json_decode($devicestatus, true);
		$Cmd=[];
		foreach($devicecmd['components'] as $components) {
		    log::add('smartthings', 'debug', "Component  - ".$components[id]);
		    foreach($components[capabilities] as $capabilities) {
			    log::add('smartthings', 'debug', "cmd - ".$components[id]." - ".$capabilities[id]);
			    $cmdline = array($capabilities[id], 'info', 'numeric', "unit");
			    $Cmd[]=$cmdline;
		            foreach($devicestatus['components'][$components[id]][$capabilities[id]] as $status) {
			         log::add('smartthings', 'debug', "value - ".$components[id]." - ".$capabilities[id]."=".$status[value]." ".$status[unit]);
		             }
			    log::add('smartthings', 'debug', "--------");
		}
         }
         }


	private static function GetDevices($RoomName,$CapabilitiesDef) {
                /* Get List of devices                           */
                /* Create Object if required                     */
		$response = self::request(self::API_URL_DEVICES, null, 'GET', array());
	log::add('smartthings', 'debug',"response : ".$response);
		$response = json_decode($response, true);

		foreach($response['items'] as $device) {
			// Vérification que l'appareil n'est pas déjà créé.
			$eqLogic = eqLogic::byLogicalId($device[deviceId], 'smartthings');
			if (is_object($eqLogic)) {
			        log::add('smartthings', 'debug',"object ".$device[label]."already exists");
			} else {
			        // l'appareil n'est pas déjà créé, donc creation
			        log::add('smartthings', 'debug',"Creating : ".$device[label]);
				event::add('jeedom::alert', array( 'level' => 'warning', 'page' => 'smartthings', 'message' => __('Nouvel appareil detecté', __FILE__). ' ' .$device['label'],));
				// Create Object Device
				$eqLogic = new smartthings();
				$eqLogic->setLogicalId($device[deviceId]);
				$eqLogic->setIsEnable(1);
				$eqLogic->setIsVisible(1);
				
				$eqLogic->setEqType_name('smartthings');
				$eqLogic->setName($device['label']);
				$eqLogic->setConfiguration('deviceId', $device['deviceId']);
				$eqLogic->setConfiguration('roomId', $device['roomId']);
				$eqLogic->setConfiguration('roomName', $RoomName[$device['roomId']]);
				$eqLogic->save();
				//self::DefineDevice($device['deviceId']);
			        ##log::add('smartthings', 'debug',"NOT Save eqLogic : ".$device[label]);
		                foreach($device['components'] as $components) {
				   foreach($components[capabilities] as $capability) {
		                           log::add('smartthings', 'debug', "-------Now Setting up Cmd : ".$capability[id]);
					   list($lookupInfo,$lookupCmd) = self::SetCapabilitiesAttributesAndCommands($eqLogic,$CapabilitiesDef,$capability[id],$capability[version]);
				   }
			        }

		        }
		}
		log::add('smartthings', 'debug',"Fin de Synchro ");
	}

	private static function majConnected() {
	/**
	 * Récupère le statut connecté des l'appareils.
	 *
	 * @param			|*Cette fonction ne retourne pas de valeur*|
	 * @return			|*Cette fonction ne retourne pas de valeur*|
	 */

		log::add('smartthings', 'debug',"Fonction majConnected()");

		// A voir si l'appareil vient de se connecter n'y aurait-il pas des choses à faire ?
		$response = self::request(self::API_REQUEST_URL, null, 'GET', array());
		$response = json_decode($response, true);
		foreach($response['data']['Devices'] as $key) {
			/* connected = boolean */

			$eqLogic = eqLogic::byLogicalId($key['haId'], 'smartthings');
			if (is_object($eqLogic) && $eqLogic->getIsEnable()){
				$cmd = $eqLogic->getCmd(null, 'connected');
				if (is_object($cmd)) {
					$eqLogic->checkAndUpdateCmd('connected', $key['connected']);

					log::add('smartthings', 'debug', "MAJ du status connected " . $eqLogic->getConfiguration('type', '') . ' ' . $eqLogic->getConfiguration('haId', '') . ' Valeur : '. $key['connected'] ? "Oui" : "Non");

				} else {
					log::add('smartthings', 'debug', "Erreur La commande connected n'existe pas :" . $eqLogic->getConfiguration('type', '') . ' ' . $eqLogic->getConfiguration('haId', ''));
				}
			}
		}

		log::add('smartthings', 'debug',"Fin de la fonction majConnected()");
	}

	public static function findProduct($_appliance) {
		$eqLogic = self::byLogicalId($_appliance['haId'], 'smartthings');
		$eqLogic->loadCmdFromConf($_appliance['type']);
		return $eqLogic;
	}

	public static function devicesParameters($_type = '') {
		$return = array();
		foreach (ls(dirname(__FILE__) . '/../config/types', '*') as $dir) {
			$path = dirname(__FILE__) . '/../config/types/' . $dir;
			if (!is_dir($path)) {
				continue;
			}
			$files = ls($path, '*.json', false, array('files', 'quiet'));
			foreach ($files as $file) {
				try {
					$content = file_get_contents($path . '/' . $file);
					if (is_json($content)) {
						$return += json_decode($content, true);
					}
				} catch (Exception $e) {
				}
			}
		}
		if (isset($_type) && $_type !== '') {
			if (isset($return[$_type])) {
				return $return[$_type];
			}
			return array();
		}
		return $return;
	}

	private static function traduction($word){
	/**
	 * Traduction des informations.
	 *
	 * @param	$word		string		Mot en anglais.
	 * @return	$word		string		Mot en Français (ou anglais, si traduction inexistante).
	 */

		$translate = [
				'Auto1' => __("Auto 35-45°C", __FILE__),
				'Auto2' => __("Auto 45-65°C", __FILE__),
				'Auto3' => __("Auto 65-75°C", __FILE__),
				'Cotton' => __("Coton", __FILE__),
				'CupboardDry' => __("Prêt à ranger", __FILE__),
				'CupboardDryPlus' => __("Prêt à ranger plus", __FILE__),
				'DelicatesSilk' => __("Délicat / Soie", __FILE__),
				'BeanAmount' => __("Quantité de café", __FILE__),
				'DoubleShot' => __("Double shot", __FILE__),
				'DoubleShotPlus' => __("Double shot plus", __FILE__),
				'EasyCare' => __("Synthétique", __FILE__),
				'Eco50' => __("Eco 50°C", __FILE__),
				'Intensiv70' => __("Intensif 70°C", __FILE__),
				'Normal65' => __("Normal 65°C", __FILE__),
				'Glas40' => __("Verres 40°C", __FILE__),
				'GlassCare' => __("Soin des verres", __FILE__),
				'Quick65' => __("Rapide 65°C", __FILE__),
				'HotAir' => __("Air chaud", __FILE__),
				'IronDry' => __("Prêt à repasser", __FILE__),
				'Mild' => __("Doux", __FILE__),
				'Mix' => __("Mix", __FILE__),
				'Normal' => __("Normal", __FILE__),
				'PizzaSetting' => __("Position Pizza", __FILE__),
				'Preheating' => __("Préchauffage", __FILE__),
				'Quick45' => __("Rapide 45°C", __FILE__),
				'Strong' => __("Fort", __FILE__),
				'Synthetic' => __("Synthétique", __FILE__),
				'TopBottomHeating' => __("Convection naturelle", __FILE__),
				'VeryStrong' => __("Très fort", __FILE__),
				'Wool' => __("Laine", __FILE__),
				'Ready' => __("Prêt", __FILE__),
				'Inactive' => __("Inactif", __FILE__),
				'Delayed Start' => __("Départ différé", __FILE__),
				'Pause' => __("Pause", __FILE__),
				'Run' => __("Marche", __FILE__),
				'Finished' => __("Terminé", __FILE__),
				'Error' => __("Erreur", __FILE__),
				'Action Required' => __("Action requise", __FILE__),
				'Aborting' => __("Abandon", __FILE__),
				'On' => __("Marche", __FILE__),
				'Off' => __("Arrêt", __FILE__),
				'Standby' => __("En attente", __FILE__),
				'Open' => __("Ouverte", __FILE__),
				'Closed' => __("Fermée", __FILE__),
				'Locked' => __("Verrouillée", __FILE__),
				'Coffee' => __("Café", __FILE__),
				'Duration' => __("Durée", __FILE__),
				'PreHeating' => __("Préchauffage", __FILE__),
				'Temperature' => __("Température", __FILE__),
				'SetpointTemperature' => __("Consigne température", __FILE__),
				'DryingTarget' => __("Cible de séchage", __FILE__),
				'Cold' => __("Froid", __FILE__),
				'CoffeeTemperature' => __("Température du café", __FILE__),
				'SpinSpeed' => __("Essorage", __FILE__),
				'RPM400' => __("400 tr/min", __FILE__),
				'RPM600' => __("600 tr/min", __FILE__),
				'RPM800' => __("800 tr/min", __FILE__),
				'RPM1000' => __("1000 tr/min", __FILE__),
				'RPM1200' => __("1200 tr/min", __FILE__),
				'RPM1400' => __("1400 tr/min", __FILE__),
				'RPM1600' => __("1600 tr/min", __FILE__),
				'StartInRelative' => __("Départ différé", __FILE__),
				'GC20' => __("20°C", __FILE__),
				'GC30' => __("30°C", __FILE__),
				'GC40' => __("40°C", __FILE__),
				'GC50' => __("50°C", __FILE__),
				'GC60' => __("60°C", __FILE__),
				'GC70' => __("70°C", __FILE__),
				'GC80' => __("80°C", __FILE__),
				'GC90' => __("90°C", __FILE__),
				'FillQuantity' => __("Contenance", __FILE__),
				'DoorState' => __("Porte", __FILE__),
				'RemoteControlStartAllowed' => __("Démarrage à distance", __FILE__),
				'RemoteControlActive' => __("Contrôle à distance", __FILE__),
				'LocalControlActive' => __("Appareil en fonctionnement", __FILE__),
				'OperationState' => __("Statut de fonctionnement", __FILE__),
				'PowerState' => __("Statut de puissance", __FILE__),
		];

		(array_key_exists($word, $translate) == True) ? $word = $translate[$word] : null;

		return $word;
	}

	public static function deleteEqLogic() {
		foreach (eqLogic::byType('smartthings') as $eqLogic) {
			$eqLogic->remove();
		}
	}
	/*
	 * Fonction exécutée automatiquement toutes les minutes par Jeedom */
	  public static function cron15() {
		self::updateAppliances();
	  }


	/*
	 * Fonction exécutée automatiquement toutes les heures par Jeedom
	  public static function cronHourly() {

	  }
	 */

	/*
	 * Fonction exécutée automatiquement tous les jours par Jeedom
	  public static function cronDayly() {

	  }
	 */



	/** *************************** Méthodes d'instance************************ */
	public function cmdNameExists($name) {
		$allCmd = cmd::byEqLogicId($this->getId());
		foreach($allCmd as $u) {
			if($name == $u->getName()) {
				return true;
			}
		}
		return false;
	}
	public function getImage() {
		$filename = 'plugins/smartthings/core/config/images/' . $this->getConfiguration('type') . '.png';
		if(file_exists(__DIR__.'/../../../../'.$filename)){
			return $filename;
		}
		return 'plugins/smartthings/plugin_info/smartthings_icon.png';
	}

	public function applyModuleConfiguration() {
		$this->setConfiguration('applyType', $this->getConfiguration('type'));
		$this->save();
		if ($this->getConfiguration('type') == '') {
		  return true;
		}
		$device = self::devicesParameters($this->getConfiguration('type'));
		if (!is_array($device)) {
			return true;
		}
		$this->import($device);
	}

	public function preInsert() {

	}

	public function isConnected() {
		$cmdConnected = $this->getCmd(null, 'connected');
		if (is_object($cmdConnected)) {
			if ($this->getIsEnable() && $cmdConnected->execCmd()) {
				return true;
			} else {
				return false;
			}
		} else {
			log::add('smartthings', 'debug', "[Erreur] La commande connected n'existe pas :");
			log::add('smartthings', 'debug', "Type : " . $this->getConfiguration('type', ''));
			log::add('smartthings', 'debug', "Marque : " . $this->getConfiguration('brand', ''));
			log::add('smartthings', 'debug', "Modèle : " . $this->getConfiguration('vib', ''));
			log::add('smartthings', 'debug', "Id : " . $this->getLogicalId());
		}
	}

	public function loadCmdFromConf($type) {
		log::add('smartthings', 'debug',"Fonction loadCmdFromConf($type)");
		if (!is_file(dirname(__FILE__) . '/../config/types/' . $type . '.json')) {
			 log::add('smartthings', 'debug', "no config file for type $type");
			return;
		}
		$device = is_json(file_get_contents(dirname(__FILE__) . '/../config/types/' . $type . '.json'), array());
		if (!is_array($device) || !isset($device['commands'])) {
			log::add('smartthings', 'debug', "no command for type $type");
			return true;
		}
		$this->import($device);
		sleep(1);
		event::add('jeedom::alert', array(
			'level' => 'warning',
			'page' => 'openzwave',
			'message' => '',
		));
	}

	public function updateInfoCmdValue($logicalId, $value) {
		$cmd = $this->getCmd(null, $logicalId);
		$reglage = '';
		if (is_object($cmd)) {
			if ($cmd->getConfiguration('withAction')) {
				// C'est une commande associée à une commande action pas de traduction
				if (isset($value['value'])) {
					$reglage = $value['value'];
				} else {
					log::add('smartthings', 'debug', "La commande info : ".$logicalId." n'a pas de valeur");
				}
			} else {
				// Récupération de la valeur du setting.
				if (isset($value['displayvalue'])) {
					$reglage = $value['displayvalue'];
				} else {
					if (isset($value['value'])) {
						if ($cmd->getSubType() == 'string') {
							$reglage = self::traduction(self::lastSegment($value['value']));
						} else {
							$reglage = $value['value'];
						}
					} else {
						log::add('smartthings', 'debug', "la commande info : ".$logicalId." n'a pas de valeur");
					}
				}
			}
			$this->checkAndUpdateCmd($logicalId, $reglage);
			log::add('smartthings', 'debug', "Mise à jour setting : ".$logicalId." - Valeur :".$reglage);
		} else {
			log::add('smartthings', 'debug', "Dans updateInfoCmdValue la commande : ".$logicalId." n'existe pas");
		}
	}

	public function updateProgram() {
		if ($this->isConnected()) {
			$eqLogicType = $this->getConfiguration('type');
			if ($eqLogicType == 'Refrigerator' || $eqLogicType == 'FridgeFreezer' || $eqLogicType == 'WineCooler' || !$this->getConfiguration('hasPrograms', true)) {
				log::add('smartthings', 'debug', "Pas de programme pour ce type d'appareil");
				return;
			}
			log::add('smartthings', 'debug', "MAJ du programme actif :");

			$programActive = self::request(self::API_REQUEST_URL . '/' . $this->getLogicalId() . '/programs/active', null, 'GET', array());
			if ($programActive !== false) {
				log::add('smartthings', 'debug', "Réponse pour program active dans updateProgram " . $programActive);
				$programActive = json_decode($programActive, true);
				if (isset($programActive['data']['key']) && $programActive['data']['key'] !== 'SDK.Error.NoProgramActive') {
					$key = $programActive['data']['key'];
					log::add('smartthings', 'debug', "key = " . $key);
					// recherche du programme action associé
					$actionCmd = $this->getCmd('action', 'PUT::' . $key);
					if (!is_object($actionCmd)) {
						log::add('smartthings', 'debug', "dans updateProgram pas de commande action " . 'PUT::' . $key);
						$programName = self::traduction(self::lastSegment($key));
					} else {
						$programName =$actionCmd->getName();
						log::add('smartthings', 'debug', "Nom de la commande action " . $programName);
					}
					// MAJ du programme en cours.
					$cmd = $this->getCmd(null, 'programActive');
					if (is_object($cmd)) {
						log::add('smartthings', 'debug', "Programme en cours : ".$programName);
						$this->checkAndUpdateCmd('programActive',$programName);

					} else {
						log::add('smartthings', 'debug', "La commande programActive n'existe pas :");
					}
				} else {
					// Pas de programme actif
					// A voir : mettre à jour les autres commandes (états et réglages)
					log::add('smartthings', 'debug', "pas de key ou key = SDK.Error.NoProgramActive");
					$this->checkAndUpdateCmd('programActive', __("Aucun", __FILE__));
				}
			} else {
				log::add('smartthings', 'debug', "réponse à la requête vaut faux");
				$this->checkAndUpdateCmd('programActive', __("Aucun", __FILE__));
			}
			$programSelected = self::request(self::API_REQUEST_URL . '/' . $this->getLogicalId() . '/programs/selected', null, 'GET', array());
			if ($programSelected !== false) {
				log::add('smartthings', 'debug', "Réponse pour program slected dans updateProgram " . $programSelected);
				$programSelected = json_decode($programSelected, true);
				if (isset($programSelected['data']['key']) && $programSelected['data']['key'] !== 'SDK.Error.NoProgramActive') {
					$key = $programSelected['data']['key'];
					log::add('smartthings', 'debug', "key = " . $key);
					// recherche du programme action associé
					$actionCmd = $this->getCmd('action', 'PUT::' . $key);
					if (!is_object($actionCmd)) {
						log::add('smartthings', 'debug', "dans updateProgram pas de commande action " . 'PUT::' . $key);
						$programName = self::traduction(self::lastSegment($key));
					} else {
						$programName =$actionCmd->getName();
						log::add('smartthings', 'debug', "Nom de la commande action " . $programName);
					}
					// MAJ du programme sélectionné.
					$cmd = $this->getCmd(null, 'programSelected');
					if (is_object($cmd)) {
						log::add('smartthings', 'debug', "Programme sélectionné : ".$programName);
						$this->checkAndUpdateCmd('programSelected',$programName);
					} else {
						log::add('smartthings', 'debug', "La commande programSelected n'existe pas :");
					}
				} else {
					// Pas de programme actif
					// A voir : mettre à jour les autres commandes (états et réglages)
					log::add('smartthings', 'debug', "pas de key ou key = SDK.Error.NoProgramSelected");
					$this->checkAndUpdateCmd('programSelected', __("Aucun", __FILE__));
				}
			} else {
				log::add('smartthings', 'debug', "réponse à la requête vaut faux");
				$this->checkAndUpdateCmd('programSelected', __("Aucun", __FILE__));
			}
			$programOptions = self::request(self::API_REQUEST_URL . '/' . $this->getLogicalId() . '/programs/selected/options', null, 'GET', array());
			if ($programOptions !== false) {
				log::add('smartthings', 'debug', "options : " . $programOptions);
				$programOptions = json_decode($programOptions, true);
				// MAJ des options et autres informations du programme en cours.
				foreach ($programOptions['data']['options'] as $value) {
					log::add('smartthings', 'debug', "option : " . print_r($value, true));
					// Récupération du nom du programme / option.
					$logicalId = 'GET::' . $value['key'];
					$optionCmd = $this->getCmd('info', $logicalId);
					if (is_object($optionCmd)) {
						$this->updateInfoCmdValue($logicalId, $value);
					} else {
						log::add('smartthings', 'debug', "pas commande info $logicalId pour mise à jour valeur d'une option");
					}
				}
			}
		}
	}

	public function updateStates() {
		if ($this->isConnected()) {
			log::add('smartthings', 'debug', "MAJ des états ".$this->getLogicalId());

			$response = self::request(self::API_REQUEST_URL . '/' . $this->getLogicalId() . '/status', null, 'GET', array());
			log::add('smartthings', 'debug', "Réponse dans updateStates : " . $response);
			if ($response !== false) {
				$response = json_decode($response, true);
				foreach($response['data']['status'] as $value) {
					log::add('smartthings', 'debug', "status : " . print_r($value, true));
					// Récupération du logicalId du status.
					$logicalId = 'GET::' .$value['key'];
					$this->updateInfoCmdValue($logicalId, $value);
				}
			}
		} else {
			log::add('smartthings', 'debug', "Non connecté, pas de mise à jour des états");
		}
	}

	public function updateSettings() {
		if ($this->isConnected()) {
			log::add('smartthings', 'debug', "MAJ des réglages ".$this->getLogicalId());

			$response = self::request(self::API_REQUEST_URL . '/' . $this->getLogicalId() . '/settings', null, 'GET', array());
			log::add('smartthings', 'debug', "Réponse updateSettings : " . $response);
			if ($response !== false) {
				$response = json_decode($response, true);
				foreach($response['data']['settings'] as $value) {
					log::add('smartthings', 'debug', "setting : " . print_r($value, true));
					// Récupération du logicalId du setting.
					$logicalId = 'GET::' . $value['key'];
					$this->updateInfoCmdValue($logicalId, $value);
				}
			}
		} else {
			log::add('smartthings', 'debug', "Non connecté, pas de mise à jour des états");
		}
	}

	public function updateApplianceData() {
		log::add('smartthings', 'debug',"Fonction updateApplianceData()");
		if ($this->getIsEnable()){
			log::add('smartthings', 'debug',"Mise à jour du status connecté");
			$response = self::request(self::API_REQUEST_URL, null, 'GET', array());
			$response = json_decode($response, true);
			foreach($response['data']['Devices'] as $appliance) {
				log::add('smartthings', 'debug',"Appareil " . print_r($appliance, true));
				if ($this->getLogicalId() == $appliance['haId']) {
					log::add('smartthings', 'debug',"On a trouvé le bon");
					$cmd = $this->getCmd(null, 'connected');
					if (is_object($cmd)) {
						log::add('smartthings', 'debug',"Mise à jour commande connectée valeur " . $appliance['connected']);
						$this->checkAndUpdateCmd('connected', $appliance['connected']);
					}
				}
			}
			$this->updateProgram();
			$this->updateStates();
			$this->updateSettings();
			$this->refreshWidget();
		}
	}

	public function postInsert() {

	}

	public function preSave() {

	}

	public function postSave() {
	/**
	 * Création / MAJ des commandes des appareils.
	 *
	 * @param			|*Cette fonction ne retourne pas de valeur*|
	 * @return			|*Cette fonction ne retourne pas de valeur*|
	 */
		if ($this->getConfiguration('applyType') != $this->getConfiguration('type')) {
			$this->applyModuleConfiguration();
			$this->refreshWidget();
		}
	}

	public function preUpdate() {

	}

	public function postUpdate() {

	}

	public function preRemove() {

	}

	public function postRemove() {

	}

	/*
	 * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
	  public function toHtml($_version = 'dashboard') {

	  }
	 */



	/** *************************** Getters ********************************* */



	/** *************************** Setters ********************************* */



}

class smartthingsCmd extends cmd {

	/** *************************** Constantes ******************************** */



	/** *************************** Attributs ********************************* */



	/** *************************** Attributs statiques *********************** */



	/** *************************** Méthodes ********************************** */



	/** *************************** Méthodes statiques ************************ */



	/** *************************** Méthodes d'instance************************ */

	/*
	 * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
	  public function dontRemoveCmd() {
	  return true;
	  }
	 */

	public function execute($_options = array()) {
		// Bien penser dans les fichiers json à mettre dans la configuration
		// key, value, type, constraints et à modifier findProduct
		log::add('smartthings', 'debug',"Fonction execute()");
		##smartthings::verifyToken();

		if ($this->getType() == 'info') {
			log::add('smartthings', 'debug',"Pas d'execute pour une commande info");
			return;
		}
		$eqLogic = $this->getEqLogic();
		$haid = $eqLogic->getConfiguration('haid', '');
		log::add('smartthings', 'debug',"logicalId : " . $this->getLogicalId());
		log::add('smartthings', 'debug',"Options : " . print_r($_options, true));

		if ($this->getLogicalId() == 'DELETE::StopActiveProgram') {
			// Commande Arrêter
			log::add('smartthings', 'debug',"Commande arrêter");
			// Si l'appareil n'a pas de programme on ne peut pas arrêter
			if (!$eqLogic->getConfiguration('hasPrograms', true)) {
				log::add('smartthings', 'debug',"L'appareil n'a pas de programmes impossible d'arrêter");
				return;
			}
			// S'il n'y a pas de programme actif on ne peut pas arrêter
			$response = smartthings::request(smartthings::API_REQUEST_URL . '/' . $haid . '/programs/active', null, 'GET', array());
			if ($response == false || $response == 'SDK.Error.NoProgramActive') {
				log::add('smartthings', 'debug',"Pas de programme actif impossible d'arrêter");
				return;
			}
		}
		// Pour la commande arrêter le traitement continue

		if ($this->getLogicalId() == 'start') {
			// Commande Lancer
			log::add('smartthings', 'debug',"Commande lancer");
			// Si l'appareil n'a pas de programme on ne peut pas lancer
			if (!$eqLogic->getConfiguration('hasPrograms', true)) {
				log::add('smartthings', 'debug',"L'appareil n'a pas de programmes, impossible de lancer");
				return;
			}

			// On lance le programme sélectionné à condition qu'il existe
			log::add('smartthings', 'debug',"Recherche du programme sélectionné");
			$response = smartthings::request(smartthings::API_REQUEST_URL . '/' . $haid . '/programs/selected', null, 'GET', array());
			log::add('smartthings', 'debug',"Réponse du serveur pour le programme sélectionné " . $response);
			if ($response == false) {
				log::add('smartthings', 'debug',"Pas de programme sélectionné impossible de lancer");
				event::add('jeedom::alert', array(
					'level' => 'warning',
					'message' => __('Sélectionnez un programme avant de lancer', __FILE__),
				));
				return;
			}
			$decodedResponse = json_decode($response, true);
			if(!isset($decodedResponse['data']['key'])) {
				log::add('smartthings', 'debug',"Pas de programme dans la réponse impossible de lancer");
				return;
			}
			$key = $decodedResponse['data']['key'];
			$selectedProgramCmd = $eqLogic->getCmd(null, 'PUT::' . $key);
			if (!is_object($selectedProgramCmd)) {
				// Commande pour le programme sélectionné non trouvée
				log::add('smartthings', 'debug',"La commande logicalId " . 'PUT::' . $key . " n'existe pas impossible de lancer");
				return;
			}
			// Si ce n'est pas un programme selectandstart impossible de lancer
			if ($selectedProgramCmd->getConfiguration('path', '') !== 'programs/selected') {
				log::add('smartthings', 'debug',"Le programme sélectionné n'est pas select and start, impossible de lancer");
				return;
			}
			$url = smartthings::API_REQUEST_URL . '/'. $haid . '/programs/active';
			$payload = '{"data": {"key": "' . $key . '"}}';
			log::add('smartthings', 'debug',"url pour le lancement " . $url);
			log::add('smartthings', 'debug',"payload pour le lancement " . $response);
			$result = smartthings::request($url, $response, 'PUT', array());
			log::add('smartthings', 'debug',"Réponse du serveur au lancement " . $result);
			$eqLogic->updateApplianceData();
			return;

		}
		if ($this->getLogicalId() == 'refresh') {
			log::add('smartthings', 'debug',"| Commande refresh");
			$eqLogic->updateApplianceData();
			return;
		}
		log::add('smartthings', 'debug',"| Commande générique");
		$parts = explode('::', $this->getLogicalId());
		if (count($parts) !== 2) {
			log::add('smartthings', 'debug',"Wrong number of parts in command eqLogic");
			return;
		}
		$method = $parts[0];
		$key = $parts[1];
		// A voir : faut il ajouter qqchose aux headers par defaut de request
		$headers = array();


		// Bien penser à mettre la partie après haid de l'url dans configuration path de la commande
		$path = $this->getConfiguration('path', '');
		$replace = array();
		switch ($this->getSubType()) {
			case 'slider':
			$replace['#slider#'] = intval($_options['slider']);
			break;
			case 'color':
			$replace['#color#'] = $_options['color'];
			break;
			case 'select':
			$replace['#select#'] = $_options['select'];
			break;
			case 'message':
			$replace['#title#'] = $_options['title'];
			$replace['#message#'] = $_options['message'];
			if ($_options['message'] == '' && $_options['title'] == '') {
			  throw new Exception(__('Le message et le sujet ne peuvent pas être vide', __FILE__));
			}
			break;
		}

		if ($method == 'DELETE') {
			$payload = null;
		} else {
			// A compléter avec les bons paramètres qui dépendent de la commande
			// Voir pour un système calqué sur Deconz les stocker dans le logicalId séparé par des ::
			$parameters = array('data' => array());
			if ($this->getConfiguration('key') !== '') {
				$parameters['data']['key'] = $this->getConfiguration('key', '');
			}
			if ($this->getConfiguration('value') !== '') {
				if ($this->getConfiguration('value') === true || $this->getConfiguration('value') === false) {
					$parameters['data']['value'] = $this->getConfiguration('value');
				} else {
					$parameters['data']['value'] = str_replace(array_keys($replace),$replace,$this->getConfiguration('value', ''));
				}
			}

			if ($this->getConfiguration('unit', '') !== '') {
				$parameters['data']['unit'] = $this->getConfiguration('unit', '');
			}
			if ($this->getConfiguration('type', '') !== '') {
				$parameters['data']['type'] = $this->getConfiguration('type', '');
			}
			$payload= json_encode($parameters);
		}

		$url = smartthings::API_REQUEST_URL . '/'. $haid . '/' . $path;
		log::add('smartthings', 'debug',"Paramètres de la requête pour exécuter la commande :");
		log::add('smartthings', 'debug',"Method : " . $method);
		log::add('smartthings', 'debug',"Url : " . $url);
		log::add('smartthings', 'debug',"Payload : " . $payload);
		$response = smartthings::request($url, $payload, $method, $headers);
		log::add('smartthings', 'debug',"Réponse du serveur : " . $response);
		// si la requête est de type program selected il faut mettre à jour les options
		$eqLogic->updateApplianceData();

	}

	/** *************************** Getters ********************************* */



	/** *************************** Setters ********************************* */



}
?>
