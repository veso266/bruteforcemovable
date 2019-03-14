<?php

namespace SeedCloud\Controllers;


use SeedCloud\BaseController;

class BotApiController extends BaseController {
    protected $viewFolder = 'part1';
	
    private function getDBConnection() {
        $connection = new \PDO("mysql:dbname=seedcloud;host=127.0.0.1", 'root', 'ZtDVAoeEY4tEo4iU'); 
        return $connection;
    }
	
	protected function isProbablyValidId0($id0) {
		return preg_match('/^(?![0-9a-fA-F]{4}(01|00)[0-9a-fA-F]{18}00[0-9a-fA-F]{6})[0-9a-fA-F]{32}$/', $id0);
	}

	protected function updateMinerStatus($action) {
		$lastStatus = false;
		$dbCon = $this->getDBConnection();
		$statement = $dbCon->prepare('select * from minerstatus where ip_addr = :ipaddr');
		$statement->bindValue('ipaddr', $this->getRealIP()); 
		$result = $statement->execute();
		if ($result !== false) {
			$results = $statement->fetchAll(\PDO::FETCH_ASSOC);
			if (count($results) > 0) {
				$lastStatus = $results[0];
			}
		}
		
		$sql = 'INSERT INTO minerstatus (ip_addr, last_action_at, last_action_change, action) VALUES (:ipaddr, now(), :last_action_change, :action) ON DUPLICATE KEY UPDATE last_action_at = now(), action = :action, last_action_change = :last_action_change';
		$statement = $this->getDBConnection()->prepare($sql);
		$statement->bindValue('ipaddr', $this->getRealIP()); 
		$statement->bindValue('action', $action);
		$actionChangeDate = date('Y-m-d H:i:s');
		if ($lastStatus && $lastStatus['action'] == $action) {
			$actionChangeDate = $lastStatus['last_action_change'];
		}
		$statement->bindValue('last_action_change', $actionChangeDate);
		$result = $statement->execute();
	} 
	
	protected function grantLeaderboardScore($username) {
		$username = preg_replace("/[^a-zA-Z0-9\_\-\|]/", '', $username);
		$sql = 'INSERT INTO minerscore (username, score, month) VALUES (:username, 5, CONCAT(YEAR(NOW()), MONTH(NOW()))) ON DUPLICATE KEY UPDATE score = score + 5';
		$statement = $this->getDBConnection()->prepare($sql);
		$statement->bindValue('username', $username); 
		$result = $statement->execute();
	}
	
	protected function getRealIP() {//$this->getRealIP();
		if ($_SERVER['REMOTE_ADDR'] == '35.239.91.188') {
			return $_SERVER["HTTP_X_REAL_IP"];
		}
		return $_SERVER['REMOTE_ADDR'];
	}

	protected function getMinerStatus() {
		$dbCon = $this->getDBConnection();
		$statement = $dbCon->prepare('select * from minerstatus where ip_addr like :ipaddr and TIMESTAMPDIFF(MINUTE, last_action_at, now()) < 60');
		$statement->bindValue('ipaddr', $this->getRealIP()); 
		$result = $statement->execute();
		if ($result !== false) {
			$action = $statement->fetchAll(\PDO::FETCH_ASSOC)[0]['action'];
			return intval($action);
		} else {
			return 0; 
		}
	} 


    public function getMovableAction() {
        if (isset($_REQUEST['task']) && strlen($_REQUEST['task']) == 32) {
            $str = "data";
            $dbCon = $this->getDBConnection();
            $statement = $dbCon->prepare('select * from seedqueue where taskId like :taskId');
            $statement->bindValue('taskId', $_REQUEST['task']);
            $result = $statement->execute();
            if ($result !== false) {
                $str = base64_decode($statement->fetchAll(\PDO::FETCH_ASSOC)[0]['movable']);
            } else {
                echo "There was a problem with your movable, please try again.";
                exit;
            }

            header('Content-Disposition: attachment; filename="movable.sed"');
            header('Content-Type: text/plain'); # Don't use application/force-download - it's not a real MIME type, and the Content-Disposition header is sufficient
            header('Content-Length: ' . strlen($str));
            header('Connection: close');
            echo $str;
            exit;
        }
    }

