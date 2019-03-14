<?php

namespace SeedCloud;

class Router {
    /** @var \Twig_Environment */
    public $twigEnvironment;

    /**
     * Router constructor.
     * @param $twigEnvironment \Twig_Environment
     */
    public function __construct($twigEnvironment)
    {
        $this->twigEnvironment = $twigEnvironment;
    }

    private function getController() {
	
			$page = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : '';
			if ($page[0] == '/') {
				$page = substr($page, 1);
			}
			
			$page = explode('?', $page)[0];
			$pageArray = explode('/', $page);

			switch ($pageArray[0]) {
				case 'getWork':
					return ['Home', 'getWork'];
				case 'claimWork':
					return ['Home', 'claimWork'];
				case 'killWork':
					return ['Home', 'killWork'];
				case 'getPart1':
					return ['Home', 'getPart1'];
				case 'check':
					return ['Home', 'check'];
				case 'get_movable': 
					return ['Home', 'getMovable'];
				case 'checkTimeouts':
					return ['Home', 'checkTimeouts'];
				case 'upload':
					return ['Home', 'upload'];
				case 'frontend-api':
					return ['Home', 'index'];
					break;
				case 'minerprofile':
					return ['MinerProfile', 'index'];
				case 'trusted':
					return ['Trusted', 'index'];
				default:
					return ['Home', 'index'];
					break;
			}
    }

    /**
     * @param $dbManager DatabaseManager
     */
    public function process() {
        $controllerAction = $this->getController();
        //@TODO: Controller and Action will need to be parsed from request data.
        $controllerClassName = "\\SeedCloud\\Controllers\\" . $controllerAction[0] . "Controller";
        /** @var BaseController $controllerInstance */
        $controllerInstance = new $controllerClassName($this);

        $controllerInstance->process($controllerAction[1]); //@TODO: Pass Request info somehow
    }
}
