<?php
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
include_file('core', 'authentification', 'php');
log::add('smartthings', 'debug',"Callback :");


log::add('smartthings', 'debug', "GET ".$_GET);
log::add('smartthings', 'debug', "POST ".$_POST);


foreach ($_GET as $value){
log::add('smartthings', 'debug', "GET ".$value);
}
foreach (array_keys($_GET) as $value){
log::add('smartthings', 'debug', "GET [".$value."] = ".$_GET[$value]);
}
foreach (array_keys($_POST) as $value){
log::add('smartthings', 'debug', "POST [".$value."] = ".$_POST[$value]);
}
foreach (array_keys($_SESSION) as $value){
log::add('smartthings', 'debug', "_SESSION [".$value."] = ".$_SESSION[$value]);
}
##if (!jeedom::apiAccess(init('apikey'), 'smartthings')) {
##    log::add('smartthings', 'debug',"apikey = " . init('apikey'));
##    log::add('smartthings', 'debug', "apikey Not Valid");
##    echo 'Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action';
##    die();
##}

## if (!isConnect('admin')) {
##         log::add('smartthings', 'debug', "Needs to be connected");
##         log::add('smartthings', 'debug', "Veuillez vous connecter à votre Jeedom " . network::getNetworkAccess() . "/index.php avant et refaire l\'opération de connexion à SmartThing");
## 	echo 'Vous ne pouvez appeler cette page sans être connecté. Veuillez vous connecter à votre Jeedom <a href=' . network::getNetworkAccess() . '/index.php>ici</a> avant et refaire l\'opération de connexion à Samsung SmartThings Developper';
## 	die();
## }
     header('Content-type: application/json');
     header('HTTP/1.1 200 OK');
     header('\'Access-Control-Allow-Origin\': *');
     header('\'Access-Control-Allow-Headers\': \'Content-Type, Authorization\'');


if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
	unset($_SESSION['oauth2state']);
	exit('Invalid state');
}

config::save('auth', init('code'), 'smartthings');
log::add('smartthings', 'debug', "│ Code d'authorisation sauvegardé (".init('code').").");
smartthings::tokenRequest();
log::add('smartthings', 'debug',"└────────── Fin de Callback");
redirect(network::getNetworkAccess('external') . '/index.php?v=d&p=plugin&id=smartthings');