    public function getWorkAction() {
		$dbCon = $this->getDBConnection();
		$timeStarted = microtime(true);
		
		while (microtime(true) < $timeStarted + 30) {
			if ($this->getMinerStatus() > 0) {  
				echo "nothing"; exit;
			}
			$this->updateMinerStatus(0);
			
			$statement = $dbCon->prepare('select * from (select seedqueue.*, minerstatus.ip_addr as miner_ip from minerstatus 
join (select * from seedqueue where state = 3 order by id_seedqueue asc limit 1) as seedqueue on (1=1)
where TIMESTAMPDIFF(SECOND, last_action_at, now()) < 60 and minerstatus.last_action_change is not null and minerstatus.action = 0
order by last_action_change asc limit 1) as tmp where miner_ip like :ip_addr');
			$statement->bindValue('ip_addr', $this->getRealIP()); 
			$result = $statement->execute();
			if ($result !== false) {
				$results = $statement->fetchAll(\PDO::FETCH_ASSOC);
				if (count($results) > 0) {
					echo $results[0]['taskId']; exit;
				}
			} else { 
				echo "nothing"; exit;
			}
			
			sleep(0.5);
		}
		echo "nothing"; exit;
    }
	
    public function claimWorkAction() {
		
        if (isset($_REQUEST['task'])) {
            $taskId = $_REQUEST['task']; 
            $dbCon = $this->getDBConnection();

            /*$statement = $dbCon->prepare('select * from seedqueue where taskId like :taskId');
            $statement->bindValue('taskId', $taskId);
            $result = $statement->execute();
            if ($result !== false && intval($statement->fetchAll(\PDO::FETCH_ASSOC)[0]['state']) === 3) {*/
			
			
                $statement = $dbCon->prepare('update seedqueue set state = 4, time_started = now() where taskId like :taskId and state = 3');
                $statement->bindParam('taskId', $taskId);
                $statement->execute();  
				if ($statement->rowCount() == 1) {
					$this->updateMinerStatus(1);
					echo "okay"; exit;
				}
            //}
        }
        echo "error"; exit;
    }
    public function checkTimeoutsAction() {
		$dbCon = $this->getDBConnection();

		$statement = $dbCon->prepare('update seedqueue set state = -1 where TIMESTAMPDIFF(MINUTE, time_started, now()) >= 60 and state = 4');
		$result = $statement->execute();
		if ($result !== false) {
			echo "okay";
		}
		$statement = $dbCon->prepare('update seedqueue set state = 5 where movable is not null and movable != \'\'');
		$result = $statement->execute();
		if ($result !== false) {
			echo "okay";
		}
         exit;
    }
    public function killWorkAction() {
        if (isset($_REQUEST['task'])) {
            $taskId = $_REQUEST['task'];
            $dbCon = $this->getDBConnection();

            $statement = $dbCon->prepare('select * from seedqueue where taskId like :taskId');
            $statement->bindValue('taskId', $taskId);
            $result = $statement->execute();
            if ($result !== false && intval($statement->fetchAll(\PDO::FETCH_ASSOC)[0]['state']) === 4) {
				$wantedState = $_REQUEST['kill'] == 'n' ? 3 : -1;
                $statement = $dbCon->prepare('update seedqueue set state = :state where taskId like :taskId');
                $statement->bindParam('taskId', $taskId);
				$statement->bindParam('state', $wantedState);
                $statement->execute();
				$this->updateMinerStatus(-1);
                echo "okay"; exit;
            }
        }
        echo "error"; exit;
    }
    public function checkAction() {
        if (isset($_REQUEST['task'])) {
			$this->updateMinerStatus(1);
            $taskId = $_REQUEST['task'];
            $dbCon = $this->getDBConnection();

            $statement = $dbCon->prepare('select * from seedqueue where state = 4 and taskId like :taskId');
            $statement->bindValue('taskId', $taskId);
            $result = $statement->execute();
			$results = $statement->fetchAll(\PDO::FETCH_ASSOC);
            if (count($results) >= 1) {
                echo "ok"; exit;
            } else {
				$this->updateMinerStatus(0);
				echo "error"; exit;
			}
        }
        echo "error"; exit;
    }
    public function getPart1Action() {
        if (isset($_REQUEST['task']) && strlen($_REQUEST['task']) == 32) {
            $str = "data";
            $dbCon = $this->getDBConnection();
            $statement = $dbCon->prepare('select * from seedqueue where taskId like :taskId');
            $statement->bindValue('taskId', $_REQUEST['task']);
            $result = $statement->execute();
            if ($result !== false) {
				$retData = $statement->fetchAll(\PDO::FETCH_ASSOC)[0];
                $str = str_pad(base64_decode($retData['part1b64']), 4096, "\0");
				for ($i = 0; $i <= 32; $i++) {
					$str[0x10 + $i] = $retData['id0'][$i];
				}
            } else {
                echo "There was a problem with your movable, please try again.";
                exit;
            }

            header('Content-Disposition: attachment; filename="movable_part1.sed"');
            header('Content-Type: text/plain'); # Don't use application/force-download - it's not a real MIME type, and the Content-Disposition header is sufficient
            header('Content-Length: ' . strlen($str));
            header('Connection: close');
            echo $str;
            exit;
        }
    }
	
