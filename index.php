<?php
require_once 'config.inc.php';
require_once './vendor/autoload.php';
$loader = new Twig_Loader_Filesystem('./views');
$twig = new Twig_Environment($loader, array(
	//'cache' => './template_cache/'
));


$router = new \SeedCloud\Router($twig);

$router->process();
exit;