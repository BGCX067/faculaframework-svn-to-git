<?php 
/*****************************************************************************
	Facula Framework Simple API
	
	FaculaFramework 2009-2012 (C) Rain Lee <raincious@gmail.com>
	
	@Copyright 2009-2012 Rain Lee <raincious@gmail.com>
	@Author Rain Lee <raincious@gmail.com>
	@Package FaculaFramework
	@Version 0.2-alpha
*******************************************************************************/

class simple_users {
	private $dbobj = null;
	private $cacobj = null;
	
	public function __construct() {
		global $db, $cac;
		
		if (($this->dbobj = $db) && ($this->cacobj = $cac)) {
			return true;
		}
		
		return false;
	}
	
	public function getUsers() {
		if ($peoples = $this->dbobj->fetch_all_array("SELECT * FROM {$this->dbobj->pre}members", 'Peoples', 3600)) {
			return $peoples;
		}
	}
}




?>