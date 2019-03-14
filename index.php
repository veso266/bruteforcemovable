<?php
require_once './vendor/autoload.php';
$loader = new Twig_Loader_Filesystem('./views');
$twig = new Twig_Environment($loader, array(
	//'cache' => './template_cache/'
));

session_start();
if (isset($_REQUEST['minername'])) {
	$minerName = $_REQUEST['minername'];
	$minerName = preg_replace("/[^a-zA-Z0-9\_\-\|]/", '', $minerName);
	\SeedCloud\BadgeManager::Bootstrap($minerName);
	$_SESSION['minername'] = $minerName;
	\SeedCloud\BadgeManager::FireEvent(\SeedCloud\BadgeManager::EVENT_MINER_SEEN);
} else if (isset($_SESSION['minername']) && strlen($_SESSION['minername']) > 0) {
	\SeedCloud\BadgeManager::Bootstrap($_SESSION['minername']);
	\SeedCloud\BadgeManager::FireEvent(\SeedCloud\BadgeManager::EVENT_MINER_SEEN);
}

$router = new \SeedCloud\Router($twig);

$router->process();
exit; 
