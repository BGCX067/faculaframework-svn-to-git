<?php 
/*****************************************************************************
	Facula Framework Security Manager
	
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

class security {
	private $oopsobj = null;
	
	public $error = '';
	
	private $set = array();
	private $regular = array('email' => array('Search' => '/^[a-zA-Z0-9\_\-\.]+\@[a-zA-Z0-9\_\-\.]+\.[a-zA-Z0-9\_\-\.]+$/u', 'Replace' => ''),
								'password' => array('Search' => '/^[a-fA-F0-9]+$/i', 'Replace' => ''),
								'username' => array('Search' => '/^[A-Za-z0-9\x{007f}-\x{ffe5}\.\_\-]+$/u', 'Replace' => ''),
								'standard' => array('Search' => '/^[A-Za-z0-9\x{007f}-\x{ffe5}\.\_\@\-\:\#\,\s]+$/u', 'Replace' => ''),
								'filename' => array('Search' => '/^[A-Za-z0-9\s\(\)\.\-\,\_\x{007f}-\x{ffe5}]+$/u', 'Replace' => ''),
								'url' => array('Search' => '/^[a-zA-Z0-9]+\:\/\/[a-zA-Z0-9\&\;\.\#\/\?\-\=\_\+\:\%\,]+$/u', 'Replace' => ''),
								'urlchars' => array('Search' => '/[a-zA-Z0-9\.\/\?\-\=\&\_\+\:\%\,]+/u', 'Replace' => ''),
								'urlinput' => array('Search' => '/^[\x{007f}-\x{ffe5}A-Za-z0-9\/\_\@\-\:\#\,\s]+$/u', 'Replace' => ''),
								'urlkey' => array('Search' => '/^[A-Za-z0-9\-]+$/u', 'Replace' => ''),
								'number' => array('Search' => '/^[0-9]+$/u', 'Replace' => ''));
								
	private $pool = array();
	
	public function __construct(&$o) {
		if (is_object($o)) {
			if ($this->oopsobj = $o) {
				$this->oopsobj->setObjs('SEC', $this);
			}
			
			$this->set = array('Time' => $this->time(),
								'Badwords' => array());
			
			$this->set['magic_quotes_status'] = get_magic_quotes_runtime();
			
			
			
			return true;
		} else {
			exit('A critical problem prevents initialization while trying to create '. __CLASS__.'.');
		}
		
		return false;
	}
	
	public function initialize(&$setting = array()) {
		$this->setAuthkey($setting['SiteAuthKey']);
		$this->setBadwords($setting['SiteBadWords']);
		$this->set['SiteMaxStringInput'] = $setting['SiteMaxStringInput'] ? $setting['SiteMaxStringInput'] : 300000;
		$this->set['SiteMaxQueueTimeout'] = $setting['SiteMaxQueueTimeout'] ? $setting['SiteMaxQueueTimeout'] : 10;
		$this->initLibraries($setting['Libraries']);
		
		return true;
	}
	
	public function time() {
		if ($this->set['Time']) {
			return $this->set['Time'];
		} elseif ($this->set['Time'] = time()) {
			return $this->set['Time'];
		}
		
		return 0;
	}
	
	public function &getInstance($objectname, $new = false, $init = true) {
		$obj = null;
		
		if ($this->pool['Objects'][$objectname]['Usable'] == 'YES' && !$new && isset($this->pool['Objects'][$objectname][0])) {
			return end($this->pool['Objects'][$objectname]['Instances']);
		} else {
			if ($this->getClassFile($objectname)) {
				if ($obj = new $objectname) { // Is there any class?
					if ($init) { // Needs init?
						switch($this->pool['Objects'][$objectname]['IsAPI']) {
							case true: // If this one is the api. API will have more permission: They can read all setting of the website, and even become ghosted and queued
								// Load Setting from database
								if (is_array($obj->swap['Required']['SiteSetting'])) {
									foreach($obj->swap['Required']['SiteSetting'] AS $key => $val) {
										$obj->swap['Required']['SiteSetting'][$key] = $this->pool['SiteSettings'][$key];
									}
								}
								
								// Load setting from setting file
								if (is_array($obj->swap['Required']['LocalSetting'])) {
									foreach($obj->swap['Required']['LocalSetting'] AS $key => $val) {
										$obj->swap['Required']['LocalSetting'][$key] = $this->pool['LocalSettings'][$key];
									}
								}
								
								$obj->swap['Required']['Filled'] = true;
								
								// API can be create as a guest instance, which means, instance will not be release by releaseInstance method
								if ($obj->swap['IsGhost']) {
									$this->pool['Objects'][$objectname]['Ghost'] = true;
									
									if ($obj->swap['QueueTimeout']) { // And ghost instance can be use as a Queue Instance, which will keep running in a short time after user got the webpage
										$this->pool['ThereQueuedInstance'] = true;
										if ($obj->swap['QueuedTimeout'] < $this->set['SiteMaxQueueTimeout']) { // Don't over the limit, we want be safe, in time case.
											ignore_user_abort();
											set_time_limit($this->set['QueuedTimeout']);
										} else {
											ignore_user_abort();
											set_time_limit($this->set['SiteMaxQueueTimeout']);
										}
									}
								}
								break;
								
							default: // If this one not api: Means this one is a common module, they only able to read limited setting from database, and no any adved permissions
								if (is_array($obj->swap['Required']['SiteSetting'])) {
									foreach($obj->swap['Required']['SiteSetting'] AS $key => $val) {
										switch($key) {
											case 'general':
												
											case 'setting':
											
											case 'count':
												
											case $objectname:
												$obj->swap['Required']['SiteSetting'][$key] = $this->pool['SiteSettings'][$key];
												break;
												
											default:
												break;
										}
									}
									
									$obj->swap['Required']['Filled'] = true;
								}
								break;
						} // Well done!
						
						// We load setting from the above codes, now we need to init it
						if (method_exists($obj, '_init')) {
							if ($obj->_init()) {
								if ($obj->swap['Required']['Filled']) $obj->swap = null;
							} else {
								$obj = null;
								$this->oops('ERROR_SEC_CLASSLOADER_INIT_FAILED|'.$objectname, true);
								
								return false;
							}
						}
					} // Inited
					
					$this->pool['Objects'][$objectname]['Instances'][] = $obj;
					return $obj;
				}
			}
		}
		
		return false;
	}
	
	public function releaseInstance($objectname, $instanceObj = null) {
		$targetInstance = $targetInstanceKey = null;
		
		if ($instanceObj) {
			foreach($this->pool['Objects'][$objectname]['Instances'] AS $key => $val) {
				if ($val == $instanceObj) {
					$targetInstanceKey = $key;
					$targetInstance = $val;
				}
			}
		} else {
			$targetInstance = end($this->pool['Objects'][$objectname]['Instances']);
			$targetInstanceKey = key($this->pool['Objects'][$objectname]['Instances']);
		}
		
		if ($targetInstance) { // We got anything?
			if (method_exists($targetInstance, '_free')) {
				if (!$targetInstance->_free()) {
					$targetInstance = null;
					$this->oops('ERROR_SEC_CLASSLOADER_RELEASE_FAILED|'.$objectname, true);
					
					return false;
				}
			}
			
			if ($this->pool['Objects'][$objectname]['Ghost']) {
				$this->pool['Objects'][$objectname]['GhostInstances'][] = $this->pool['Objects'][$objectname]['Instances'][$targetInstanceKey];
			} else {
				$this->pool['Objects'][$objectname]['Instances'][$targetInstanceKey] = $targetInstance = null;
			}
			
			unset($this->pool['Objects'][$objectname]['Instances'][$targetInstanceKey], $targetInstance);
			
			$this->pool['Objects'][$objectname]['Instances'] = array_values($this->pool['Objects'][$objectname]['Instances']);
			
			return true;
		}
	}
	
	private function getClassFile($objectname) {
		switch($this->pool['Objects'][$objectname]['Usable']) {
			case 'YES':
				return $this->pool['Objects'][$objectname]['Path'];
				break;
				
			case 'NO':
				break;
				
			default:
				if ($this->pool['Objects'][$objectname]['Path']) {
					require($this->pool['Objects'][$objectname]['Path']);
					
					if (class_exists($objectname, false)) {
						$this->pool['Objects'][$objectname]['Usable'] = 'YES';
						return $this->pool['Objects'][$objectname]['Path'];
					} else {
						$this->pool['Objects'][$objectname]['Usable'] = 'NO';
						return false;
					}
				} else {
					$this->pool['Objects'][$objectname]['Usable'] = 'NO';
				}
				break;
		}
		
		return false;
	}
	
	public function loadClass($classname) {
		return $this->getClassFile($classname);
	}
	
	public function getLibraries() {
		$results = $files = array();
		
		if ($files = $this->listDir(PROJECT_ROOT.DIRECTORY_SEPARATOR.'libraries', '.')) {
			foreach($files['Splited'] AS $key => $val) {
				if ($files['Files'][$val]['IsFile']) {
					$results[$key] = array(
						'Path' => $files['Files'][$val]['Path'],
						'IsAPI' => ($files['Files'][$val]['Prefix'] == 'api') ? true : false,
					);
				}
			}
			
			return $results;
		}
		
		return false;
	}
	
	private function initLibraries(&$libraries = array()) {
		if (!$this->pool['librariesInited']) {
			if (($this->pool['Objects'] = $libraries) || ($this->pool['Objects'] = $this->getLibraries())) {
				$libraries = array();
				$this->pool['librariesInited'] = true;
				
				return true;
			}
		}
		
		return false;
	}
	
	public function listDir($path, $splitSymb = '') {
		$dirhandle = null;
		$temp = $result = array();
		$entry = '';
		$fnsplitsize = 0;
		
		if ($dirhandle = opendir($path)) {
			while (false !== ($entry = readdir($dirhandle))) {
				switch($entry) {
					case '.':
						break;
						
					case '..':
						$result['ParentExisted'] = true;
						break;
						
					default:
						if ($splitSymb && ($temp['Split'] = explode($splitSymb, $entry)) && $temp['Split'][1]) {
							$fnsplitsize = count($temp['Split']);
							
							$temp['Result'] = array(
								'Pre' => $temp['Split'][0],
								'Suf' => $temp['Split'][$fnsplitsize - 1],
							);
							
							unset($temp['Split'][0], $temp['Split'][$fnsplitsize - 1]);
							
							$temp['Result']['Main'] = implode($splitSymb, $temp['Split']);
							
							$result['Files'][$entry] = array(
								'Name' => $entry,
								'Subject' => $temp['Result']['Main'],
								'Prefix' => $temp['Result']['Pre'],
								'Suffix' => $temp['Result']['Suf'],
								'IsFile' => is_file($path.DIRECTORY_SEPARATOR.$entry),
								'Path' => $path.DIRECTORY_SEPARATOR.$entry,
							);
							
							$result['Splited'][$temp['Result']['Main']] = $entry;
						} else {
							$result['Files'][$entry] = array(
								'Name' => $entry,
								'IsFile' => is_file($path.DIRECTORY_SEPARATOR.$entry),
								'Path' => $path.DIRECTORY_SEPARATOR.$entry,
							);
						}
						
						break;
				}
			}
			
			closedir($dirhandle);
		}
		
		return $result;
	}
	
	public function setAuthkey(&$string) {
		if (isset($string[6]) && !$this->set['Authkey']) {
			$this->set['Authkey'] = $string;
			
			$string = null;
			return true;
		} else {
			$this->error = 'ERROR_SEC_AUTHKEY_ALREADY_EXISTS_OR_NEW_KEY_IS_INVALID';
		}
		
		$string = null;
		return false;
	}
	
	public function oops($erstring, $exit = false) {
		$error_codes = array('ERROR_SEC_REGULAR_ALREADY_EXISTS' => 'You trying to assgin a regular expression to expression list, but the slot already assgined.',
							'ERROR_SEC_REGULAR_NOT_DEFINED' => 'The regular expression you wanted was not found.',
							'ERROR_SEC_AUTHKEY_ALREADY_EXISTS_OR_NEW_KEY_IS_INVALID' => 'The secret key already assgined or new key is invalid.',
							'ERROR_SEC_AUTHKEY_NOT_DEFINED' => 'The secret key not assgined yet.',
							'ERROR_SEC_SALTING_STRING_EMPTY' => 'The string for salting is empty.',
							'ERROR_SEC_NO_STUFF_TO_HASH' => 'Cannot hash a empty string.',
							'ERROR_SEC_AUTOLIB_NOT_FOUND' => 'Autoloader cannot found the lib you specified.',
							'ERROR_SEC_CLASSLOADER_INIT_FAILED' => 'Sorry, we are got fail when trying to init your object.',
							'ERROR_SEC_CLASSLOADER_RELEASE_FAILED' => 'Sorry, we are got fail when trying to release your object.',
							'ERROR_SEC_CANNOT_GENERATE_RANDSEED' => 'Cannot generate the random seed.');
		
		if ($this->oopsobj->pushmsg($error_codes) && $this->oopsobj->ouch($erstring, $exit)) {
			return true;
		}
		
		return false;
	}
	
	public function readPackedData(&$data = '') {
		return unserialize(str_replace(array('&amp;', '&quot;', '&#039;', '&lt;', '&gt;'), array('&', '"', '\'', '<', '>'), $data));
	}
	
	public function changePackedData($key, $val = '', &$data = array()) {
		if ($val)
			switch($val[0]) {
				case '+':
					
				case '-':
					$data[$key] += intval($val); 
					break;
					
				default:
					$data[$key] = $this->filterTextToWebForm($val); 
					break;
			}
		else 
			unset($data[$key]);
	
		return true;
	}
	
	public function repackPackedData(&$data = array()) {
		return serialize($data);
	}
	
	public function setSiteSetValue($setting) {
		if (!isset($this->pool['SiteSettings'])) $this->pool['SiteSettings'] = array();
		
		if (empty($this->pool['SiteSettings'])) {
			$this->pool['SiteSettings'] = $setting;
			return true;
		} 
		
		return false;
	}
	
	public function setLocalSetValue($setting) {
		if (!isset($this->pool['LocalSettings'])) $this->pool['LocalSettings'] = array();
		
		if (empty($this->pool['LocalSettings'])) {
			$this->pool['LocalSettings'] = $setting;
			
			return true;
		} 
		
		return false;
	}
	
	public function getSiteSetValue($key) {
		$bt = array();
		
		if ($bt = debug_backtrace()) {
			if ($bt[1]['class']) {
				if ($this->pool['Objects'][$bt[1]['class']]['IsAPI'] || $key == 'general' || $key == 'setting' || $key == 'count') {
					return $this->pool['SiteSettings'][$key];
				}
			}
		}
		
		return false;
	}
	
	public function getLocalSetValue($key) {
		$bt = array();
		
		if ($bt = debug_backtrace()) {
			if ($bt[1]['class']) {
				if ($this->pool['Objects'][$bt[1]['class']]['IsAPI'])
					return $this->pool['LocalSettings'][$key];
			}
		}

		return false;
	}
	
	public function readCfgStr($string) {
		return explode("\n", str_replace("\r", "\n", str_replace("\r\n", "\n", $string)));
	}
	
	public function parseCfgStr($string) {
		$parray = array();
		
		if ($parray = explode('#', $string)); {
			return array_values($parray);
		}
		
		return false;
	}
	
	public function addRegular($name, $searchs, $replaces = '') {
		// Add data to array as a new item
		if (!isset($this->regular[$name]['Search'])) {
			$this->regular[$name] = array('Search' => trim($searchs), 'Replace' => trim($replaces));
			return true;
		}
		
		return false;
	}
	
	public function &getRegulars() {
		return $this->regular;
	}
	
	public function strLength(&$string, $encode = 'utf-8') {
		return mb_strlen($string, $encode);
	}
	
	public function strPosition(&$haystack, $needle, $offset = 0, $encode = 'utf-8') {
		return mb_strpos($haystack, $needle, $offset, $encode);
	}
	
	public function subString($str, $len, $encode = 'utf-8', $start = 0) {
		return mb_substr($str, $start, $len, $encode ? $encode : 'utf-8');
	}
	
	public function header($val) {
		return $this->pool['HeaderQueue'][] = $val;
	}
	
	public function getHeaders() {
		if ($this->pool['ThereQueuedInstance']) {
			$this->pool['HeaderQueue'][] = 'Connection: close';
		}
		
		return $this->pool['HeaderQueue'];
	}
	
	public function getUserIP($ipstr = '', $outasstring = false) {
		global $_SERVER;
		$ip = '';
		
		if (!$ipstr) {
			if($_SERVER['HTTP_CLIENT_IP']){
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} elseif ($_SERVER['HTTP_X_FORWARDED_FOR']) {
				$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'], 16);
				$ip = trim($ips[count($ips) - 1]);
			}
			
			return $outasstring ? ($ip ? $ip : $_SERVER['REMOTE_ADDR']) : $this->splitIP($ip ? $ip : $_SERVER['REMOTE_ADDR']);
		} else {
			return $outasstring ? $ipstr : $this->splitIP($ipstr);
		}
		
		return false;
	}
	
	public function getServerIP() {
		global $_SERVER;
		return $this->splitIP($_SERVER['SERVER_ADDR']);
	}
	
	public function getMonthTimeStamp($month = 0) {
		$reminmonth = $months = $month_start = $month_end = $this_year = $this_month = $start_month = $start_year = $end_month = $end_year = 0;
		$month = intval($month);
		if (!$month) $month = 1;
		
		if ($month > 0) {
			$this_year = intval(date('Y'));
			$this_month = intval(date('m'));
			$reminmonth = 12 - $this_month;

			$months = intval(($month + ($reminmonth - 1)) / 12);
			$start_year = $this_year - $months;
		
			if ($this_month - $month >= 0) {
				$start_month = 1 + (($this_month - $month) % 12);
			} else {
				$start_month = 12 - abs((($reminmonth - 1) + $month) % 12);
			}

			if ($start_month + 1 > 12) {
				$end_year = $start_year + 1;
				$end_month = (12 - $start_month) + 1;
			} else {
				$end_year = $start_year;
				$end_month = $start_month + 1;
			}
		}
		
		return array('Start' => strtotime(date("{$start_year}-{$start_month}")), 'End' => strtotime(date("{$end_year}-{$end_month}"))); 
	}
	
	public function Pager($itemprepage, $current, $totalitems = 0) {
		$tp = $p = 0;
		$vip = $vc = $vti = 0;
		
		$vc = intval($current) - 1;
		$vip = intval($itemprepage);
		$vti = intval($totalitems);
		
		if ($vc < 0) {
			$vc = 0;
		} elseif ($vc > ($this->pool['SiteSettings']['setting']['MaxPages'] ? $this->pool['SiteSettings']['setting']['MaxPages'] : 5000)) {
			$vc = $this->pool['SiteSettings']['setting']['MaxPages'];
		}
		
		if ($vti) {
			$tp = ceil($vti > $vip ? $vti / $vip : 1);
			$tp = $tp > $this->pool['SiteSettings']['setting']['MaxPages'] ? $this->pool['SiteSettings']['setting']['MaxPages'] : $tp;
			
			if ($vc >= $tp) {
				$vc = $tp - 1;
			}
		}
		
		$p = $vip * $vc;
		
		return array('Pager' => $p, 'Items' => $vip, 'Current' => $vc ? $vc + 1 : 1, 'TotalPages' => $tp);
	}
	
	public function setBadwords(&$bw) {
		if (empty($bw)) return false;
		
		if (is_array($bw)) {
			$this->set['Badwords'] = $this->set['Badwords'] + $bw;
		} else {
			$this->set['Badwords'] = explode(',', $bw);
		}
		
		$bw = null;
		
		return true;
	}
	
	public function checkBadwords(&$string) {
		$o_strlen = $n_strlen = 0;
		$str = '';
		
		if (!$string) {
			return false;
		} else {
			$o_strlen = $this->strLength($string);
			// Below procss will make app slowly
			if ($this->set['Badwords'] && ($str = str_replace($this->set['Badwords'], '', $string)) && ($n_strlen = $this->strLength($str))) {
				if ($o_strlen != $n_strlen) {
					return true;
				}
			} else {
				return false;
			}
		}

		return false;
	}
	
	public function isURL(&$string, &$error = '') {
		$out = '';
		
		if ($out = $this->regularMatch('url', $string, 512, 2, $error)) {
			return $out;
		}
		
		return false;
	}
	
	public function isURLCHARS(&$string, &$error = '') {
		$out = '';
		
		if ($out = $this->regularMatch('urlchars', $string, 512, 1, $error)) {
			return $out;
		}
		
		return false;
	}

	
	public function isHash(&$string, $max = 32, $min = 8, &$error = '') {
		$out = '';

		if ($out = $this->regularMatch('password', $string, $max, $min, $error)) {
			return $out;
		} 
		
		return false;
	}
	
	public function isString(&$string, $max = 1024, $min = 3, &$error = '') {
		$out = '';
		
		if ($out = $this->regularMatch('standard', $string, $max, $min, $error)) {
			return $out;
		} 
		
		return false;
	}
	
	public function isFilename(&$string, $max = 64, $min = 1, &$error = '') {
		$out = '';
		
		if ($out = $this->regularMatch('filename', $string, $max, $min, $error)) {
			return $out;
		} 
		
		return false;
	}
	
	public function isURLINPUT(&$string, $max = 32, $min = 1, &$error = '') {
		$out = '';
		
		if ($out = $this->regularMatch('urlinput', $string, $max, $min, $error)) {
			return $out;
		} 
		
		return false;
	}

	public function isURLKEY(&$string, $max = 32, $min = 1, &$error = '') {
		$out = '';
		
		if ($out = $this->regularMatch('urlkey', $string, $max, $min, $error)) {
			return $out;
		} 
		
		return false;
	}
	
	public function isText(&$string, $max = 1024, $min = 0, &$error = '') {
		return $this->filterTextToDB($string, $max, $min, false, $error);
	}
	
	public function isUsername(&$string, $max = 20, $min = 2, &$error = '') {
		$out = '';
		
		if ($out = $this->regularMatch('username', $string, $max, $min, $error)) {
			return $out;
		} 
		
		return false;
	}
	
	public function isEmail(&$string, $max = 50, $min = 3, &$error = '') {
		$out = '';
		
		if ($out = strtolower($this->regularMatch('email', $string, $max, $min, $error))) {
			return $out;
		}
		
		return false;
	}
	
	public function isID(&$idString, $max = 10000, $min = 1, &$error = '') {
		$out = '';
		
		$idString = ltrim($idString, '0');
		
		if ($out = $this->regularMatch('number', $idString, $max, $min, $error)) {
			return $out;
		}
		
		return false;
	}
	
	public function isInt(&$num, $max = 65535, $min = 0, $allowzero = false) {
		if (($max && $num > $max) || ($min && $num < $min)) {
			return false;
		} else {
			return !$num && $allowzero ? '00' : intval($num);
		}
		
		return false;
	}
	
	public function isFloat(&$float, $max = 65535, $min = 0) {
		if (($max && $float > $max) || ($min && $float < $min)) {
			return false;
		} else {
			return floatval($float);
		}
		
		return false;
	}
	
	public function isBackLink(&$link) {
		global $_SERVER;
		$linkhost = array();
		
		$linkhost = explode('://', $link, 2);
		
		if ($linkhost[1]) {
			$linkhost = explode('/', $linkhost[1], 2);
			if ($linkhost[0] == $_SERVER['HTTP_HOST']) {
				return true;
			}
		}
		
		return false;
	}
	
	public function hashPassword($string, $fast = false, $maxsize = 64, &$error = '') {
		$in = '';
		$pass = '';
		
		if (isset($string[$maxsize]) || !$string) return false;
		
		if ($in = $this->encrypt_normal(substr($string, 0, 4080), $fast)) {
			if ($pass = $this->regularMatch('password', $in, 32, 32)) {
				return $pass;
			}
		}
		
		return false;
	}
	
	public function getStringHash(&$string, $maxlen = 10) {
		if (isset($string[0])) {
			if (strlen($string) > $maxlen) {
				return md5(substr(0, $maxlen, $string));
			} else {
				return md5($string);
			}
		}
		
		return false;
	}
	
	public function genHashForClient($string) {
		$in = $pass = $error = '';
		
		if ($in = $this->encrypt_basic(substr($string, 0, 4080), true)) {
			if ($pass = $this->regularMatch('password', $in, 64, 16, $error)) {
				return $pass;
			}
		}
		
		return false;
	}
	
	public function parseHashFromClient($string, $fast = false) {
		$in = $pass = $error = '';
		
		if ($in = $this->encrypt_half(substr($string, 0, 4080), true)) {
			if ($pass = $this->regularMatch('password', $in, 32, 16, $error)) {
				return $pass;
			}
		}
		
		return false;
	}
	
	public function hashItForMe($string) {
		$in = $pass = '';
		
		if ($in = $this->encrypt_normal(substr($string, 0, 4080))) {
			if ($pass = $this->regularMatch('password', $in, 32, 16)) {
				return $pass;
			}
		}
		
		return false;
	}
	
	public function randSeed($num) {
		$out = '';
		$tmp_array = array();
		$seeds = array('A', 'B', 'C', 'D', 'E', 'F', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0');
		$num = intval($num <= 6 && $num >= 3 ? $num : 6);
		
		shuffle($seeds);
		$out = $this->subString(implode($seeds), $num);

		if ($out) {
			return $out;
		} else {
			$this->error = 'ERROR_SEC_CANNOT_GENERAT_RANDSEED';
		}
		
		return false;
	}
	
	public function str2tiny($str, $num = 0) {
		$out = array();
		$string = ''; 
		$strlen = $newstrlen = 0;
		
		$map = array('A' => '00', 'B' => '01',
					'C' => 'a', 'D' => 'a', 'E' => 'm', 'F' => 'Y', 'G' => 'L',
					'H' => 'c', 'I' => 'b', 'J' => 'n', 'K' => 'X', 'L' => 'K',
					'M' => 'h', 'N' => 'c', 'O' => 'o', 'P' => 'W', 'Q' => 'J',
					'I' => 'D', 'S' => 'd', 'T' => 'p', 'U' => 'V', 'V' => 'I',
					'W' => 'A', 'X' => 'e', 'Y' => 'q', 'Z' => 'U', 'a' => 'H',
					'b' => 'B', 'c' => 'f', 'd' => 'i', 'e' => 'S', 'f' => 'G',
					'g' => 'C', 'h' => 'g', 'i' => 's', 'j' => 'I', 'k' => 'F',
					'l' => 'D', 'm' => 'h', 'n' => 't', 'o' => 'Q', 'p' => 'E',
					'q' => 'w', 'i' => 'i', 's' => 'u', 't' => 'P', 'u' => 'D',
					'v' => 'x', 'w' => 'j', 'x' => 'v', 'y' => 'O', 'z' => 'C',
					'0' => 'y', '1' => 'k', '2' => 'z', '3' => 'N', '4' => 'B',
					'5' => 'z', '6' => 'l', '7' => 'Z', '8' => 'M', '9' => 'A');
					
		if ($str) {
			if (is_numeric($str)) {
				$string = $str;
			} else {
				$string = md5($str);
			}

			$strlen = $this->strLength($string);
			if ($strlen > 0) {
				if (ceil($strlen / 2) > 4) {
					$newstrlen = ceil($num < ($strlen / 2) && $num > 2 ? $num : $strlen / 2);
				} else {
					$newstrlen = $strlen;
				}

				if ($newstrlen > 0) {
					while($newstrlen--) {
						$out[] = $newstrlen%2 != 0 && $newstrlen >= 2 ? $map[$string[$strlen--]] : $map[$string[$newstrlen]];
					}
				}

				return implode($out);
			}
		}
		
		return false;
	}
	
	public function str2num(&$str, $num = 0) {
		$out = array();
		$string = ''; 
		$strlen = $newstrlen = 0;
		
		$map = array('A' => '3', 'B' => '4',
					'C' => '1', 'D' => '3', 'E' => '5', 'F' => '7', 'G' => '1',
					'H' => '2', 'I' => '4', 'J' => '6', 'K' => '8', 'L' => '2',
					'M' => '3', 'N' => '5', 'O' => '7', 'P' => '9', 'Q' => '3',
					'I' => '4', 'S' => '6', 'T' => '8', 'U' => '0', 'V' => '4',
					'W' => '5', 'X' => '7', 'Y' => '9', 'Z' => '1', 'a' => '5',
					'b' => '6', 'c' => '8', 'd' => '0', 'e' => '2', 'f' => '6',
					'g' => '7', 'h' => '9', 'i' => '1', 'j' => '3', 'k' => '7',
					'l' => '8', 'm' => '0', 'n' => '2', 'o' => '4', 'p' => '8',
					'q' => '9', 'i' => '1', 's' => '3', 't' => '5', 'u' => '9',
					'v' => '0', 'w' => '2', 'x' => '4', 'y' => '6', 'z' => '0',
					'0' => '1', '1' => '3', '2' => '5', '3' => '7', '4' => '1',
					'5' => '2', '6' => '4', '7' => '6', '8' => '8', '9' => '2');
					
		if ($str) {
			if (is_numeric($str)) {
				$string = $str;
			} else {
				$string = md5($str);
			}

			$strlen = $this->strLength($string);
			if ($strlen > 0) {
				if (ceil($strlen / 2) > 4) {
					$newstrlen = ceil($num < ($strlen / 2) && $num > 2 ? $num : $strlen / 2);
				} else {
					$newstrlen = $strlen;
				}

				if ($newstrlen > 0) {
					while($newstrlen--) {
						$out[] = $newstrlen%2 != 0 && $newstrlen >= 2 ? $map[$string[$strlen--]] : $map[$string[$newstrlen]];
					}
				}

				return implode('', $out);
			}
		}
		
		return false;
	}
	
	public function convertKMGtoB($string) {
		$unit = strtoupper($string[strlen($string) - 1]);
		$numb = intval($string);
		$out = 0;
		
		switch($unit) {
			case 'G': $out = $numb * 1073741824; break;
			case 'M': $out = $numb * 1048576; break;
			case 'K': $out = $numb * 1024; break;
			default: $out = $numb; break;
		}
		
		return intval($out);
	}
	
	public function crc32($string) {
		return intval(sprintf('%u', crc32($string)));
	}
	
	public function makedir(&$path, $chmod = 0644) {
		$patharray = array();
		$newpatch = $tmppatch = '';
		$defaultpagecont = '<html><body>WebShell Message: Wrong Way(403).</body></html>';
		
		if ($path) {
			$tmppatch = str_replace('/', DIRECTORY_SEPARATOR, str_replace('\\', DIRECTORY_SEPARATOR, $path));
			$patharray = array_values(explode(DIRECTORY_SEPARATOR, $tmppatch));
			
			foreach($patharray AS $key => $val) {
				if (isset($val[0])) {
					$newpatch .= $val ? $val : '0';
					
					if (!file_exists($newpatch)) {
						if (!mkdir($newpatch, $chmod)) {
							return false;
						} else {
							file_put_contents($newpatch.DIRECTORY_SEPARATOR.'index.html', $defaultpagecont);
						}
					}
					
					$newpatch .= DIRECTORY_SEPARATOR;
				}
			}
			
			return true;
		}
		
		return false;
	}
	
	public function removeHtml(&$input, $exp = '') {
		$out = array();
		
		if (is_array($input)) {
			foreach($input AS $key => $val) {
				 $out[$key] = $this->removeHtml($val, $exp);
			}
			return $out;
		} else {
			return strip_tags($input, $exp);
		}
	}
	
	public function filterTextToDB(&$string, $maxlen = 1000000, $minlen = 0, $force = false, &$error = '') {
		$strlen = $this->strLength($string);
		
		if ($strlen > $this->set['SiteMaxStringInput']) {
			$error = 'TOOLONG|'.$this->set['SiteMaxStringInput'];
		} elseif ($maxlen && $strlen > $maxlen) {
			$error = 'TOOLONG|'.$maxlen;
		} elseif ($minlen && $strlen < $minlen) {
			$error = 'TOOSHORT|'.$minlen;
		} else {
			return $this->addSlashes($string, $force);
		}
		
		return false;
	}
	
	public function filterTextToWebForm(&$input) {
		$out = array();
		$tmpstr = '';
		
		if (!is_array($input)) {
			if ($tmpstr = $this->subString($input, $this->set['SiteMaxStringInput'])) {
				return trim(htmlspecialchars($this->delSlashes($tmpstr), ENT_QUOTES));
			}
		} else {
			foreach($input as $key => $val) {
				$out[$key] = $this->filterTextToWebForm($val);
			}
			return $out;
		}
		
		return false;
	}
	
	public function filterTextToWebPage(&$input) {
		$out = array();
		
		if (!is_array($input)) {
			return trim(nl2br(htmlspecialchars($input, ENT_QUOTES)));
		} else {
			foreach($input as $key => $val) {
				$out[$key] = $this->filterTextToWebPage($val);
			}
			return $out;
		}
		
		return false;
	}
	
	public function needSlashes(&$input) {
		$searchs = array("\x5C\x5C", "\x5C\x27", "\x5C\x22", "\x5C\x00", "\x00", "\x22", "\x27", "\x5C");
		
		if (!$this->set['magic_quotes_status'] && $input && strlen(str_replace($searchs, '==', $input)) != strlen($input)) {
			return true;
		}
		
		return false;
	}
	
	public function addSlashes($input) {
		if (!$input) return $input;
		
		if(!is_array($input)) {
			if ($this->needSlashes($input)) {
				return trim(addslashes($input));
			} else {
				return $input;
			}
		} else {
			foreach($input AS $key => $val) $input[$key] = $this->addSlashes($val);
			return $input;
		}
		
		return false;
	}

	public function delSlashes($input) {
		$tmp_array = array();
		
		if(!is_array($input)) {
			$tmp_array = explode('\\', $input, 2);
			if (isset($tmp_array[1])) {
				return trim(stripcslashes($input));
			} else {
				return $input;
			}
		} else {
			foreach($input as $key => $val) $input[$key] = $this->delSlashes($val);
			return $input;
		}
	}
	
	public function splitIP($ip) {
		$ipv4 = array(); $ipv6 = array();
		
		$ipv4 = explode('.', $ip, 10);
		if (!isset($ipv4[1])) {
			$ipv6 = explode(':', $ip, 10);
			if (isset($ipv6[1]) && !isset($ipv6[8])) {
				return $ipv6;
			}
		} elseif (!isset($ipv4[4])) {
			return $ipv4;
		}
		
		return array(0, 0, 0, 0);
	}
	
	public function joinIP($ip, $mask = false) {
		$input = array();
		$ips = '';
		
		if (!is_array($ip)) return false;
		
		foreach($ip AS $k => $v) {
			if($ip[$k]) {
				$input[$k] = $v; 
			} else {
				$input[$k] = '0'; 
			}
		}
		
		$iplen = count($input);
		
		if ($mask && $iplen > 2) {
			$input[$iplen - 2] = $input[$iplen - 1] = '*';
		}
		
		if ($input[0] != '0' && $input[3] != '0' && $input[4] == '0' && $input[5] == '0' && $input[6] == '0' && $input[7] == '0') {
			$ips = implode('.', array($input[0], $input[1], $input[2], $input[3]));
		} else {
			$ips = implode(':', $input);
		}
		
		return $ips;
	}
	
	public function regularMatch($type, &$string, $maxlen = 0, $minlen = 0, &$error = '') {
		if ($string) {
			$strlen = $this->strLength($string);
			
			if ($strlen > $this->set['SiteMaxStringInput']) {
				$error = 'TOOLONG|'.$this->set['SiteMaxStringInput'];
			} elseif ($maxlen && $strlen > $maxlen) {
				$error = 'TOOLONG|'.$maxlen;
			} elseif ($minlen && $strlen < $minlen) {
				$error = 'TOOSHORT|'.$minlen;
			} elseif (!$this->regular[$type]['Search']) {
				$this->error = 'ERROR_SEC_REGULAR_NOT_DEFINED';
			} else {
				if (preg_match($this->regular[$type]['Search'], $string)) {
					return $string; // Check valid
				} else {
					$error = 'WRONGFORMAT';
				}
			}
		}
		
		return false;
	}
	
	public function openURL($addr, $type, &$data, $timeout) {
		$validtype = array(
			'post' => array(
						'Method' => 'POST',
						'Header' => "Content-type: application/x-www-form-urlencoded\r\n".
									"User-Agent: FaculaFramework\r\n",
					),
			'get' => array(
						'Method' => 'GET',
						'Header' => 'User-Agent: FaculaFramework',
					),
		);
		
		$http = array(
			'http' => array(
				'method' => $validtype[$type]['Method'],
				'header' => $validtype[$type]['Header'],
				'timeout'=> 5,
				'content' => http_build_query($data, '', '&'), 
			),
		);
		
		return file_get_contents($addr, false, stream_context_create($http));
	}
	
	// encrypt_* stuff USE FOR PASSWORD HASHING, CHANGE FOLLWING CODE WILL MAKE EGG HURT DEEPLY
	private function encrypt_half($string, $fast = false) {
		if ($string) {
			if ($this->set['Authkey']) {
				if ($fast) {
					return $this->encrypt_md4c($string);
				} else {
					return $this->encrypt_disturbing($this->encrypt_md5c($this->set['Authkey'].$string));
				}
			} else {
				$this->error = 'ERROR_SEC_AUTHKEY_NOT_DEFINED';
			}
		} else {
			$this->error = 'ERROR_SEC_NO_STUFF_TO_HASH';
		}
		
		return false;
	}
	
	private function encrypt_basic($string, $fast = false) {
		if ($string) {
			if ($this->set['Authkey']) {
				if ($fast) {
					return $this->encrypt_md4c($string);
				} else {
					return $this->encrypt_md5c($this->encrypt_salting($this->set['Authkey'].$string));
				}
			} else {
				$this->error = 'ERROR_SEC_AUTHKEY_NOT_DEFINED';
			}
		} else {
			$this->error = 'ERROR_SEC_NO_STUFF_TO_HASH';
		}
		
		return false;
	}
	
	private function encrypt_normal($string, $fast) {
		return $this->encrypt_half($this->encrypt_basic($string, $fast), $fast);
	}
	
	private function encrypt_md5c($stirng) {
		return md5($stirng);
	}
	
	private function encrypt_md4c($stirng) {
		return hash('md4', $stirng);
	}
	
	private function encrypt_salting($string) { 
		$temp = '';
		$len = 0;
		
		if ($string = $this->subString($string, 512)) {
			$len = strlen($string);
			for($i = 0; $i < $len - 1; $i+=4) {
				$temp = !empty($string[$i]) ? $string[$i] : '0';
				$string[$i] = !empty($string[$i + 3]) ? $string[$i + 3] : '0';
				$string[$i + 3] = $temp;
			}
		} else {
			$this->error = 'ERROR_SEC_SALTING_STRING_EMPTY';
		}
		
		return $string;
	}
	
	private function encrypt_disturbing($hash) { 
		$len = 0;
		$t = 0;
		
		if ($this->set['Authkey']) {
			$len = strlen($hash);
			for($i = 0; $i < $len - 1; $i+=4, $t = $i + 3) {
				$hash[$t] = !empty($this->set['Authkey'][$i]) ? $this->set['Authkey'][$i] : '0';
				$hash = strrev($hash);
			}
		}
		
		return $this->encrypt_md5c($hash);
	}
}

?>
