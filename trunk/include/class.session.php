<?php
/*****************************************************************************
	Facula Framework Session Management Unit
	
	FaculaFramework 2009-2012 (C) Rain Lee <raincious@gmail.com>
	
	@Copyright 2009-2012 Rain Lee <raincious@gmail.com>
	@Author Rain Lee <raincious@gmail.com>
	@Package FaculaFramework
	@Version 0.2-alpha
	
	This file is part of Facula Framework.
	
	Facula Framework is free software: you can redistribute it and/or modify
	it under the terms of the GNU Lesser General Public License as published 
	by the Free Software Foundation, version 3.
	
	Facula Framework is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Lesser General Public License for more details.
	
	You should have received a copy of the GNU Lesser General Public License
	along with Facula Framework. If not, see <http://www.gnu.org/licenses/>.
*******************************************************************************/

if(!defined('IN_FACULA')) {
	exit('Access Denied');
}

class session {
	private $dbobj = null;
	private $secobj = null;
	private $oopsobj = null;
	
	public $error = '';

	// Setting set
	private $set = array();
	
	// Client infos
	private $clientrecords = array();
	
	// User infos
	private $user = array();
	
	public $imported = array();
	
	public function __construct(&$set, &$dbobj, &$secobj, &$oopsobj, $mode = 'Primary') {
		ini_set('session.auto_start', false);
		ini_set('session.use_cookies', false);
		ini_set('session.use_trans_sid', false);
		
		if (is_object($dbobj) && is_object($secobj) && is_object($oopsobj)) {
			$this->dbobj = $dbobj;
			$this->secobj = $secobj;
			$this->oopsobj = $oopsobj;
			
			$this->set = array('Time' => $this->secobj->time(),
								'Alert' => '',
								'Cookiepre' => $set['Cookiepre'] ? $set['Cookiepre'] : 'ff_',
								'WorkingMode' => $mode == 'Primary' || $mode == 'Readonly' || $mode == 'Updateonly' ? $mode : 'Primary',
								'MaxToken' => $set['MaxToken'] > 1 && $set['MaxToken'] < 8 ? $set['MaxToken'] : 8,
								'SessionGot' => false,
								'SessionUpdated' => false,
								'Record' => '',
								'LoginFailToBan' => $set['LoginFailLimit'] > 1 && $set['LoginFailLimit'] < 10 ? $set['LoginFailLimit'] : 5,
								'LoginFailBanTime' => $set['LoginFailBanTime'] > 60 && $set['LoginFailBanTime'] < 3600 ? $set['LoginFailBanTime'] : 60,
								'SessionExpired' => $set['SessionExpired'] > 500 && $set['SessionExpired'] < 3600 ? $set['SessionExpired'] : 500,
								'SessionTicketExpired' => $set['SessionTicketExpired'] > 86400 && $set['SessionTicketExpired'] < 604800 ? $set['SessionTicketExpired'] : 604800);
			
			if ($this->parseRecords() && $this->getSession()) {
				$set = null;
				register_shutdown_function(array(&$this, 'update'));
				return true;
			} else {
				$this->oops($this->error);
				return false;
			}
		}
		
		$set = null;
		exit('A critical problem prevents initialization while trying to create '.__CLASS__.'.');
	}
	
	public function oops($erstring, $exit = false) {
		$error_codes = array('ERROR_SESSION_GEN_CONDITIONCODE_FAILED' => 'Cannot generate the condition code that use for client authorization.',
							'ERROR_SESSION_CANNOT_SENT_CLIENT_KEY' => 'We cannot set up a Sole condition code and even Chorus condition code for you, please enable COOKIE support in your browser, thank you.',
							'ERROR_SESSION_CANNOT_INIT_SESSION' => 'We cannot start session now. sorry, please refresh to try to fix this problem.',
							'ERROR_SESSION_SESSION_ALREADY_EXISTS' => 'Session ID already be add in other place, but it\'s cannot be use right now.');
		
		if ($this->oopsobj->pushmsg($error_codes) && $this->oopsobj->ouch($erstring, $exit)) {
			return true;
		}
		
		return false;
	}
	
