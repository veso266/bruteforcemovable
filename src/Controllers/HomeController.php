<?php

namespace SeedCloud\Controllers;


use SeedCloud\BaseController;

class HomeController extends BaseController
{
	protected $viewFolder = 'home';
	
	private function getDBConnection()
	{
		$global $CONF;
        $connection = new \PDO(
	        "mysql:dbname=" . $CONF['mysql']['database'] . ";host=" . $CONF['mysql']['host'] . "",
	        $CONF['mysql']['user'],
	        $CONF['mysql']['password']
        );
        return $connection;
    }
	
	protected function isProbablyValidId0($id0)
	{
		return preg_match('/^(?![0-9a-fA-F]{4}(01|00)[0-9a-fA-F]{18}00[0-9a-fA-F]{6})[0-9a-fA-F]{32}$/', $id0);
	}
	
	protected function updateMinerStatus($action)
	{
		$sql = 'INSERT INTO minerstatus (ip_addr, last_action_at, action) VALUES (:ipaddr, now(), :action) ON DUPLICATE KEY UPDATE last_action_at = now(), action = :action';
		$statement = $this->getDBConnection()->prepare($sql);
		$statement->bindValue('ipaddr', $_SERVER['REMOTE_ADDR']);
		$statement->bindValue('action', $action);
		$result = $statement->execute();
	}
	
	
	public function getMovableAction()
	{
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
	
	public function getWorkAction()
	{
		$this->updateMinerStatus(0);
		$dbCon = $this->getDBConnection();
		$statement = $dbCon->prepare('select * from seedqueue where state = 3 order by id_seedqueue asc limit 1');
		$result = $statement->execute();
		if ($result !== false) {
			$results = $statement->fetchAll(\PDO::FETCH_ASSOC);
			if (count($results) > 0) {
				echo $results[0]['taskId'];
				exit;
			}
			echo "nothing";
			exit;
		} else {
			echo "nothing";
			exit;
		}
	}
	
	public function claimWorkAction()
	{
		if (isset($_REQUEST['task'])) {
			$taskId = $_REQUEST['task'];
			$dbCon = $this->getDBConnection();
			
			$statement = $dbCon->prepare('select * from seedqueue where taskId like :taskId');
			$statement->bindValue('taskId', $taskId);
			$result = $statement->execute();
			if ($result !== false && intval($statement->fetchAll(\PDO::FETCH_ASSOC)[0]['state']) === 3) {
				$statement = $dbCon->prepare('update seedqueue set state = 4, time_started = now() where taskId like :taskId');
				$statement->bindParam('taskId', $taskId);
				$statement->execute();
				$this->updateMinerStatus(1);
				echo "okay";
				exit;
			}
		}
		echo "error";
		exit;
	}
	
	public function checkTimeoutsAction()
	{
		$dbCon = $this->getDBConnection();
		
		$statement = $dbCon->prepare('update seedqueue set state = -1 where TIMESTAMPDIFF(MINUTE, time_started, now()) >= 30 and state = 4');
		$result = $statement->execute();
		if ($result !== false) {
			echo "okay";
		}
		$statement = $dbCon->prepare('update seedqueue set state = 5 where movable is not null');
		$result = $statement->execute();
		if ($result !== false) {
			echo "okay";
		}
		exit;
	}
	
	public function killWorkAction()
	{
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
				echo "okay";
				exit;
			}
		}
		echo "error";
		exit;
	}
	
	public function checkAction()
	{
		if (isset($_REQUEST['task'])) {
			$taskId = $_REQUEST['task'];
			$dbCon = $this->getDBConnection();
			
			$statement = $dbCon->prepare('select * from seedqueue where state = 4 and taskId like :taskId');
			$statement->bindValue('taskId', $taskId);
			$result = $statement->execute();
			$results = $statement->fetchAll(\PDO::FETCH_ASSOC);
			if (count($results) >= 1) {
				echo "ok";
				exit;
			}
		}
		echo "error";
		exit;
	}
	
	public function getPart1Action()
	{
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
	
	public function uploadAction()
	{
		if (isset($_REQUEST['task']) && $_FILES['movable']) {
			$taskId = $_REQUEST['task'];
			
			$dbCon = $this->getDBConnection();
			
			
			$fileContent = file_get_contents($_FILES['movable']['tmp_name']);
			
			/*keyy := movable[0x110:0x11F]
		sha := sha256.Sum256(keyy)
		testid0 := fmt.Sprintf("%08x%08x%08x%08x", sha[0:4], sha[4:8], sha[8:12], sha[12:16])
log.Println("id0check:", hex.EncodeToString(keyy), hex.EncodeToString(sha[:]), testid0, id0)*/
			
			
			$statement = $dbCon->prepare('select * from seedqueue where taskId like :taskId');
			
			$statement->bindValue('taskId', $taskId);
			$result = $statement->execute();
			if ($result !== false && intval($statement->fetchAll(\PDO::FETCH_ASSOC)[0]['state']) === 4) {
				$statement = $dbCon->prepare('update seedqueue set state = 5, movable = :movable where taskId like :taskId');
				$statement->bindParam('taskId', $taskId);
				$fileBase64 = base64_encode($fileContent);
				$statement->bindParam('movable', $fileBase64);
				$statement->execute();
				echo "success";
				exit;
			}
			exit;
		}
		echo "error";
		exit;
	}
	
	private function object2array($object)
	{
		return @json_decode(@json_encode($object), 1);
	}
	
	
	public function getPart1DumperState($id0, $friendcode)
	{
		$part1MechaResponse = file_get_contents('http://part1dumper.mechanicaldragon.xyz/getStatus.php?friendcode=' . $friendcode . '&id0=' . $id0);
		$xmlFile = simplexml_load_string($part1MechaResponse);
		return $this->object2array($xmlFile);
	}
	
	
	public function resetPart1DumperState($id0, $friendcode)
	{
		$part1MechaResponse = file_get_contents('http://part1dumper.mechanicaldragon.xyz/resettimeout.php?fc=' . $friendcode);
		
		return "done";
	}
	
	public function indexAction()
	{
		if (isset($_REQUEST['taskId']) && isset($_REQUEST['action']) && $_REQUEST['action'] == 'cancel') {
			$dbCon = $this->getDBConnection();
			$statement = $dbCon->prepare('update seedqueue set state = -100 where taskId like :taskId');
			$statement->bindParam('taskId', $_REQUEST['taskId']);
			$statement->execute();
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
							'stuff' => $part1DumperResponse
						));
					} else {
						echo json_encode(array('currentState' => $currentState));
					}
				} else if ($currentState === 1) {
					$part1DumperResponse = $this->getPart1DumperState($task['id0'], $task['friendcode']);
					if ($part1DumperResponse['dumped'] === 'true') {
						//Yay we got a dump!!!
						
						$fp1 = '';
						for ($i = 0; $i < 6; $i++) {
							$fp1[$i] = pack("H*", substr($part1DumperResponse['lfcs'], 14 - ($i * 2), 16 - ($i * 2)));
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
							'stuff' => $part1DumperResponse
						));
					}
				} else {
					echo json_encode(array(
						'currentState' => $currentState
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
			$newTaskId = md5(microtime(true) . '');
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
				
				$statement = $this->getDBConnection()->prepare("insert into seedqueue (id0, part1b64, friendcode, taskId, time_started, `state`) VALUES (:id0, '', :friendcode, :taskId, now(), 0)");
				$statement->bindValue('id0', $_REQUEST['id0']);
				$statement->bindValue('friendcode', $_REQUEST['friendcode']);
				$statement->bindValue('taskId', $newTaskId);
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
			$newTaskId = md5(microtime(true) . '');
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
				
				$statement = $this->getDBConnection()->prepare("insert into seedqueue (id0, part1b64, friendcode, taskId, time_started, `state`) VALUES (:id0, :part1b64, '', :taskId, now(), 3)");
				$statement->bindValue('id0', $_REQUEST['id0']);
				$statement->bindValue('part1b64', $_REQUEST['part1b64']);
				$statement->bindValue('taskId', $newTaskId);
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
		$statement = $dbConn1->prepare('select count(*) as number from seedqueue where movable is not null');
		$result = $statement->execute();
		$gotMovableCount = $statement->fetchAll(\PDO::FETCH_ASSOC)[0]["number"];
		$statement = $dbConn1->prepare('select count(*) as number from minerstatus where `action` = 0 and TIMESTAMPDIFF(SECOND, last_action_at, now()) < 60 ');
		$result = $statement->execute();
		$minerCount = $statement->fetchAll(\PDO::FETCH_ASSOC)[0]["number"];
		return array(
			'userCount' => $waitingForBruteforceCount,
			'miningCount' => $bruteforcingCount,
			'msCount' => $gotMovableCount,
			'minersStandby' => $minerCount
		);
	}
}