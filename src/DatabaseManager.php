<?php
namespace SeedCloud;

class DatabaseManager
{
	protected static $connectionHandle;

	public static function getHandle() {
		if (self::$connectionHandle) return self::$connectionHandle;
		
		$database = ConfigManager::GetConfiguration('database.database');
        $host = ConfigManager::GetConfiguration('database.host');
        $username = ConfigManager::GetConfiguration('database.username');
        $password = ConfigManager::GetConfiguration('database.password');
        self::$connectionHandle = new \PDO('mysql:dbname=' . $database . ';host=' . $host, $username, $password);

		return self::$connectionHandle;
	}
}
