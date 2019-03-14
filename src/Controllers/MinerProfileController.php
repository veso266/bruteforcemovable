<?php

namespace SeedCloud\Controllers;

use SeedCloud\BaseController;
use SeedCloud\BadgeManager;

class MinerProfileController extends BaseController {
	protected $viewFolder = 'minerprofile';

	public function indexAction() {
		$page = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : '';
		if ($page[0] == '/') {
			$page = substr($page, 1);
		}

		$page = explode('?', $page)[0];
		$pageArray = explode('/', $page);

		if (count($pageArray) < 2) { echo "error"; exit; }
		$minerName = $pageArray[1];
		$minerName = preg_replace("/[^a-zA-Z0-9\_\-\|]/", '', $minerName);
		BadgeManager::Bootstrap($minerName);
		//var_dump(BadgeManager::$badgeInstances);

		$badgeData = [];

		foreach(BadgeManager::$badgeInstances as $currentBadgeInstance) {
			$badgeProgress = $currentBadgeInstance->getBadgeProgress();
			$badgeData[] = [
				'title' => $currentBadgeInstance->title,
				'description' => $currentBadgeInstance->description,
				'currentLevel' => $currentBadgeInstance->getBadgeLevel(),
				'badgeProgressPercentage' => $badgeProgress[0],
				'badgeProgressText' => $badgeProgress[1]
			];
		}

		return [
			'minerName' => $minerName,
			'badges' => $badgeData
		];
	}
}
