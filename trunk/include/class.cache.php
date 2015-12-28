<?php
/*****************************************************************************
	Facula Framework Passivity File Cache Unit

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

class filecache {
	private $oopsobj = null;
	private $secobj = null;
	private $set = array();
	private $pool = array();
	public $count = array();
	
	public function __construct(&$setting, &$oops, &$sec) {
		if (is_object($oops) && is_object($sec)) {
			$this->oopsobj = $oops;
			$this->secobj = $sec;
			
			$this->set = array('Time' => $this->secobj->time(), 
								'CachePath' => str_replace('/', DIRECTORY_SEPARATOR, $setting['CacheDir']),
								'DefaultExpire' => intval($setting['DefaultExpire'] > 60 && $setting['DefaultExpire'] < 604800 ? $setting['DefaultExpire'] : 3600),
								'Enabled' => $setting['Enabled']);
			
			if ($setting['MencachedHost']) {
				$setting['MencachedPort'] = $setting['MencachedPort'] ? $setting['MencachedPort'] : 11211;
				
				if (class_exists('Memcached', false)) {
					$this->set['Memcache'] = new Memcached;
					$this->set['MemcacheType'] = 'Memcached';
					$this->set['Memcache']->setOption(Memcached::OPT_RECV_TIMEOUT, 500);
					$this->set['Memcache']->setOption(Memcached::OPT_SEND_TIMEOUT, 500);
					$this->set['Memcache']->setOption(Memcached::OPT_TCP_NODELAY, true);
					$this->set['Memcache']->setOption(Memcached::OPT_PREFIX_KEY, 'ff_');
					$this->set['Memcache']->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
					$this->pool['UseMemcached'] = true; 
				} elseif (class_exists('Memcache', false)) {
					$this->set['Memcache'] = new Memcache;
					$this->set['MemcacheType'] = 'Memcache';
					$this->pool['UseMemcached'] = true; 
				}
				
				$this->set['MemcacheHost']	= $setting['MencachedHost'];
				$this->set['MemcachePort']	= $setting['MencachedPort'];
			} else {
				$this->pool['UseMemcached'] = false; 
			}
			
			$this->count = array('Readed' => 0, 'Written' => 0, 'FailedRead' => 0, 'FailedWrite' => 0);
			
			$setting = null;
			
			return true;
		} else {
			exit('A critical problem prevents initialization while trying to create '.__CLASS__.'.');
		}
		
		$setting = null;
		return false;
	}
	
	public function read($type, $filename, $timeout, $id = 0) {
		$filenamearray = array();
		
		if ($this->set['Enabled']) {
			// Always load data from cache if $timeout is zero.
			if (!$timeout || !$this->isExpired($type, $filename, $timeout, $id)) {
				return $this->readCache($type, $filename, $id);
			} else {
				return false;
			}
		}
	}
	
	public function save($type, $filename, $data, $id = 0) {
		if ($this->set['Enabled']) {
			return $this->writeCache($data, $type, $filename, $id);
		} else {
			return false;
		}
	}
	
	public function clear($type, $filename, $id = 0) {
		if ($this->set['Enabled']) {
			return $this->clearCache($type, $filename, $id);
		} else {
			return false;
		}
	}
	
	private function isExpired($type, $filename, $timeout = 0, $id = 0) {
		$file = '';

		if ($this->pool['UseMemcached'] && $this->memcache_connect_server()) { 
			$file = strtolower($type.'_'.$filename.'_'.$id);
			
			if (floor($this->set['Time'] - ($timeout ? $timeout : $this->set['DefaultExpire'])) > $this->memcache_get_cached_time($file)) {
				return true;
			}
		} else {
			if ($id) {
				$filedir = $this->set['CachePath'].DIRECTORY_SEPARATOR.'cd_'.strtolower($type).DIRECTORY_SEPARATOR.$this->idtosubdir($id, 3).DIRECTORY_SEPARATOR;
				$file = $filedir.'c_'.strtolower($filename).'_'.$id.'.php';
			} else {
				$filedir = $this->set['CachePath'].DIRECTORY_SEPARATOR.'cd_'.strtolower($type).DIRECTORY_SEPARATOR;
				$file = $filedir.'c_'.strtolower($filename).'.php';
			}
			
			if (floor($this->set['Time'] - ($timeout ? $timeout : $this->set['DefaultExpire'])) > filemtime($file)) {
				return true;
			}
		}
		
		return false;
	}
	
	private function readCache($type, $filename, $id = 0) {
		$file = $filecontent = '';
		$replacement = array('<?php header(\'HTTP/1.0 404 Not Found\'); exit();/******Facula fw Cache Content******', 
							'******/?>');
		
		if ($this->pool['UseMemcached'] && $this->memcache_connect_server()) {
			$file = strtolower($type.'_'.$filename.'_'.$id);
			$this->count['Readed']++;
			return $this->memcache_readcache($file);
		} else {
			if ($id) {
				$filedir = $this->set['CachePath'].DIRECTORY_SEPARATOR.'cd_'.strtolower($type).DIRECTORY_SEPARATOR.$this->idtosubdir($id, 3).DIRECTORY_SEPARATOR;
				$file = $filedir.'c_'.strtolower($filename).'_'.$id.'.php';
			} else {
				$filedir = $this->set['CachePath'].DIRECTORY_SEPARATOR.'cd_'.strtolower($type).DIRECTORY_SEPARATOR;
				$file = $filedir.'c_'.strtolower($filename).'.php';
			}
			
			if ($filecontent = file_get_contents($file)) {
				$filecontent = str_replace($replacement, '', $filecontent);
				
				$this->count['Readed']++;
				return unserialize($filecontent);
			}
		}
		
		$this->count['FailedRead']++;
		return false;
	}
	
	private function writeCache(&$array, $type, $filename, $id = 0) {
		$filecontent = $file = $filedir = '';
		
		if (is_array($array) && !empty($array)) {
			if ($this->pool['UseMemcached'] && $this->memcache_connect_server()) {
				$file = strtolower($type.'_'.$filename.'_'.$id);
				
				if ($this->memcache_savecache($file, $array)) {
					$this->count['Written']++;
					return true;
				}
			} else {
				if ($id) {
					$filedir = $this->set['CachePath'].DIRECTORY_SEPARATOR.'cd_'.strtolower($type).DIRECTORY_SEPARATOR.$this->idtosubdir($id, 3).DIRECTORY_SEPARATOR;
					$file = $filedir.'c_'.strtolower($filename).'_'.$id.'.php';
				} else {
					$filedir = $this->set['CachePath'].DIRECTORY_SEPARATOR.'cd_'.strtolower($type).DIRECTORY_SEPARATOR;
					$file = $filedir.'c_'.strtolower($filename).'.php';
				}
				
				if (is_dir($filedir) || $this->secobj->makedir($filedir, 0777)) {
					$filecontent .= '<?php header(\'HTTP/1.0 404 Not Found\'); exit();/******Facula fw Cache Content******';
					$filecontent .= serialize($array);
					$filecontent .= '******/?>';
					
					unlink($file);
					
					if (file_put_contents($file, $filecontent)) {
						$this->count['Written']++;
						return true;
					}
				}
			}
		}
		
		$this->count['FailedWrite']++;
		return false;
	}
	
	private function clearCache($type, $filename, $id = 0) {
		if ($this->pool['UseMemcached'] && $this->memcache_connect_server()) {
			if ($this->memcache_savecache(strtolower($type.'_'.$filename.'_'.$id), array())) {
				return true;
			}
		} else {
			if ($id) {
				$filedir = $this->set['CachePath'].DIRECTORY_SEPARATOR.'cd_'.strtolower($type).DIRECTORY_SEPARATOR.$this->idtosubdir($id, 3).DIRECTORY_SEPARATOR;
				$file = $filedir.'c_'.strtolower($filename).'_'.$id.'.php';
			} else {
				$filedir = $this->set['CachePath'].DIRECTORY_SEPARATOR.'cd_'.strtolower($type).DIRECTORY_SEPARATOR;
				$file = $filedir.'c_'.strtolower($filename).'.php';
			}
			
			return unlink($file);
		}
		
		return false;
	}
	
	private function memcache_connect_server() {
		if ($this->pool['LastMemcacheServerConCheck'] != time() || !$this->set['Memcache']->getVersion()) {
			switch($this->set['MemcacheType']) {
				case 'Memcache':
					if ($this->set['Memcache']->connect($this->set['MemcacheHost'], $this->set['MemcachePort'], 1) === true) {
						$this->pool['LastMemcacheServerConCheck'] = time();
						return true;
					} else {
						$this->pool['UseMemcached'] = false;
					}
					break;
					
				case 'Memcached':
					if ($this->set['Memcache']->addServer($this->set['MemcacheHost'], $this->set['MemcachePort']) === true) {
						$this->pool['LastMemcacheServerConCheck'] = time();
						return true;
					} else {
						$this->pool['UseMemcached'] = false;
					}
					break;
					
				default:
					break;
			}
		} else {
			$this->pool['LastMemcacheServerConCheck'] = time();
			return true;
		}
		
		return false;
	}
	
	private function memcache_savecache($key, $val) {
		switch($this->set['MemcacheType']) {
			case 'Memcache':
				if ($this->set['Memcache']->set($key, $val, 0, 0) && 
					$this->set['Memcache']->set($key.'_CachedTime', $this->set['Time'], 0, 0)) {
					return true;
				}			
				break;
				
			case 'Memcached':
				if ($this->set['Memcache']->set($key, $val, 0) && 
					$this->set['Memcache']->set($key.'_CachedTime', $this->set['Time'], 0)) {
					return true;
				}		
				break;
				
			default:
				break;
		}

		return false;
	}
	
	private function memcache_readcache($key) {
		$out = null;
		
		switch($this->set['MemcacheType']) {
			case 'Memcache':
				return $this->set['Memcache']->get($key);
				break;
				
			case 'Memcached':
				return $this->set['Memcache']->get($key);
				break;
				
			default:
				break;
		}
		
		return false;
	}
	
	private function memcache_get_cached_time($key) {
		$out = null;
		
		switch($this->set['MemcacheType']) {
			case 'Memcache':
				return $this->set['Memcache']->get($key.'_CachedTime');
				
				break;
				
			case 'Memcached':
				return $this->set['Memcache']->get($key.'_CachedTime');
				break;
				
			default:
				break;
		}
	
		return false;
	}
	
	private function idtosubdir($id, $depth) {
		$maxfiles = 65534; // Limits of FAT32
		$subdirname = array();
		$tmpresult = 0;
		
		// Do a preg_replace so the intval will not large than expect
		if (($tmpresult = $id / $maxfiles) > 1) {
			$subdirname[] = preg_replace('/^([0-9]+)\.(.*)/', '\1', $tmpresult);
		} else {
			$subdirname[] = 0;
		}
		
		if ($depth) {
			for($i = $depth - 1, $j = 0; $i > 0; $i--, $j++) {
				if (($tmpresult = $subdirname[$j] / $maxfiles) > 1) {
					$subdirname[] = preg_replace('/^([0-9]+)\.(.*)/', '\1', $tmpresult);
				} else {
					$subdirname[] = 0;
				}
			}
		}
		
		return implode(DIRECTORY_SEPARATOR, array_reverse($subdirname));
	}
}


?>