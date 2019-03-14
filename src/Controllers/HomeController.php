<?php

namespace SeedCloud\Controllers;


use SeedCloud\BaseController;

class HomeController extends BaseController {
    protected $viewFolder = 'home';
	public $bannedIPs = ['94.254.174.65', '35.202.2.139', '35.226.5.63', '35.232.24.145', '94.254.173.9'];
	public $authKeys = [
	'24B538C6D10523BCE86FC4EAD90C0C5AC306CC9EBC603CC69107B69517733318',
	'2F3D75B447EA2966CC384D66290E8D1C52228E54CB2BE4029F6C1C686E789EAF',
	'3C97A7ACA386DEB4E7C6ED97B18CB7A44894A10E855313BEE2609452413DA90C',
	'BAFF4FB62C8B1C5A7934AEB176930B58670BC06D1DB866B398B7A511A6B90F25',
	'9BAF49839728805DD313FEFD58643D10B5759AE269FB82E97701296264B2FAC9',
	'F3E07946262C3FB1B7FE99D433AC0AF9978E99751D0E9D3FBC4CA242B5679F9D',
	'33f8a8d5c8a80f2bac4c882784d09565b7864f5d06b8245ab41a6ee9a5bb49e6',
	'951be890a524d19ebbc48278ac94df872aea4d2ea1488dc7f8dc2275496c9dce',
	'3078386225291574682E92498DB30D7BDDF382E4491215609DF7DC1153E90339',
	'c3e7cd635716425d574b7a0dc5a5d1f95df112b5fdae5684a8c0b8777f6ce44a',
	'9608C3466CCDDBBD35CF72644D9EA1853F7965DA40045AA24EE5BD408110A675',
	'EA3BD73E2B506E00527232B3ED743C066DA83A8E3066F62A71E75EB9B4AA1DB6',
	'7477D53CA603E95AA1E0E0376B8414A7122A407CAE1935FA44C6597DB3BC8592',
	'8DF4FC847AAE26F544E04940A07E45EDEBD3A6EE92A218AA945C6F08EA8AE9F3',
	'52B5CD277FBCD7882372813E9F952E38246DBB6E8E6274029B086A384A7F37DD',
	'525ECA1D5089DBDCBB6700D910C5E0BC23FBAA23EE026C0E224C2B45490E5F29'
	
	];

    private function getDBConnection() {
        $connection = new \PDO("mysql:dbname=bruteforcemovable;host=127.0.0.1", 'bruteforcemovable', 'liK0sDLA'); 
        return $connection;
    }
	
	protected function isProbablyValidId0($id0) {
		return preg_match('/^(?![0-9a-fA-F]{4}(01|00)[0-9a-fA-F]{18}00[0-9a-fA-F]{6})[0-9a-fA-F]{32}$/', $id0);
	}

	protected function updateMinerStatus($action) {
		$lastStatus = false;
		$dbCon = $this->getDBConnection();
		$statement = $dbCon->prepare('select * from minerstatus where ip_addr = :ipaddr and TIMESTAMPDIFF(SECOND, last_action_at, now()) <= 35');
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
		//if ($_SERVER['REMOTE_ADDR'] == '35.239.91.188') {
		//	return $_SERVER["HTTP_X_REAL_IP"];
		//}
		return $_SERVER['REMOTE_ADDR'];
	}

	protected function getMinerStatus() {
		if (in_array($this->getRealIP(), $this->bannedIPs)) {
			return;
		}
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
	exit;
    }

