<?php
/*****************************************************************************
	Facula Framework Misc Funtions
	
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

function url_parameters($urlstring) {
	global $sec;
	$urls = array();
	
	if ($urlstring) {
		$urls = explode('/', addslashes($urlstring), 16);
		
		if ($sec->isURLKEY($urls[1], 512, 1)) {
			return $urls;
		}
	}
	
	return array();
}

function getMicrotime() {
	$usec = $sec = 0;
	list($usec, $sec) = explode(' ', microtime());
	return ((float)$usec + (float)$sec);
}
?>