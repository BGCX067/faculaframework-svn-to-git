<?php 
/*****************************************************************************
	Facula Framework SMTP Sender API
	
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

class mail {
	private $tempobj = null;
	private $secobj = null;
	private $sesobj = null;
	private $oopsobj = null;
	
	public $error = '';
	
	private $serverhandle = null;
	private $server = '';
	
	private $set = array();
	private $pool = array();
	
	public function __construct() {
		global $ui, $oops, $sec, $ses;
		
		if (($this->tempobj = $ui) && ($this->oopsobj = $oops) && ($this->secobj = $sec) && ($this->sesobj = $ses)) {
			$this->swap = array('Required' => array(
												'SiteSetting' => array('mail' => array(),
																		'general' => array()
																		)
											),
								'IsGhost' => true,
								'QueueTimeout' => 10,
								);
			
			register_shutdown_function(array(&$this, 'sendQueue'));
			return true;
		}
		
		return false;
	}
	
	public function _init() {
		if ($this->swap['Required']['Filled']) {
			$this->set = array('Time' => $this->secobj->time(),
								'Servers' => $this->secobj->readCfgStr($this->swap['Required']['SiteSetting']['mail']['Servers']),
								'Site' => $this->swap['Required']['SiteSetting']['general']);
			if ($this->set['Servers'] && function_exists('fsockopen')) {
				$this->pool['ReadyToSend'] = true;
			}
								
			return true;
		}
		
		return false;
	}
	
	public function oops($erstring, $exit = false) {
		$error_codes = array('MAIL_VAR_ALREADY_ASSGINED' => 'You trying to assgin a value for a key, but the key already assgined.');

		if ($this->oopsobj->pushmsg($error_codes) && $this->oopsobj->ouch($erstring, $exit)) {
			return true;
		}
		
		return false;
	}
	
	public function connect() {	
		if (!empty($this->set['Servers']) && !$this->server) {
			foreach ($this->set['Servers'] AS $key => $val) {
				if (($this->server = $val) && $this->doConnect()) {
					$this->error = '';
					break;
				}
			}

			if ($this->serverhandle) {
				return true;
			}
		}
		return false;
	}
	
	public function doConnect() {
		$errorno = $timeout = 0;
		$server = array();
		$callback = $error = '';

		if ($this->serverhandle && $this->isConnected()) {
			return true;
		} else {
			$this->serverhandle = 0;	
			list($this->set['Host'], $this->set['ShowName'], $this->set['Address'], $this->set['AdminMail'], $this->set['Username'], $this->set['Password'], $this->set['AuthType'], $this->set['Timeout']) = $this->secobj->parseCfgStr($this->server);
			$server = explode(':', $this->set['Host']);
			if ($server[0] && $this->set['Username'] && $this->set['Password'] && $this->set['Address']) {
				$server[1] = !$server[1] ? '25' : $server[1];
				$this->set['AuthType'] = $this->set['AuthType'] != 'EHLO' && $this->set['AuthType'] != 'HELO' ? 'HELO' : $this->set['AuthType'];
				if (!($this->serverhandle = fsockopen($server[0], $server[1], $errorno, $error, $this->set['Timeout'] > 1 && $this->set['Timeout'] < 3 ? $this->set['Timeout'] : 3))) {
					$this->serverhandle = 0;
					$this->error = 'MAIL_CONNECT_FAILED|'.$server[0];
					
					return false;
				} else {
					stream_set_blocking($this->serverhandle, true);
				}
							
				$callback = $this->getCallBack(3);
				if (!$this->serverhandle || $callback != '220') {
					$this->bye();
					$this->error = "MAIL_CONNECT_FAILED|Init Connection: {$callback}";
					$this->serverhandle = 0;
				} elseif (!$this->set['Username'] || !$this->set['Password'] || ($callback = $this->put("{$this->set['AuthType']} {$this->set['Username']}") && ($callback != '220' && $callback != '250'))) {
					$this->bye();
					$this->error = "MAIL_CONNECT_FAILED|Set Auth Type: {$callback}";
					$this->serverhandle = 0;
				} elseif ($this->put('AUTH LOGIN') != '334') {
					$this->bye();
					$this->error = "MAIL_CONNECT_FAILED|Auth Login: {$callback}";
					$this->serverhandle = 0;
				} elseif ($this->put(base64_encode($this->set['Username'])) != '334') {
					$this->bye();
					$this->error = "MAIL_CONNECT_FAILED|Checking Mail Username: {$callback}";
					$this->serverhandle = 0;
				} elseif ($this->put(base64_encode($this->set['Password'])) != '235') {
					$this->bye();
					$this->error = "MAIL_AUTH_FAILED|Checking Mail Password: {$callback}";
					$this->serverhandle = 0;
				} elseif ($this->put('MAIL FROM: <'.preg_replace('/.*\<(.+?)\>.*/', '\\1', $this->set['Address']).'>') != '250') {
					$this->bye();
					$this->error = "MAIL_CONFIG_FAILED|Setting Mail From: {$callback}";
					$this->serverhandle = 0;
				} else {
					return true;
				}
			} else {
				$this->error = 'MAIL_NO_SMTP_HOST';
			}
		}
		
		return false;
	}
	
	private function isConnected() {
		$link = array();
		if ($this->serverhandle && $link = stream_get_meta_data($this->serverhandle)) {
			if (!$link['timed_out']) return true;
		}
		
		return false;
	}
	
	public function send(&$tos, &$vals, $mailtpl) {
		$mailtplcontent = '';
		
		if (!$this->pool['ReadyToSend']) {
			$this->error = 'ERROR_MAIL_NOTREADY';
			return false; 
		}
		
		if (is_array($vals) && !empty($tos)) {
			foreach($vals AS $key => $val) {
				$this->assign($key, $val);
			}
			
			if ($mailtplcontent = $this->fecthmailtpl($mailtpl)) {
				$this->pool['Queue'][] = array('Tos' => $tos, 'Vals' => $vals, 'TplContent' => $mailtplcontent);
				
				return true;
			}
		}
		
		return false;
	}
	
	public function sendQueue() {
		$mailout = array();
		$boundary = $callback = '';
		
		if (isset($this->pool['Queue'][0])) {
			if ($this->isConnected() || $this->connect()) {
				foreach($this->pool['Queue'] AS $qk => $qv) {
					if (is_array($qv['Tos']) && is_array($qv['TplContent'])) {
						foreach($qv['Tos'] AS $key => $val) {$this->addCC($val);}
						
						if ($qv['TplContent'][0] && $qv['TplContent'][1]) {
							$boundary = '----=_NextPart_FACULA_'.base64_encode($this->secobj->randSeed(3));
							$mailout = explode("\n", $this->makeMailHead($boundary, $qv['TplContent'][0]).$this->makeMailBody($boundary, $qv['TplContent'][1]));
							
							if (!empty($mailout)) {
								if ($this->doTos()) {
									if ($this->put('DATA') == '354') {
										foreach ($mailout AS $key => $val) {
											if (!$this->put($val, true)) {
												$this->error = 'MAIL_FAILED_SEND_MAIL_CONTENT';
											}
										}
										
										if ($this->put('.') != '250') {
											$this->error = 'MAIL_UNEXPECTED_ERROR|'.$qk;
										}
									}
								}
							}
						}
					}
				}
				$this->bye();
				
				return true;
			}
			return false;
		}
		
		return true;
	}
	
	public function addCC($address) {
		if ($this->secobj->isEmail($address)) {
			$this->set['CCs'][] = $address;
			
			return true;
		}
		
		return false;
	}
	
	public function assign($key, $val) {
		if ($this->tempobj->assign($key, $this->secobj->filterTextToWebPage($val))) {
			return true;
		} else {
			$this->error = 'MAIL_ASSGIN_FAILED';
		}

		return false;
	}
	
	public function bye() {
		if ($this->isConnected()) {
			$this->put('QUIT');
			fclose($this->serverhandle);
			$this->serverhandle = 0;
			return true;
		} else {
			fclose($this->serverhandle);
			$this->serverhandle = 0;
		}

		return true;
	}
	
	private function fecthmailtpl($tpl) {
		$mail = array();
		$tmpmail = $mailcontent = $mailtitle = '';
		
		if ($tmpmail = $this->tempobj->display('mail.'.$tpl, true)) {
			$mail = explode('<***********>', $tmpmail.'                                                                '); // Add Space to avoid bit eating
			
			if ($mail[0] && $mail[1]) {
				return $mail;
			}
		}
		
		return false;
	}
	
	private function addHeadLine($key, $val) {
		return $key.': '.$val."\n";
	}
	
	private function getMailTime() {
		return date('D, d M y H:i:s O', $this->set['Time']);
	}
	
	private function makeMailHead($boundary, $title) {
		global $_SERVER;
		
		$mailhead = "";
		$sendfrom = $user = array();
		
		$user = $this->sesobj->getSessionInfo();
		
		if ($this->set['AdminMail']) {
			$mailhead .= $this->addHeadLine('Return-Path', '<'.preg_replace('/.*\<(.+?)\>.*/', '\\1', $this->set['AdminMail']).'>');
		} else {
			$mailhead .= $this->addHeadLine('Return-Path', '<'.preg_replace('/.*\<(.+?)\>.*/', '\\1', $this->set['Address']).'>');
		}
		
		if (!$user['MemberID']) {
			$mailhead .= $this->addHeadLine('Message-ID', 'SYSTEM.'.trim($this->secobj->randSeed(3)."@{$_SERVER['HTTP_HOST']}"));
		} else {
			$mailhead .= $this->addHeadLine('Message-ID', 'USER_'.$user['MemberID'].'.'.trim($this->secobj->randSeed(3)."@{$_SERVER['HTTP_HOST']}"));
		}
		
		
		if ($this->set['ShowName']) {
			$mailhead .= $this->addHeadLine('From', '"'.$this->set['ShowName'].'" <'.$this->set['Address'].'>');
		} else {
			$mailhead .= $this->addHeadLine('From', '<'.$this->set['Address'].'>');
		}
		
		if ($this->set['AdminMail']) {
			$mailhead .= $this->addHeadLine('Reply-To', '<'.$this->set['AdminMail'].'>');
			$mailhead .= $this->addHeadLine('Errors-To', '<'.$this->set['AdminMail'].'>');
		} else {
			$mailhead .= $this->addHeadLine('Reply-To', '<'.$this->set['Address'].'>');
			$mailhead .= $this->addHeadLine('Errors-To', '<'.$this->set['Address'].'>');
		}
		
		if (isset($this->set['CCs'][1])) {
			$mailhead .= $this->addHeadLine('To', 'undisclosed-recipients:;');
		} else {
			$mailhead .= $this->addHeadLine('To', "<{$this->set['CCs'][0]}>");
		}

		if ($title) {
			$mailhead .= $this->addHeadLine('Subject', $this->addUTF8Tag(base64_encode($title)));
		} else {
			$mailhead .= $this->addHeadLine('Subject', $this->addUTF8Tag(base64_encode('Untitled')));
		}
		
		$mailhead .= $this->addHeadLine('Date', $this->getMailTime());
		$mailhead .= $this->addHeadLine('MIME-Version', '1.0');
		
		$mailhead .= $this->addHeadLine('X-Priority', '3');
		$mailhead .= $this->addHeadLine('X-MSMail-Priority', 'Normal');
		
		if ($this->set['SiteVersion']) {
			$mailhead .= $this->addHeadLine('X-Mailer', $this->set['SiteVersion'].' Mailer');
			$mailhead .= $this->addHeadLine('X-MimeOLE', "Facula Framework Mailer OLE ({$this->set['SiteVersion']} Mod)");
			$mailhead .= $this->addHeadLine('X-AntiAbuse', "Script - Facula Framework ({$this->set['SiteVersion']} Mod)");
		} else {
			$mailhead .= $this->addHeadLine('X-Mailer', 'Facula Framework Mailer');
			$mailhead .= $this->addHeadLine('X-MimeOLE', 'Facula Framework Mailer OLE');
			$mailhead .= $this->addHeadLine('X-AntiAbuse', 'Script - Facula Framework');
		}
		
		$sendfrom = explode('@', $this->set['Address']);
		
		$mailhead .= $this->addHeadLine('X-AntiAbuse', "Site - {$this->set['Address']} / {$_SERVER['HTTP_HOST']}");
		$mailhead .= $this->addHeadLine('X-AntiAbuse', "Sender - {$this->set['Address']}");
		
		$mailhead .= $this->addHeadLine('Content-Type', "multipart/alternative; boundary=\"{$boundary}\"");
		$mailhead .= "\nThis MIME mail produce by Facula Framework Mailer. If you cannot see any content in this mail, please contact our postmaster via {$this->set['Site']['SiteAdminMail']}\n";

		return $mailhead;
	}
	
	private function makeMailBody($boundary, $mailcontent) {
		$mailbody = "";
		
		$mailcontent = trim(str_replace("\r", "\n", str_replace("\r\n", "\n", $mailcontent)));
		
		$mailbody .= "\n--".$boundary."\n";
		$mailbody .= $this->addHeadLine('Content-Type', 'text/plain; charset=utf-8');
		$mailbody .= $this->addHeadLine('Content-Transfer-Encoding', 'base64');
		$mailbody .= "\n".chunk_split(base64_encode($this->secobj->removeHTML($mailcontent)).'?=', 76, "\n")."\n";
		$mailbody .= "\n--".$boundary."\n";
		$mailbody .= $this->addHeadLine('Content-Type', 'text/html; charset=utf-8');
		$mailbody .= $this->addHeadLine('Content-Transfer-Encoding', 'base64');
		$mailbody .= "\n".chunk_split(base64_encode($mailcontent).'?=', 76, "\n")."\n";
		$mailbody .= "\n--".$boundary."--\n";
		
		$mailbody .= "\n";
		
		return $mailbody;
	}
	
	private function addUTF8Tag($string) {
		return preg_replace('/^(.*)$/s', '=?UTF-8?B?\\1?=', $string);
	}
	
	private function doTos() {
		$failed = 0;
		$lastfailed = array();
		
		if (!empty($this->set['CCs'])) {
			if ($this->isConnected() || $this->connect()) {
				foreach ($this->set['CCs'] AS $key => $val) {
					if ($this->put('RCPT TO: <'.preg_replace('/.*\<(.+?)\>.*/', '\\1', $val).'>') != '250') {
						$failed = 1;
						$lastfailed = array('Callback' => $callback, 'Address' => $val);
					}
				}
				
				if (!$failed) {
					$this->set['TODefined'] = true;
					return true;
				} else {
					$this->set['TODefined'] = false;
					$this->error = 'MAIL_ADD_RCPT_FAILED|'.implode(';', $lastfailed);
				}
			}
		}
		
		return false;
	}
	
	private function put($msg, $nocallback = false) {
		if ($this->isConnected() || $this->connect()) {
			if (fputs($this->serverhandle, $msg."\r\n")) {
				return $nocallback ? true : $this->getCallBack(3);
			} else {
				$this->error = 'MAIL_FAILED_ON_PUTING_MSG';
			}
		} else {
			$this->error = 'MAIL_CONNECTION_LOST';
		}
		
		return false;
	}
	
	private function get() {
		$iloop = 0;
		$nmsg = $mmsg = $msg = '';
		if ($this->isConnected() || $this->connect()) {
			while($msg = @fgets($this->serverhandle, 512)) {
				$nmsg = substr($msg, 3, 1);
				if (!$mmsg) $mmsg = $msg;
				$iloop++;
				if ($nmsg != '-' || empty($nmsg) || $iloop > 10) break;
			}
			return trim($mmsg);
		} else {
			$this->error = 'MAIL_CONNECTION_LOST';
		}
		
		return false;
	}
	
	private function getCallBack($len = 3, $startat = 0) {
		$len = intval($len ? $len : 3);

		return substr($this->get(), $startat, $len);
	}
}

?>