	private function parseRecords() {
		global $_COOKIE, $_SESSION;
		$cookielens = 0;
		$newval = $newkey = '';

		if (!$this->set['Record']) {
			if ($_COOKIE[$this->set['Cookiepre'].'factor']) {
				$cookielens = strlen($this->set['Cookiepre']);
				foreach($_COOKIE as $key => $val) {
					if(substr($key, 0, $cookielens) == $this->set['Cookiepre']) {
						$this->clientrecords[(substr($key, $cookielens))] = $this->secobj->addSlashes($val);
					}
				}
				
				$_COOKIE = array();
				$this->set['Record'] = 'COOKIE';

				return true;
			} elseif ($this->initSession()) {
				if ($_SESSION['PublicKey']) {
					foreach($_SESSION AS $key => $val) {
						if ($newval = $this->secobj->addSlashes($val)) {
							$this->clientrecords[$key] = $newval;
							if ($key != 'PublicKey') {
								$this->clientdata($key, $newval, true);
							}
						}
					}
				}
				
				$newval = '';
				$this->set['Record'] = 'PHPSESSION';
				
				return true;
			} else {
				if (!$this->error) {
					$this->error = 'ERROR_SESSION_CANNOT_SENT_CLIENT_KEY';
				}
				$this->oops($this->error, true);
			}
		}
		
		return false;
	}
	
	private function initSession() {
		global $_SESSION;
		
		$conditioncode = '';
		
		if (!session_id()) {
			session_name('FFSESSION'); // This function just move from below below below IF for anit below problem.
			if ($conditioncode = $this->getClientHash()) { // MUST BE VALID HASH
				session_id($conditioncode); // Well, after 8 hrs, i finally know the truth:
											// This damn function will NOT return any value when you setting it.
											// After I remove it from below IF, it works. Thank god i finally know this.
											// I need notice it so i make this five-line comment.
											// And this is the line five.
				if (!headers_sent() && session_start() && $conditioncode == ($_SESSION['PublicKey'] = session_id())) { // Check if the Session ID really setted
					return true;
				} else {
					$this->error = 'ERROR_SESSION_CANNOT_INIT_SESSION';
					return false;
				}
			} else {
				$this->error = 'ERROR_SESSION_GEN_CONDITIONCODE_FAILED';
			}
		} else {
			$this->error = 'ERROR_SESSION_SESSION_ALREADY_EXISTS';
		}
		
		return false;
	}

	private function getClientHash($stochastic = false) {
		global $_SERVER;
		
		if (!$stochastic)
			return $this->secobj->genHashForClient($_SERVER['REMOTE_ADDR'].$this->secobj->getUserIP(null, true).$_SERVER['HTTP_USER_AGENT']);
		else
			return $this->secobj->genHashForClient($this->secobj->randSeed(6).$this->set['Time'].$_SERVER['REMOTE_ADDR'].$this->secobj->getUserIP(null, true).$_SERVER['HTTP_USER_AGENT']);
	}

	private function writecookie($key, $value, $ttl = 0, $patch = '/') {
		global $_SERVER;
		$host = array();
		$host = explode(':', $_SERVER['HTTP_HOST']);
		
		if ($this->secobj->header('Set-Cookie: '.$this->set['Cookiepre'].$key.'='.strip_tags($value).'; path='.$patch.'; domain='.(strpos($host[0], '.') === false ? '' : $host[0]).'; expires='.gmstrftime('%A, %d-%b-%Y %H:%M:%S GMT', ($ttl != 0 ? $this->set['Time'] + $ttl : 0)).'')) {
			return true;
		} else {
			return false;
		}
	}
	
	public function clientdata($key, $val = '', $forcecookie = false) {
		global $_SESSION;
		
		if (!$val) {
			if ($this->set['Record'] || $this->parseRecords()) {
				if (isset($this->clientrecords[$key])) {
					return $this->clientrecords[$key];
				}
			}
		} else {
			if (($this->set['Record'] == 'COOKIE' || $forcecookie) && $this->writecookie($key, $val, $this->set['SessionTicketExpired'])) {
				$this->clientrecords[$key] = $val;
				return true;
			} elseif ($this->set['Record'] == 'PHPSESSION' && ($_SESSION[$key] = $val)) {
				$this->clientrecords[$key] = $val;
				return true;
			}
		}

		return false;
	}
	
	public function getSessionInfo($full = false) {
		if ($full)
			return $this->user + array('RecordFrom' => $this->set['Record'], 'Token' => $this->getLastToken());
		else
			return array('RecordFrom' => $this->set['Record'], 'TimeOffset' => $this->user['TimeOffset'], 'MemberID' => $this->user['MemberID'], 'Points' => $this->user['Points'], 'Avatar' => $this->user['Avatar'], 'MemberName' => $this->user['MemberName'], 'MemberUsername' => $this->user['MemberUsername'], 'Alert' => $this->user['Alert'], 'Token' => $this->getLastToken(), 'IP' => $this->user['IP'], 'Data' => $this->user['UserData']);
	}
	