    public function getWorkAction() {
		$dbCon = $this->getDBConnection();
		$timeStarted = microtime(true);
		if (!isset($_REQUEST['ver'])) {$this->updateMinerStatus(3);}		
			if ($this->getMinerStatus() > 0) {  
				echo "nothing"; exit;
			}
			$this->updateMinerStatus(0);
			
			$statement = $dbCon->prepare('select * from (select seedqueue.*, minerstatus.ip_addr as miner_ip from minerstatus '.
'join (select * from seedqueue where state = 3 order by id_seedqueue asc limit 1) as seedqueue on (1=1) '.
'where TIMESTAMPDIFF(SECOND, last_action_at, now()) < 60 and minerstatus.last_action_change is not null and minerstatus.action = 0 '.
'order by last_action_change asc limit 1) as tmp where miner_ip like :ip_addr');
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
		if ($wantedState == -1) {
			\SeedCloud\BadgeManager::FireEvent(\SeedCloud\BadgeManager::EVENT_MINING_FAILURE);
		}
                echo "okay"; exit;
            }
        }
        echo "error"; exit;
    }
    public function checkAction() {
        if (isset($_REQUEST['task'])) {
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

            /*keyy := movable[0x110:0x11F]
		sha := sha256.Sum256(keyy) 
		testid0 := fmt.Sprintf("%08x%08x%08x%08x", sha[0:4], sha[4:8], sha[8:12], sha[12:16])
log.Println("id0check:", hex.EncodeToString(keyy), hex.EncodeToString(sha[:]), testid0, id0)*/

			

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
		\SeedCloud\BadgeManager::FireEvent(\SeedCloud\BadgeManager::EVENT_MINING_SUCCESS);
                echo "success"; exit;
            }exit;
        }
        echo "error"; exit;
    }

    private function object2array($object) { return @json_decode(@json_encode($object),1); }


    public function getPart1DumperState($id0, $friendcode) {
        $part1MechaResponse = file_get_contents('http://part1dumper.mechanicaldragon.xyz/getStatus.php?friendcode=' . $friendcode . '&id0=' . $id0);
	
        $xmlFile = simplexml_load_string($part1MechaResponse);
		
        return $this->object2array($xmlFile);
    }


    public function resetPart1DumperState($id0, $friendcode) {
        $part1MechaResponse = file_get_contents('http://part1dumper.mechanicaldragon.xyz/resettimeout.php?fc=' . $friendcode);

        return "done";
    }
	
	public function is_base64_encoded()
	{
		if (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $data)) {
			return TRUE;
		} 
		else {
			return FALSE;
		}
	}
	
	public function indexAction() {

	if (isset($_REQUEST['test'])) {
	$part1MechaResponse = file_get_contents('http://part1dumper.mechanicaldragon.xyz/getStatus.php?friendcode=384100639709&id0=19af3cf4a64665d0d53067751280a735');
        
        $xmlFile = simplexml_load_string($part1MechaResponse);
                
        var_dump($this->object2array($xmlFile));die;
}	
	$timeStart = microtime(true);
		if (in_array($this->getRealIP(), $this->bannedIPs)) {
			echo "bye";die;
		}
        if (isset($_REQUEST['taskId']) && isset($_REQUEST['action']) && $_REQUEST['action'] == 'cancel') {
            /*$dbCon = $this->getDBConnection();
            $statement = $dbCon->prepare('update seedqueue set state = -100 where taskId like :taskId');
            $statement->bindParam('taskId', $_REQUEST['taskId']);
            $statement->execute();*/ 
            echo "deleted";
            exit;
        }
        if (isset($_REQUEST['taskId']) && isset($_REQUEST['action']) && $_REQUEST['action'] == 'do-bruteforce') {
            $dbCon = $this->getDBConnection();
            $statement = $dbCon->prepare('update seedqueue set state = 3 where taskId like :taskId');
            $statement->bindParam('taskId', $_REQUEST['taskId']);
            $statement->execute();
            echo "ok";
            exit;
        }
        if (isset($_REQUEST['taskId']) && isset($_REQUEST['action']) && $_REQUEST['action'] == 'reset-fc') {
            $taskId = $_REQUEST['taskId'];
            $dbCon = $this->getDBConnection();
            $statement = $dbCon->prepare('select * from seedqueue where taskId like :taskId');
            $statement->bindValue('taskId', $taskId);
            $result = $statement->execute();
            $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
            if ($result !== false && count($results) === 1) {
                $task = $results[0];
                $okornah = $this->resetPart1DumperState($task['id0'], $task['friendcode']);
            }
            echo "ok";
            exit;
        }
        if (isset($_REQUEST['taskId']) && isset($_REQUEST['action']) && $_REQUEST['action'] == 'get-state') {
            $taskId = $_REQUEST['taskId'];
            $dbCon = $this->getDBConnection();
            $statement = $dbCon->prepare('select * from seedqueue where taskId like :taskId');
            $statement->bindValue('taskId', $taskId);
            $result = $statement->execute();
            $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
            if ($result !== false && count($results) === 1) {
                $task = $results[0];
                $currentState = intval($task['state']);

                if ($currentState === 0) {
                    //Ask Randals site for state info
                    $part1DumperResponse = $this->getPart1DumperState($task['id0'], $task['friendcode']);
                    if ($part1DumperResponse && $part1DumperResponse['claimedBy'] !== '0') {
                        //Yay we got a claim!!!
                        $statement = $dbCon->prepare('update seedqueue set state = 1 where taskId like :taskId');
                        $statement->bindParam('taskId', $taskId);
                        $result = $statement->execute();
                        echo json_encode(array(
                            'currentState' => 1,
                            'claimedBy' => $part1DumperResponse['claimedBy'],
                            'timeout' => $part1DumperResponse['timeout'],
                            'lockout' => $part1DumperResponse['lockout'],
                            'stuff' => $part1DumperResponse
                        ));
                    } else {
                        echo json_encode(array(
						'currentState' => $currentState + (($part1DumperResponse['timeout'] === 'true' || $part1DumperResponse['lockout'] === 'true') ? 1 : 0),
                            'timeout' => $part1DumperResponse['timeout'],
                            'lockout' => $part1DumperResponse['lockout']
						));
                    }
                } else if ($currentState === 1) {
                    $part1DumperResponse = $this->getPart1DumperState($task['id0'], $task['friendcode']);
                    if ($part1DumperResponse['dumped'] === 'true') {
                        //Yay we got a dump!!!

                        $fp1 = '';
                        for ($i=0; $i<6;$i++) {
                            $fp1 .= hex2bin(substr($part1DumperResponse['lfcs'], 14-($i*2),2));
                        }
						
						if (substr($fp1, 0, 4) == "\00\00\00\00") {
							$statement = $dbCon->prepare('update seedqueue set state = -1, part1b64 = :part1b64 where taskId like :taskId');
							$statement->bindParam('taskId', $taskId);
							$statement->bindValue('part1b64', base64_encode($fp1));
							$statement->execute();
							echo json_encode(array(
								'currentState' => -1
							));
							exit; 
						}

                        $statement = $dbCon->prepare('update seedqueue set state = 2, part1b64 = :part1b64 where taskId like :taskId');
                        $statement->bindParam('taskId', $taskId);
                        $statement->bindValue('part1b64', base64_encode($fp1));
                        $statement->execute();
                        echo json_encode(array(
                            'currentState' => 2
                        ));
                    } else {
                        echo json_encode(array('currentState' => $currentState,
                            'claimedBy' => $part1DumperResponse['claimedBy'],
                            'timeout' => $part1DumperResponse['timeout'],
                            'lockout' => $part1DumperResponse['lockout'],
                            'stuff' => $part1DumperResponse
                        ));
                    }
                } else {
                    echo json_encode(array(
                        'currentState' => $currentState,
                            'timeout' => false,
                            'lockout' => false
                    ));
                }
            } else {
                echo json_encode(array(
                    'currentState' => -1
                ));
            }
            exit;
        }
        if (isset($_REQUEST['id0']) && isset($_REQUEST['friendcode'])) {
            $newTaskId = md5(microtime(true).'');
            $id0 = $_REQUEST['id0'];
            $friendcode = $_REQUEST['friendcode'];
            if (!\SeedCloud\Recaptcha::validateRequest($_REQUEST['gRecaptchaResponse'])) {
                echo json_encode(array(
                    'success' => false,
                    'message' => 'You need to verfiy that you are human before sending a bruteforce request.'
                ));
                die;
            }
            if (strlen($id0) == 32 && strlen($friendcode) == 12) {
                if (!$this->isProbablyValidId0($id0)) {
                    echo json_encode(array(
                        'success' => false,
                        'message' => 'The id0 you provided does not seem valid. (Its probably an id1).'
                    ));
                    die;
                }
                $dbCon = $this->getDBConnection();
                $statement = $dbCon->prepare('select * from seedqueue where id0 like :id0 and friendcode like :friendcode and state > -99 order by state desc');
                $statement->bindValue('id0', $_REQUEST['id0']);
                $statement->bindValue('friendcode', $_REQUEST['friendcode']);
                $result = $statement->execute();
                $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
                if ($result !== false && count($results) >= 1) {
                    $task = $results[0];
					
					if ($task['state'] == -1) {
						echo json_encode(array(
							'success' => true,
							'taskId' => $task['taskId']
						));
						exit;
					}

                    if (strlen($task['movable']) > 0) {
                        $statement = $dbCon->prepare('update seedqueue set state = 5 where taskId like :taskId');
                        $statement->bindParam('taskId', $task['taskId']);
                        $statement->execute();
                    } else if (strlen($task['part1b64']) > 0) {
                        $statement = $dbCon->prepare('update seedqueue set state = 3 where taskId like :taskId');
                        $statement->bindParam('taskId', $task['taskId']);
                        $statement->execute();
                    } else {
                        $statement = $dbCon->prepare('update seedqueue set state = 0 where taskId like :taskId');
                        $statement->bindParam('taskId', $task['taskId']);
                        $statement->execute();
                    }
                    echo json_encode(array(
                        'success' => true,
                        'taskId' => $task['taskId']
                    ));
                    exit;
                }

                $statement = $this->getDBConnection()->prepare("insert into seedqueue (id0, part1b64, friendcode, taskId, time_started, `state`, ip_addr) VALUES (:id0, '', :friendcode, :taskId, now(), 0, :ipAddr)");
                $statement->bindValue('id0', $_REQUEST['id0']);
                $statement->bindValue('friendcode', $_REQUEST['friendcode']);
                $statement->bindValue('taskId', $newTaskId);
                $statement->bindValue('ipAddr', $this->getRealIP());
                $result = $statement->execute();
                if ($result) {
                    echo json_encode(array(
                        'success' => true,
                        'taskId' => $newTaskId
                    ));
                } else {
                    echo json_encode(array(
                        'success' => false,
                        'message' => 'The server was unable to save your request. Please try again in an hour.'
                    ));
                }
            } else {
                echo json_encode(array(
                    'success' => false,
                    'message' => 'Your supplied information could not be verified. Please check your inputs and try again.'
                ));
            }
            die;
        }
        if (isset($_REQUEST['id0']) && isset($_REQUEST['part1b64'])) {
            $newTaskId = md5(microtime(true).'');
            $id0 = $_REQUEST['id0'];
            $part1b64 = $_REQUEST['part1b64'];
            if (!\SeedCloud\Recaptcha::validateRequest($_REQUEST['gRecaptchaResponse'])) {
                echo json_encode(array(
                    'success' => false,
                    'message' => 'You need to verfiy that you are human before sending a bruteforce request.'
                ));
                die;
            }
            if (strlen($id0) == 32 && strlen($part1b64) > 0) {
                if (!$this->isProbablyValidId0($id0)) {
                    echo json_encode(array(
                        'success' => false,
                        'message' => 'The id0 you provided does not seem valid. (Its probably an id1).'
                    ));
                    die;
                }
				$part1bRaw = base64_decode($part1b64, true);
				 
			if (strlen($part1bRaw) < 5 || ($part1bRaw[4] !== "\00" && $part1bRaw[4] !== "\02") || $part1bRaw == false || substr($part1bRaw, 0, 4) == "\00\00\00\00") {
                    echo json_encode(array( 
                        'success' => false, 
                        'message' => 'The part1 you provided seems to be invalid.'
                    )); 
                    die;
                }

                $dbCon = $this->getDBConnection();
                $statement = $dbCon->prepare('select * from seedqueue where id0 like :id0 and part1b64 like :part1b64 and state > -99 order by state desc');
                $statement->bindValue('id0', $_REQUEST['id0']);
                $statement->bindValue('part1b64', $_REQUEST['part1b64']);
                $result = $statement->execute();
                $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
                if ($result !== false && count($results) >= 1) {
                    $task = $results[0];

                    if (strlen($task['movable']) > 0) {
                        $statement = $dbCon->prepare('update seedqueue set state = 5 where taskId like :taskId');
                        $statement->bindParam('taskId', $task['taskId']);
                        $statement->execute();
                    } else if (strlen($task['part1b64']) > 0) {
                        $statement = $dbCon->prepare('update seedqueue set state = 3 where taskId like :taskId');
                        $statement->bindParam('taskId', $task['taskId']);
                        $statement->execute();
                    }

                    echo json_encode(array(
                        'success' => true,
                        'taskId' => $task['taskId']
                    ));
                    exit;
                }  

                $statement = $this->getDBConnection()->prepare("insert into seedqueue (id0, part1b64, friendcode, taskId, time_started, `state`, ip_addr) VALUES (:id0, :part1b64, '', :taskId, now(), 3, :ipAddr)");
                $statement->bindValue('id0', $_REQUEST['id0']);
                $statement->bindValue('part1b64', $_REQUEST['part1b64']);
                $statement->bindValue('taskId', $newTaskId);
                $statement->bindValue('ipAddr', $this->getRealIP());
                $result = $statement->execute();
                if ($result) {
                    echo json_encode(array(
                        'success' => true,
                        'taskId' => $newTaskId
                    ));
                } else {
                    echo json_encode(array(
                        'success' => false,
                        'message' => 'The server was unable to save your request. Please try again in an hour.'
                    ));
                }
            } else {
                echo json_encode(array(
                    'success' => false,
                    'message' => 'Your supplied information could not be verified. Please check your inputs and try again.'
                ));
            }
            die;
        }
        $dbConn1 = $this->getDBConnection();
        $statement = $dbConn1->prepare('select count(*) as number from seedqueue where state = 3');
        $result = $statement->execute();
        $waitingForBruteforceCount = $statement->fetchAll(\PDO::FETCH_ASSOC)[0]["number"];
        $statement = $dbConn1->prepare('select count(*) as number from seedqueue where state = 4');
        $result = $statement->execute();
        $bruteforcingCount = $statement->fetchAll(\PDO::FETCH_ASSOC)[0]["number"];
        $statement = $dbConn1->prepare('select count(*) as number from seedqueue where state = 5');
        $result = $statement->execute();
        $gotMovableCount = $statement->fetchAll(\PDO::FETCH_ASSOC)[0]["number"];
        $statement = $dbConn1->prepare('select count(*) as number from minerstatus where `action` = 0 and TIMESTAMPDIFF(SECOND, last_action_at, now()) < 60 ');
        $result = $statement->execute();
        $minerCount = $statement->fetchAll(\PDO::FETCH_ASSOC)[0]["number"];
        $statement = $dbConn1->prepare('select username, SUM(score) as score from minerscore group by username order by SUM(score) desc limit 25');
        $result = $statement->execute();
        $minerScores = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $statement = $dbConn1->prepare('select username, score from minerscore where month = CONCAT(YEAR(NOW()),MONTH(NOW())) order by score desc limit 25');
        $result = $statement->execute();
        $minerScoresThisMonth = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $statement = $dbConn1->prepare('select username, score from minerscore where month = CONCAT(YEAR(DATE_SUB(now(), INTERVAL 1 MONTH)),MONTH(DATE_SUB(now(), INTERVAL 1 MONTH))) order by score desc limit 25');
        $result = $statement->execute();
        $minerScoresLastMonth = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return array(
            'userCount' => $waitingForBruteforceCount,
            'miningCount' => $bruteforcingCount,
            'msCount' => $gotMovableCount,
            'minersStandby' => $minerCount,
			'took' => microtime(true) - $timeStart,
			'minerScores' => $minerScores,
			'minerScoresCurrentMonth' => $minerScoresThisMonth,
			'minerScoresLastMonth' => $minerScoresLastMonth
        );
    } 
}
