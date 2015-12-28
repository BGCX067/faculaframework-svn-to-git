<?php
/*****************************************************************************
	Facula Framework Dispatcher Simple
	
	FaculaFramework 2009-2012 (C) Rain Lee <raincious@gmail.com>
	
	@Copyright 2009-2012 Rain Lee <raincious@gmail.com>
	@Author Rain Lee <raincious@gmail.com>
	@Package FaculaFramework
	@Version 0.2-alpha
*******************************************************************************/

if(!defined('IN_FACULA')) {
	define('IN_FACULA', true); // Allow facula to run
}

require('./include/inc.initializer.php'); // Call facula in

//////////////////////////////////YOUR CODE BELOW//////////////////////////////////

// You don't have to follow below code to coding
if ($_URLPARAM[1]) { // Check the first param of URL: http://www.yoursite.com/param1/param2/param3/param4/..../param64/
	if (file_exists(PROJECT_ROOT."/pages/page.{$_URLPARAM[1]}.php")) {
		require(PROJECT_ROOT."/pages/page.{$_URLPARAM[1]}.php");
	} else {
		$ui->display_httpmessage("404", "HTTP/1.0 404 Not Found");
	}
} else { // If there is no param1 set, call the home page
	require(PROJECT_ROOT.'/pages/homepage.index.php');
}

//////////////////////////////////YOUR CODE ABOVE//////////////////////////////////

// Following code for optimizing, disable it by remove code or define COUNT_RUNTIME to false
define('COUNT_RUNTIME', true);

if(defined('COUNT_RUNTIME')) {
	function endCount() {
		global $_runtime, $_SERVER, $db, $cac;
		
		$_runtime['Timer']['UserCodeFinished'] = getMicrotime();
		$runtimecore = $_runtime['Timer']['InitFinished'] - $_runtime['Timer']['InitStart'];
		$runtimeuser = $_runtime['Timer']['UserCodeFinished'] - $_runtime['Timer']['UserCodeStart'];
		$runtimetotal = $runtimecore + $runtimeuser;
		$runtimeava = (file_get_contents(PROJECT_ROOT.'/p-ava.log') + $runtimetotal) / 2;
		
		file_put_contents(PROJECT_ROOT.'/p.log', sprintf('[Performance Log] Total Running time: %s (Core code %s, User code %s); Database query: %s (%s failed); Cache query: %sr / %sw; Men usage: %s kilobytes (peak: %s kilobytes); URL: %s'."\r\n", 
														$runtimetotal, 
														$runtimecore, 
														$runtimeuser, 
														$db->count['Success'], 
														$db->count['Failed'], 
														$cac->count['Readed'], 
														$cac->count['Written'], 
														memory_get_usage(true) / 1024, 
														memory_get_peak_usage(true) / 1024, 
														$_SERVER['REQUEST_URI']), FILE_APPEND);
		file_put_contents(PROJECT_ROOT.'/p-ava.log', $runtimeava);
	}

	register_shutdown_function('endCount');
}

?>