	private function genToken($tokenrow = '', $justloop = false) {
		$tokentemp = '';
		$token = array();
		$map = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','i','s','t','u','v','w','x','y','z',
					   'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','I','S','T','U','V','W','X','Y','Z',
					   '1','2','3','4','5','6','7','8','9','0');

		$token = explode('|', $tokenrow);
		
		if (!$justloop && !isset($token[$this->set['MaxToken'] - 1])) {
			shuffle($map);
			
			if ($token[0]) {
				$token[] = substr(implode('', $map), 0, 3);
			} else {
				$token[0] = substr(implode('', $map), 0, 3);
			}
		}
		
		if ($token[1]) { // I hate looping, so here say good bye for each while.
			$tokentemp = $token[0];
			$token[] = $tokentemp;
			unset($token[0]);
		}
		
		return $token;
	}
	
	private function getLastToken() {
		if (!$this->set['SecletedToken'] && is_array($this->user['Token'])) {
			foreach ($this->user['Token'] AS $key => $val) {
				if ($val) {
					$this->set['SecletedToken'] = $val;
					return $val;
				}
			} // If you not lucky, foreach will helps you.
		} else {
			return $this->set['SecletedToken'];
		}

		return false;
	}
	
	public function checkToken($token) {
		if ($validtoken = $this->secobj->isString($token, 3, 1)) {
			if (in_array($validtoken, $this->user['Token']) && !$this->set['SessionUpdated']) {
				foreach ($this->user['Token'] AS $key => $val) {
					if ($this->user['Token'][$key] == $validtoken) {
						unset($this->user['Token'][$key]);
						return true;
					}
				}
			}
		}
		
		return false;
	}
	
	public function checkPeriod() {
		$remain = $this->user['ProtectionPeriod'] - $this->set['Time'];
		
		if ($remain > 0) {
			return $remain;
		}
		
		return false;
	}
	
	public function setPeriod($sec) {
		if (!$this->set['SessionUpdated']) {
			if ($sec < $this->set['SessionExpired']) {
				$this->imported['AddProtectionPeriod'] = intval($sec);
			} else {
				$this->imported['AddProtectionPeriod'] = $this->set['SessionExpired'];
			}
			$this->set['PeriodChanged'] = true;
			return true;
		} else {
			$this->error = "ERROR_SESSION_SETPERIOD_SESSIONALREADYUPDATED";
		}
		
		return false;
	}
	
	private function checkTicketIP() {
		if ($this->user['MemberID'] && isset($this->user['TicketIP'][0])) {
			foreach($this->user['IP'] AS $key => $val) {
				if ($this->user['TicketIP'][$key] != $val) {
					return false;
				}
			}
		}
		
		return true;
	}
	
	private function getSession() {
		$member_sql = $visitor_sql = $sessionkey = $validsessionkey = $sessionkeyrow = $tickethash = $sql = '';
		$sessionrow = $sessiontrimed = $echoarray = array();
		$memberid = 0;
		
		if (!$this->set['SessionGot'] && $this->set['Record']) {
			// Get or gen session key
			if (!($sessionkey = $this->secobj->parseHashFromClient($this->clientdata('factor'), true))) {
				if ($this->set['Record'] == 'PHPSESSION' && ($sessionkeyrow = $this->clientdata('PublicKey'))) {
					$sessionkey = $this->secobj->parseHashFromClient($sessionkeyrow, true);
					$this->clientdata('factor', $sessionkeyrow, true);
				} elseif ($this->set['Record'] == 'COOKIE' && ($sessionkeyrow = $this->getClientHash(true))) {
					$sessionkey = $this->secobj->parseHashFromClient($sessionkeyrow, true);
					$this->clientdata('factor', $sessionkeyrow, true);
				}
			}
			
			if ($sessionkey) {
				if ($echoarray = explode("\t", base64_decode($this->clientdata('echo')), 2)) {
					$memberid = $this->secobj->isID($echoarray[0], 16);
					$tickethash = $this->secobj->parseHashFromClient($echoarray[1], true);
				}
				
				// Check for user & visitor maybe
				$member_sql = 'SELECT `s`.`sessionID`, `s`.`sessionkey`, `s`.`alert`, `s`.`bornin`, 
										`s`.`tokenring`, `s`.`lastsubmit`, `s`.`loginfailcount`, `s`.`protectionperiod`, `s`.`ip`, `s`.`pagevisited`, 
										`st`.`logindate`, `st`.`memberID`, `st`.`clientIP1`, `st`.`clientIP2`, `st`.`clientIP3`, `st`.`clientIP4`, `st`.`clientIP5`, `st`.`clientIP6`, `st`.`clientIP7`, `st`.`clientIP8`, 
										`m`.`avatar`, `m`.`friendlyname`, `m`.`username`, `m`.`timeoffset`, `m`.`points`, `m`.`settings`, `m`.`data`, 
										`mg`.`membergroupID`, `mg`.`membergroupName`, `mg`.`settings` AS `groupsetting`, `mg`.`permissions`, 
										`eg`.`exgDatas`, `eg`.`exgPermissions` 
										FROM `'.$this->dbobj->pre.'sessions` AS `s` 
										LEFT JOIN `'.$this->dbobj->pre.'sessiontickets` AS `st` ON `st`.`memberID` = \''.$memberid.'\' AND `st`.`tickethash` = \''.$tickethash.'\' 
										LEFT JOIN `'.$this->dbobj->pre.'members` AS `m` ON `m`.`memberID` = `st`.`memberID` 
										LEFT JOIN `'.$this->dbobj->pre.'exgroups` AS `eg` ON `eg`.`exgID` = `m`.`exgroupID` 
										LEFT JOIN `'.$this->dbobj->pre.'membergroups` AS `mg` ON `mg`.`membergroupID` = `m`.`membergroupID` 
										WHERE `s`.`sessionkey` = \''.$sessionkey.'\'';
				
				// Check for visitor
				$visitor_sql = 'SELECT `s`.`sessionID`, `s`.`sessionkey`, `s`.`alert`, `s`.`bornin`, `s`.`tokenring`, `s`.`lastsubmit`, `s`.`loginfailcount`, `s`.`protectionperiod`, `s`.`ip`, `s`.`pagevisited` 
								FROM `'.$this->dbobj->pre.'sessions` AS `s` 
								WHERE `s`.`sessionkey` = \''.$sessionkey.'\'';
				
				if ($memberid && $tickethash) {
					$sql = $member_sql;
				} else {
					$sql = $visitor_sql;
				}

				if ($sql) {
					if ($this->set['WorkingMode'] != 'Updateonly') {
						if ($sessionrow = $this->dbobj->query_first($sql)) { // First, friendly try
							$this->user = array('SessionID' => $sessionrow['sessionID'],
												'SessionKey' => $sessionrow['sessionkey'],
												'TimeOffset' => $sessionrow['timeoffset'],
												'Alert' => $sessionrow['alert'],
												'BornIn' => $sessionrow['bornin'],
												'TicketHash' => $tickethash,
												'TicketTime' => $sessionrow['logindate'],
												'TicketIP' => array($sessionrow['clientIP1'], 
																	$sessionrow['clientIP2'], 
																	$sessionrow['clientIP3'], 
																	$sessionrow['clientIP4'], 
																	$sessionrow['clientIP5'], 
																	$sessionrow['clientIP6'], 
																	$sessionrow['clientIP7'], 
																	$sessionrow['clientIP8']),
												'MemberID' => $sessionrow['memberID'],
												'Avatar' => $sessionrow['avatar'],
												'Points' => $sessionrow['points'] ? $sessionrow['points'] : 0,
												'MemberName' => $sessionrow['friendlyname'],
												'MemberUsername' => $sessionrow['username'],
												'SessionCreated' => $sessionrow['bornin'],
												'IP' => explode('-', $sessionrow['ip']),
												'Token' => $this->genToken($sessionrow['tokenring']),
												'LastPublished' => $sessionrow['lastsubmit'],
												'LoginFailed' => $sessionrow['loginfailcount'],
												'ProtectionPeriod' => $sessionrow['protectionperiod'],
												'PageVisted' => $sessionrow['pagevisited'] ? $sessionrow['pagevisited'] : 1,
												'MemberGroup' => $sessionrow['membergroupName'],
												'MemberGroupID' => $sessionrow['membergroupID'],
												'MemberGroupSetting' => $this->secobj->readPackedData($sessionrow['groupsetting']),
												'Permissions' => $this->secobj->readPackedData($sessionrow['permissions']),
												'UserSetting' => $this->secobj->readPackedData($sessionrow['settings']),
												'UserData' => $this->secobj->readPackedData($sessionrow['data']),
												'ExGroupDatas' => $this->secobj->readPackedData($sessionrow['exgDatas']),
												'ExGroupPermissions' => $this->secobj->readPackedData($sessionrow['exgPermissions']));
						} elseif ($this->updateSession($sessionkey) && ($sessionrow = $this->dbobj->query_first($sql))) { // Then, angrily write then try load  
							$this->user = array('SessionID' => $sessionrow['sessionID'],
												'SessionKey' => $sessionrow['sessionkey'],
												'TimeOffset' => $sessionrow['timeoffset'],
												'Alert' => $sessionrow['alert'],
												'BornIn' => $sessionrow['bornin'],
												'TicketHash' => $tickethash,
												'TicketTime' => $sessionrow['logindate'],
												'TicketIP' => array($sessionrow['clientIP1'], 
																	$sessionrow['clientIP2'], 
																	$sessionrow['clientIP3'], 
																	$sessionrow['clientIP4'], 
																	$sessionrow['clientIP5'], 
																	$sessionrow['clientIP6'], 
																	$sessionrow['clientIP7'], 
																	$sessionrow['clientIP8']),
												'MemberID' => $sessionrow['memberID'],
												'Avatar' => $sessionrow['avatar'],
												'Points' => $sessionrow['points'] ? $sessionrow['points'] : 0,
												'MemberName' => $sessionrow['friendlyname'],
												'MemberUsername' => $sessionrow['username'],
												'SessionCreated' => $sessionrow['bornin'],
												'IP' => explode('-', $sessionrow['ip']),
												'Token' => $this->genToken($sessionrow['tokenring'], true),
												'LastPublished' => $sessionrow['lastsubmit'],
												'LoginFailed' => $sessionrow['loginfailcount'],
												'ProtectionPeriod' => $sessionrow['protectionperiod'],
												'PageVisted' => $sessionrow['pagevisited'] ? $sessionrow['pagevisited'] : 1,
												'MemberGroup' => $sessionrow['membergroupName'],
												'MemberGroupID' => $sessionrow['membergroupID'],
												'MemberGroupSetting' => $this->secobj->readPackedData($sessionrow['groupsetting']),
												'Permissions' => $this->secobj->readPackedData($sessionrow['permissions']),
												'UserSetting' => $this->secobj->readPackedData($sessionrow['settings']),
												'ExGroupDatas' => $this->secobj->readPackedData($sessionrow['exgDatas']),
												'ExGroupPermissions' => $this->secobj->readPackedData($sessionrow['exgPermissions']));
						}
						
						if ($this->user['SessionID'] && !$this->set['Alert']) {
							if (!$this->checkTicketIP()) {
								$this->set['Alert'] = 'USER_LOGIN_VIA_OLD_TICKET';
							} elseif ($this->set['Record'] == 'PHPSESSION') {
								$this->set['Alert'] = 'USER_PUBLIC_CREDENTIAL';
							} elseif ($this->user['ProtectionPeriod'] > $this->set['Time']) {
								$this->set['Alert'] = 'USER_LIMIT_PERIOD';
							} elseif ($this->user['LoginFailed']) {
								$this->set['Alert'] = 'USER_LOGIN_FAILED_EVIL';
							}
						}
					} else {
						$this->user = array('SessionKey' => $sessionkey,
											'IP' => implode('-', $this->secobj->getUserIP()));
					}
					
					$this->set['SessionGot'] = true;
					return true;
				}
			}
		} else {
			return true;
		}

		return false;
	}
	
	public function setUserData($key, $val) {
		if ($this->set['SessionGot'] && $this->user['MemberID']) { // Only set data when session got what they want.
			if ($this->secobj->changePackedData($key, $val, $this->user['UserData'])) {
				$this->user['UserData'][$key] = $val;
				$this->user['UserUpdateNeeded'] = $this->user['UserDataUpdateNeeded'] = true; // Set tag to let app know we need to update user data.
				return true;
			} 
		} 
		
		return false;
	} 
	
	public function setUserSetting($key, $val) {
		if ($this->set['SessionGot'] && $this->user['MemberID']) {
			if ($this->secobj->changePackedData($key, $val, $this->user['UserSetting'])) {
				$this->user['UserSetting'][$key] = $val;
				$this->user['UserUpdateNeeded'] = $this->user['UserSettingUpdateNeeded'] = true;
				return true;
			} 
		} 
		
		return false;
	} 
	
	public function setUserPoints($action, $amount) {
		if ($this->set['SessionGot'] && $this->user['MemberID']) {
			switch($action) {
				case '+':
					$this->user['Points'] += $amount;
					$this->user['UserUpdateNeeded'] = $this->user['UserPointsUpdateNeeded'] = true;
					break;
					
				case '-':
					if ($amount < $this->user['Points'])
						$this->user['Points'] -= $amount;
					else
						$this->user['Points'] = '0';
					$this->user['UserUpdateNeeded'] = $this->user['UserPointsUpdateNeeded'] = true;
					break;
					
				default:
					break;
			}
			
			return true;
		} 
		
		return false;
	} 
	
	private function updateSession($sessionkey = '') {
		$updateuser = $sessionrow = array();
		
		if ($this->user['MemberID'] && $this->user['UserUpdateNeeded']) {
			if ($this->user['UserDataUpdateNeeded']) {
				$updateuser['data']		= $this->secobj->repackPackedData($this->user['UserData']);
			}
			
			if ($this->user['UserSettingUpdateNeeded']) {
				$updateuser['settings']	= $this->secobj->repackPackedData($this->user['UserSetting']);
			}
			
			if ($this->user['UserPointsUpdateNeeded']) {
				$updateuser['points']	= ($this->user['Points']);
			}
			
			$this->dbobj->query_update('members', $updateuser, '`memberID` = \''.$this->secobj->isID($this->user['MemberID'], 16).'\'');
		}
		
		if (!$this->set['SessionUpdated']) {
			switch($this->set['WorkingMode']) {
				case 'Readonly':
					if ($this->set['PeriodChanged'] && $this->user['SessionKey']) {
						// Update without Token Ring
						$sessionrow = array('memberID' => $this->user['MemberID'],
											'alert' => $this->set['Alert'],
											'lastactivity' => $this->set['Time'],
											'ip' => implode('-', $this->secobj->getUserIP()),
											'pagevisited' => $this->user['PageVisted'] + 1,
											'protectionperiod' => $this->imported['AddProtectionPeriod'] ? $this->set['Time'] + $this->imported['AddProtectionPeriod'] : $this->user['ProtectionPeriod'],
											'loginfailcount' =>  $this->imported['AddLoginFailed'] ? $this->user['LoginFailed'] + $this->imported['AddLoginFailed'] : $this->user['LoginFailed'],
											'lastsubmit' => $this->imported['AddPublished'] ? $this->user['LastPublished'] + $this->imported['AddPublished'] : $this->user['LastPublished']);
					}
					break;
					
				default:
					if ($sessionkey) {
						$sessionrow = array('memberID' => 0,
											'alert' => $this->set['Alert'],
											'bornin' => $this->set['Time'],
											'lastactivity' => $this->set['Time'],
											'ip' => implode('-', $this->secobj->getUserIP()),
											'sessionkey' => $sessionkey,
											'pagevisited' => 1,
											'protectionperiod' => 0,
											'loginfailcount' => 0,
											'lastsubmit' => 0,
											'tokenring' => implode('|', $this->genToken()));
					} else {
						$sessionrow = array('memberID' => $this->user['MemberID'],
											'alert' => $this->set['Alert'],
											'lastactivity' => $this->set['Time'],
											'ip' => implode('-', $this->secobj->getUserIP()),
											'pagevisited' => $this->user['PageVisted'] + 1,
											'protectionperiod' => $this->imported['AddProtectionPeriod'] ? $this->set['Time'] + $this->imported['AddProtectionPeriod'] : $this->user['ProtectionPeriod'],
											'loginfailcount' =>  $this->imported['AddLoginFailed'] ? $this->user['LoginFailed'] + $this->imported['AddLoginFailed'] : $this->user['LoginFailed'],
											'lastsubmit' => $this->imported['AddPublished'] ? $this->user['LastPublished'] + $this->imported['AddPublished'] : $this->user['LastPublished'],
											'tokenring' => implode('|', $this->user['Token']));
					}
					break;
			
			}
			
			if ($sessionrow['lastactivity']) { // Don't remove
				if ($sessionrow['sessionkey']) {
					if ($this->dbobj->query_insert('sessions', $sessionrow)) {
						$this->set['SessionUpdated'] = true;
						return true;
					}
				} else {
					if ($this->dbobj->query_update('sessions', $sessionrow, '`sessionkey` = \''.$this->secobj->isHash($this->user['SessionKey']).'\'')) {
						$this->set['SessionUpdated'] = true;
						return true;
					}
				}
			}
		}
		
		return false;
	}
	
	public function isBanned($keyword = '') {
		$validuserip = $checkbanned = array();
		
		if (!$this->user['IsBanChecked']) {
			$this->user['IsBanChecked'] = 1;
			$validkeyword = $this->secobj->isString($keyword, 16, 1);
			
			$validuserip = array($this->dbobj->escape($this->user['IP'][0]), 
								$this->dbobj->escape($this->user['IP'][1]),
								$this->dbobj->escape($this->user['IP'][2]),
								$this->dbobj->escape($this->user['IP'][3]),
								$this->dbobj->escape($this->user['IP'][4]),
								$this->dbobj->escape($this->user['IP'][5]),
								$this->dbobj->escape($this->user['IP'][6]),
								$this->dbobj->escape($this->user['IP'][7]));
								
			// I know, I hate following code too, AND ALSO, mysql may will hate this, but there is no way better than this way (Check ip with just one query and without to be bother with IO)
			if ($checkbanned = $this->dbobj->query_first('SELECT `expire` FROM `'.$this->dbobj->pre.'banned` WHERE (
														(`bannedIP1` = \''.$validuserip[0].'\' OR `bannedIP1` = \'ALL\') AND 
														(`bannedIP2` = \''.$validuserip[1].'\' OR `bannedIP2` = \'ALL\') AND 
														(`bannedIP3` = \''.$validuserip[2].'\' OR `bannedIP3` = \'ALL\') AND 
														(`bannedIP4` = \''.$validuserip[3].'\' OR `bannedIP4` = \'ALL\') AND 
														(`bannedIP5` = \''.$validuserip[4].'\' OR `bannedIP5` = \'ALL\') AND 
														(`bannedIP6` = \''.$validuserip[5].'\' OR `bannedIP6` = \'ALL\') AND 
														(`bannedIP7` = \''.$validuserip[6].'\' OR `bannedIP7` = \'ALL\') AND 
														(`bannedIP8` = \''.$validuserip[7].'\' OR `bannedIP8` = \'ALL\') AND 
														`keyword` LIKE \'%'.$this->dbobj->escape($validkeyword).'%\') AND `expire` > \''.$this->set['Time'].'\'')) {
				return $this->user['IsBanned'] = $checkbanned['expire'];
			}
		} else {
			return $this->user['IsBanned'];
		}
		
		return false;
	}
	
	public function update() {
		return $this->updateSession();
	}
	
	public function signin($token, $username, $password) {
		$sql = $validusername = $validemail = $validpass = $clientkey = '';
		$userrow = $userinfo = array();
		$ticktid = $periodremain = 0;
		
		if (!$this->user['MemberID']) {
			if ($this->set['SessionGot'] && !$this->set['SessionUpdated']) {
				if (!($periodremain = $this->checkPeriod())) {
					if ($this->checkToken($token)) {
						if (!($validusername = $this->secobj->isUsername($username)) && !($validemail = $this->secobj->isEmail($username))) {
							$this->error = 'ERROR_SESSION_VERIFY_INVALID_ACCOUNTNAME';
						} elseif (!($validpass = $this->secobj->hashPassword($password))) {
							$this->error = 'ERROR_SESSION_VERIFY_INVALID_PASSWORD';
						}
						
						if ($validpass) {
							// Thanks to the Jed in Newsfan.net gives idea on how to fastly verify user in huge mass user table
							if ($validusername) {
								$sql = 'SELECT `memberID`, `friendlyname`, `isactivated`, `isbanned`, `password`, `avatar`, `timeoffset`, `points`, `settings`, `data` FROM `'.$this->dbobj->pre.'members` WHERE (`username` = \''.$this->dbobj->escape($validusername).'\' OR `friendlyname` = \''.$this->dbobj->escape($validusername).'\')';
							} elseif ($validemail) {
								$sql = 'SELECT `memberID`, `friendlyname`, `isactivated`, `isbanned`, `password`, `avatar`, `timeoffset`, `points`, `settings`, `data` FROM `'.$this->dbobj->pre.'members` WHERE `email` = \''.$this->dbobj->escape($validemail).'\'';
							} else {
								return false;
							}
							
							if (!$this->isBanned($validusername)) {
								if (($userrow = $this->dbobj->query_first($sql)) && ($userrow['password'] == $validpass)) {
									if (!$userrow['isbanned']) {
										if ($userrow['isactivated']) {
											if ($clientkey = $this->secobj->randSeed(5).$this->getClientHash(true)) {
												$userinfo = array('memberID' => $userrow['memberID'],
																'tickethash' => $this->secobj->hashPassword($clientkey, true),
																'logindate' => $this->set['Time'],
																'clientIP1' => $this->user['IP'][0],
																'clientIP2' => $this->user['IP'][1],
																'clientIP3' => $this->user['IP'][2],
																'clientIP4' => $this->user['IP'][3],
																'clientIP5' => $this->user['IP'][4],
																'clientIP6' => $this->user['IP'][5],
																'clientIP7' => $this->user['IP'][6],
																'clientIP8' => $this->user['IP'][7]);
												
												if (($ticktid = $this->dbobj->query_insert('sessiontickets', $userinfo)) && $this->clientdata('echo', base64_encode($userinfo['memberID']."\t".$this->secobj->genHashForClient($clientkey)))) {					
													$this->user['MemberGroupSetting'] = $this->secobj->readPackedData($userrow['groupsetting']);
													$this->user['Permissions'] = $this->secobj->readPackedData($userrow['permissions']);
													$this->user['UserSetting'] = $this->secobj->readPackedData($userrow['settings']);
													$this->user['UserData'] = $this->secobj->readPackedData($userrow['data']);
													$this->user['MemberID'] = $userrow['memberID'];
													$this->user['MemberName'] = $userrow['friendlyname'];
													$this->user['TicketHash'] = $userinfo['tickethash'];
													
													$this->setUserData('LastLoginIP', $this->user['IP']);
													$this->setUserData('LastLoginTime', $this->set['Time']);
													$this->setUserData('LastLoginTicketID', $ticktid);
													
													$this->dbobj->query('DELETE FROM `'.$this->dbobj->pre.'sessions` WHERE `sessionID` <> \''.$this->user['SessionID'].'\' AND `lastactivity` < \''.intval($this->set['Time'] - $this->set['SessionExpired']).'\' LIMIT 50');
													$this->dbobj->query('DELETE FROM `'.$this->dbobj->pre.'sessiontickets` WHERE `memberID` = \''.$userrow['memberID'].'\' AND `logindate` < \''.intval($this->set['Time'] - $this->set['SessionTicketExpired']).'\' AND `tickethash` <> \''.$userinfo['tickethash'].'\' LIMIT 50');		
													
													return true;
												} else {
													$this->error = 'ERROR_SESSION_VERIFY_PASSED_BUT_WRITTEN_FAILED';
												}
											}
										} else {
											$this->error = 'ERROR_SESSION_VERIFY_ACCOUNT_NOT_VERIFY';
										}
									} else {
										$this->error = 'ERROR_SESSION_VERIFY_ACCOUNT_BANNED';
									}
								} else {
									$this->imported['AddLoginFailed'] = 1;
									
									if ($this->user['LoginFailed'] + 1 >= $this->set['LoginFailToBan']) {
										$this->setPeriod($this->set['LoginFailBanTime'] * (($this->user['LoginFailed'] - $this->set['LoginFailToBan']) + 2));
									}
									
									$this->error = 'ERROR_SESSION_VERIFY_BAD_ACCOUNT|'.$this->set['LoginFailToBan'];
								}
							} else {
								$this->error = 'ERROR_SESSION_VERIFY_NETBANNED';
							}
						}
					} else {
						$this->error = 'ERROR_TOKEN_REFUSED';
					}
				} else {
					$this->error = 'ERROR_SESSION_VERIFY_SITE_REFUSED|'.$periodremain;
				}
			} else {
				$this->error = 'ERROR_SESSION_VERIFY_SESSION_NOT_READY';
			}
		} else {
			$this->error = 'ERROR_SESSION_VERIFY_ALREADY_LOGIN';
		}
		
		return false;
	}
	
	public function signout($token) {
		if ($this->set['SessionGot'] && !$this->set['SessionUpdated']) {
			if ($this->user['MemberID']) {
				if ($this->checkToken($token)) {
					if ($this->user['TicketHash']) {
						if ($this->dbobj->query('DELETE FROM `'.$this->dbobj->pre.'sessiontickets` WHERE `memberID` = \''.$this->user['MemberID'].'\' AND (`tickethash` = \''.$this->user['TicketHash'].'\' OR `logindate` < \''.intval($this->set['Time'] - $this->set['SessionTicketExpired']).'\') LIMIT 50')) {
							$this->dbobj->query('DELETE FROM `'.$this->dbobj->pre.'sessions` WHERE `sessionID` <> \''.$this->user['SessionID'].'\' AND `lastactivity` < \''.intval($this->set['Time'] - $this->set['SessionExpired']).'\' LIMIT 50');
							if ($this->clientdata('echo', '0')) {
								$this->user['MemberID'] = $this->user['NewPMID'] = 0;
								$this->user['MemberName'] = $this->user['TicketHash'] = '';
								return true;
							} else {
								$this->error = 'ERROR_SESSION_SIGNOUT_CANNOT_SEND_SETTING';
							}
						} else {
							return true;
						}
					} else {
						$this->error = 'ERROR_SESSION_SIGNOUT_TICKETHASH_NOT_DEFINED';
					}
				} else {
					$this->error = 'ERROR_TOKEN_REFUSED';
				}
			} else {
				$this->error = 'ERROR_SESSION_SIGNOUT_ALREADY_LOGOUT';
			}
		} else {
			$this->error = 'ERROR_SESSION_SIGNOUT_SESSION_NOT_READY';
		}
		
		return false;
	}
}

?>