<?php

namespace SeedCloud\Controllers;

use SeedCloud\BaseController;
use SeedCloud\DatabaseManager;
use SeedCloud\ConfigManager;

class TrustedController extends BaseController {
	protected $viewFolder = 'trusted';

	public function indexAction() {
		if (!isset($_REQUEST[ConfigManager::GetConfiguration('trusted.secret')])) { die; }
		if (isset($_REQUEST['searchterm'])) {
			$dbHandle = DatabaseManager::getHandle();
               		$sql = 'select * from seedqueue where id0 like :searchterm or friendcode like :searchterm';
                	$statement = $dbHandle->prepare($sql);
                	$statement->bindValue('searchterm', $_REQUEST['searchterm']);
			$result = $statement->execute();
			$results = $statement->fetchAll(\PDO::FETCH_ASSOC);

			return ['searchterm' => $_REQUEST['searchterm'], 'results' => $results];
		}
		return [];
	}
}
