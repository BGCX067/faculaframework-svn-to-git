<?php
/*****************************************************************************
	Facula Framework MySQL Operater
	
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

class db {
	private $oopsobj = null;
	private $secobj = null;
	
	public $pre = '';
	
	private $set = array();
	private $pool = array();
	private $link_id = 0;
	private $query_id = 0;
	public $count = array('Success' => 0, 'Failed' => 0);
	
	public function __construct(&$cfg, &$oops, &$sec, &$cac) {
		if ($oops && $sec && $cac && $cfg) {
			$this->oopsobj = $oops;
			$this->secobj = $sec;
			$this->cacobj = $cac;
			
			$this->set = array('Time' => $this->secobj->time());
			
			$this->pre = $cfg['Tablepre'];
			$this->exitonerror = $cfg['ErrorExit'];
			
			if (is_array($cfg['Server'])) {
				$this->pool['Servers'] = $cfg['Server'];
			}
			
			$cfg = null;
			return true;
		} else {
			exit('A critical problem prevents initialization while trying to create '. __CLASS__.'.');
		}
		
		$s = $servers = null;
		return false;
	}
	
	public function __destruct() {
		return $this->disconnect();
	}
	
	public function oops($erstring, $exit = false) {
		$error_codes = array('ERROR_DB_CONNECT_FAILED' => 'App cannot connect to database server',
							'ERROR_DB_CONNECTION_LOST' => 'Data link to database server was lost',
							'ERROR_ALL_DBSERVER_UNREACHABLE' => 'We tried all database servers but none is reachable.',
							'ERROR_DB_QUERY_FAILED_BEFORE' => 'Application encountered a fail on database before, futher query will be cancelled for protect the database.',
							'ERROR_DB_QUERY_FAILED' => 'There problem while asking data from database');

		if ($this->oopsobj->pushmsg($error_codes) && $this->oopsobj->ouch($erstring, $exit || $this->exitonerror)) {
			return true;
		}
		
		return false;
	}
	
	private function connect() {
		$nowtime = time();
		
		if ($this->link_id && ($nowtime == $this->set['LastConCheck'] || $this->isConnected($nowtime))) {
			$this->set['Connected'] = true;
			return true;
		} else {
			if (isset($this->pool['Servers'])) {
				foreach ($this->pool['Servers'] AS $key => $val) {
					$this->error = ''; // Clear errors so we can go on process
					$this->set['ActivedServer'] = $val;
					
					if ($this->reach($val)) {
						$this->pool['ServerSelected'] = true;
						break;
					}
				}
				
				if ($this->pool['ServerSelected']) {
					unset($this->pool['Servers']);
					return true;
				} else {
					unset($this->pool['Servers']);
					$this->error = 'ERROR_ALL_DBSERVER_UNREACHABLE';
					$this->oops($this->error, true);
				}
			}
		}
		
		return false;
	}
	
	private function reach($server) {
		// If param not set yet, just use old data if it existed
		if ($server['Host'] && $server['Username'] && $server['Password'] && $server['Database']) { // Again, welcome to the ifinitment node
			if ($this->link_id) mysql_close(); // close by force
			$this->link_id = 0;
			$this->set['Connected'] = false;
			$server['Charset'] = $server['Charset'] ? $server['Charset'] : 'utf8';
			
			if (!$this->link_id = mysql_connect($server['Host'], $server['Username'], $server['Password'])) {
				$this->error = 'ERROR_DB_CONNECT_FAILED|DB->CONNECT FAILED';
			} elseif(!mysql_select_db($server['Database'], $this->link_id)) {
				$this->error = 'ERROR_DB_CONNECT_FAILED|DB->CONNECT CANNOT SELECT REACH DB';
			} elseif (!mysql_query("SET NAMES '{$server['Charset']}', character_set_connection = '{$server['Charset']}', character_set_results = '{$server['Charset']}', character_set_client = '{$server['Charset']}', sql_mode = ''", $this->link_id)) {
				$this->error = 'ERROR_DB_CONNECT_FAILED|DB->CONNECT CANNOT SETTING CHAR';
			} else {
				$this->set['LastConCheck'] = time();
				$this->set['Connected'] = true;
				return true;
			}
		} else {
			$this->error = 'ERROR_DB_CONNECT_FAILED|DB->CONNECT FAILED (NO SETTING)';
		}
		
		$this->oops($this->error, true);
		
		return false;
	}
	
	private function disconnect() {
		$nowtime = time();
		
		if ($nowtime == $this->set['LastConCheck'] || $this->isConnected($nowtime)) {
			if(!@mysql_close($this->link_id)) {
				$this->error = 'ERROR_DB_CONNECTION_LOST|DB->DISCONNECT FAILED';
				$this->oops($this->error);
			} else {
				$this->set['Connected'] = false;
				return true;
			}
		} else {
			$this->error = 'ERROR_DB_NO_CONNECT_YET|DB->DISCONNECT FAILED';
		}
		
		return false;
	}
	
	private function isConnected(&$nowtime = 0) {
		if ($this->link_id) {
			$this->set['LastConCheck'] = $nowtime;
			return mysql_ping($this->link_id);
		}

		return false;
	}
	
	public function escape($string) {
		$replaces = array(
			'Search' => array("\r\n", "\r", "\n"),
			'Replace' => array("\n", "\n", "\r\n")
		);
		
		if ($this->connect() && $string) {		
			return mysql_real_escape_string(trim($this->secobj->delSlashes(str_replace($replaces['Search'], $replaces['Replace'], $string))), $this->link_id);
		}
		
		return '';
	}
	
	public function filterText(&$input) {
		$replaces = array(
			'Search' => array('&', '"', '\'', '<', '>'),
			'Replace' => array('&amp;', '&quot;', '&#039;', '&lt;', '&gt;')
		);
		$result = array();
		// Oops, more faster
		if (is_array($input[0])) {
			foreach($input AS $key => $val) {
				$result[$key] = str_replace($replaces['Search'], $replaces['Replace'], $val);
			}
			
			return $result;
		}
		
		return str_replace($replaces['Search'], $replaces['Replace'], $input);
	}
	
	public function query($sql, &$affected = 0, $cacheexpired = 0) {
		if ($sql) {
			if ($cacheexpired && ($out = $this->cacobj->read('DBCACHED', ($sqlhash = $this->secobj->getStringHash($sql, 10240)), $cacheexpired))) {
				return $out;
			}
			
			if (!$this->count['Failed']) {
				if ($this->connect()) {
					if (!$sql || !($this->query_id = mysql_query($sql, $this->link_id))) {
						$this->error = 'ERROR_DB_QUERY_FAILED|'.mysql_error($this->link_id).": ".$sql;
					} else {
						$this->count['Success']++;
						$this->set['Affected'] = $affected = mysql_affected_rows($this->link_id);
						// count how many we done
						return $this->query_id;
					}
				} else {
					$this->error = 'ERROR_DB_CONNECTION_LOST|DB->QUERY FAILED';
				}
			} else {
				$this->error = 'ERROR_DB_QUERY_FAILED_BEFORE|'.$sql;
			}
		}
		
		// If we failed anyway
		$this->count['Failed']++;
		$this->oops($this->error);
		
		return false;
	}
	
	public function query_first($sql, $cacheexpired = 0, $filterCallback = null) {
		$tmp_queryid = 0;
		$out = array();
		$sqlhash = '';
		
		if ($sql) {
			if ($cacheexpired && ($out = $this->cacobj->read('DBCACHED', ($sqlhash = $this->secobj->getStringHash($sql, 10240)), $cacheexpired))) {
				return $out;
			}
			
			if ($tmp_queryid = $this->query($sql)) {
				if ($out = $this->fetch_array($tmp_queryid, $filterCallback)) {
					if ($cacheexpired) {
						$this->cacobj->save('DBCACHED', $sqlhash, $out);
					}
					
					return $out;
				}
			}
		}
		
		return false;
	}
	
	private function fetch_array($queryid, &$filterCallback = null) {
		$out = array();
		
		if ($queryid) {
			if ($out = mysql_fetch_array($queryid, MYSQL_ASSOC)) {
				$this->free_result($queryid);
				
				if (is_callable($filterCallback)) { 
					$out = $filterCallback($out);
				} else {
					$out = $this->filterText($out);
				}
				
				return $out;
			}
		}
		
		return false;
	}
	
	public function fetch_all_array($sql, $cacheexpired = 0, $keyname = null, $filterCallback = null) {
		$tmp_queryid = 0;
		$row = $out = array();
		$sqlhash = '';
		
		if ($sql) {
			if ($cacheexpired && ($out = $this->cacobj->read('DBCACHED', ($sqlhash = $this->secobj->getStringHash($sql, 10240)), $cacheexpired))) {
				return $out;
			}
			
			if ($tmp_queryid = $this->query($sql)) {
				while ($row = mysql_fetch_array($tmp_queryid, MYSQL_ASSOC)) {
					if($keyname) {
						$out[$row[$keyname]] = $row;
					} else {
						$out[] = $row;
					} 
				}
				
				if (isset($out[0])) {
					if (is_callable($filterCallback)) {
						$out = $filterCallback($out);
					} else {
						$out = $this->filterText($out);
					}
					
					if ($cacheexpired) {
						$this->cacobj->save('DBCACHED', $sqlhash, $out);
					}
				}
				
				$this->free_result($tmp_queryid);
				
				return $out;
			}
		}
		
		return false;
	}
	
	public function free_result($queryid) {
		if ($this->connect()) {
			if (!mysql_free_result($queryid)) {
				$this->error = 'ERROR_DB_CANNOT_FREE_RESULT|DB->FREE FAILED';
				$this->oops($this->error);
				return false;
			} else {
				return true;
			}
		}
		
		return false;
	}
	
	public function query_update($table, &$data, $where) {
		$out = 0;
		$datas = '';
		
		if ($datas = $this->get_update_query_string($data)) {
			if ($table && $datas && $where) {
				if ($out = $this->query('UPDATE `'.$this->pre.$table.'` SET '.$datas.' WHERE '.$where.';')) {
					if (!$this->error) {
						return $out;
					}
				}
			}
		}
		
		return false;
	}
	
	public function get_update_query_string($data) {
		if (is_array($data)) {
			foreach ($data AS $key => $val) {
				if ($val) 
					$datas .= '`'.$key.'` = \''.$this->escape($val).'\', ';
				else
					$datas .= '`'.$key.'` = NULL, ';
			}
		}
		
		return substr($datas, 0, strlen($datas) - 2);
	}
	
	public function query_multi_update($tabledata, $where = 1, &$affected = 0) {
		$out = array();
		
		$tables = '';
		$sets = '';
		/*****************************
		Legal format of $table
		$table = array( 'TableA' = array(
											'Field_1' = 1;
											'Field_2' = 2;
										);
						'TableB' = array( 
											'DATA' = 1;
											'A' = 1;
										);
						);
		
		******************************/
		if (is_array($tabledata)) {
			foreach ($tabledata AS $tkey => $tval) {
				$tables .= "`".trim($this->pre.$tkey)."`, ";
				
				foreach ($tval AS $skey => $sval) {
					if ($sval) 
						$sets .= '`'.$this->pre.$tkey.'`.`'.$skey.'` = \''.$this->escape($sval).'\', ';
					else
						$sets .= '`'.$this->pre.$tkey.'`.`'.$skey.'` = NULL, ';
				}
			}
			$tables = substr($tables, 0, strlen($tables) - 2); // Couhely use it
			$sets = substr($sets, 0, strlen($sets) - 2); // Use it as above

			if ($tables && $sets && $where) {
				if ($out = $this->query('UPDATE '.$tables.' SET '.$sets.' WHERE '.$where.';', $affected = 0)) {
					if (!$this->error) {
						return $out;
					}
				}
			}
		}
		
		return false;
	}
	
	public function query_insert($table, $data) {
		$sql = '';
		$fields = array();
		$sets = array();
		$returnit = 0;
		/*****************************
		Legal format of $table
		$table = array( 'FieldA' = 1,
						'FieldB' = 'DATA'
						);
		
		******************************/
		foreach ($data AS $key => $val) {
			$fields[] = '`'.$this->escape($key).'`';
			$sets[] = '\''.$this->escape($val).'\'';
		}
		
		$sql = 'INSERT INTO `'.$this->pre.$table.'` ('.implode(',', $fields).') VALUES ('.implode(',', $sets).');';

		if ($this->query($sql)) {
			$returnit = mysql_insert_id();
			return $returnit ? $returnit : true;
		}
		
		return false;
	}
	
	public function query_multi_insert($table, $data, &$affected = 0) {
		$sql = "";
		$ilooped = 0;
		$idataemcount = 0;
		$fields = array();
		$set = $sets = array();
		$returnit = 0;
		/*****************************
		Legal format of $table
		$table = array( array('Data' => "A", 'ID' => 1)
						array('Data' => "A", 'ID' => 2),
						array('Data' => "B", 'ID' => 3),
						array('Data' => "B", 'ID' => 3),
						);
		
		******************************/

		$idataemcount = sizeof($data);
		
		foreach ($data AS $key => $val) {
			foreach ($val AS $ekey => $eval) {
				if (!$ilooped) $fields[] = '`'.$this->escape($ekey).'`';
				$set[] = '\''.$this->escape($eval).'\'';
			}
			$sets[] = '('.implode(',', $set).')';
			$set = array();
			$ilooped = 1;
		}
		
		$sql = 'INSERT INTO `'.$this->pre.$table.'` ('.implode(',', $fields).') VALUES '.implode(',', $sets).';';

		if ($this->query($sql, $affected)) {
			$returnit = mysql_insert_id();
			return $returnit ? $returnit : true;
		}
		
		return false;
	}
}
?>