    public function uploadAction() {
        if (isset($_REQUEST['task']) && $_FILES['movable']) {
            $taskId = $_REQUEST['task'];
			$this->updateMinerStatus(0);
            $dbCon = $this->getDBConnection();

            $fileContent = file_get_contents($_FILES['movable']['tmp_name']);

			$movableLength = strlen($fileContent);
			if ($movableLength !== 288 && $movableLength !== 320) {
				$statement = $dbCon->prepare('update seedqueue set state = 3 where taskId like :taskId');
                $statement->bindParam('taskId', $taskId);
                $statement->execute();
				echo "error (Movable Size: " . $movableLength . ")"; exit;
			} 
            $statement = $dbCon->prepare('select * from seedqueue where taskId like :taskId');

            $statement->bindValue('taskId', $taskId);
            $result = $statement->execute();
			$retData = $statement->fetchAll(\PDO::FETCH_ASSOC);
            if ($result !== false && count($retData) > 0) {
				$keyY = substr($fileContent, 0x110, 16);
				$sha = hex2bin(hash("sha256", $keyY));
				$checkId0 = bin2hex(strrev(substr($sha, 0, 4)).
					strrev(substr($sha, 4, 4)).
					strrev(substr($sha, 8, 4)).
					strrev(substr($sha, 12, 4)));
				if ($checkId0 != $retData[0]['id0']) {
					$statement = $dbCon->prepare('update seedqueue set state = 3 where taskId like :taskId');
					$statement->bindParam('taskId', $taskId);
					$statement->execute();
					echo "error (Movable invalid)"; exit;
				} 
				
                $statement = $dbCon->prepare('update seedqueue set state = 5, movable = :movable where taskId like :taskId');
                $statement->bindParam('taskId', $taskId);
                $fileBase64 = base64_encode($fileContent);
                $statement->bindParam('movable', $fileBase64);
                $statement->execute();
				
				if (isset($_REQUEST['minername']) && strlen($_REQUEST['minername']) > 1) {
					$this->grantLeaderboardScore($_REQUEST['minername']);
				}
				
                echo "success"; exit;
            }exit;
        }
        echo "error"; exit;
    }
}