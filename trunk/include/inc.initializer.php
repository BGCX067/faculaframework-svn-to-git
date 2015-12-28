<?php
/*****************************************************************************
	Facula Framework Initializer
	
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

define('__FACULAVERSION__', 'ALPHA 0.2');

$_runtime = $_ini = $_URLPARAM = array();

define('FACULA_ROOT', substr(dirname(__FILE__), 0, -7));
define('PROJECT_ROOT', realpath('.'));

$oops = $sec = $cac = $db = $ui = $ses = null;

$cfg = array();
if(defined('IN_FACULA_CFGSWAP_ENABLED')) {
	$cfg = array_merge($cfg, $swap);
}
unset($swap);

require(FACULA_ROOT.'/include/class.oops.php');
require(FACULA_ROOT.'/include/class.mysql.php');
require(FACULA_ROOT.'/include/class.template.php');
require(FACULA_ROOT.'/include/class.security.php');
require(FACULA_ROOT.'/include/class.session.php');
require(FACULA_ROOT.'/include/class.cache.php');
require(FACULA_ROOT.'/include/func.common.php');
require(PROJECT_ROOT.'/inc.config.php');

$_runtime['Timer']['InitStart'] = getMicrotime();

// First init the exception handle
$oops = new oops($cfg['debug']);

// Exit if we got old php version
if(PHP_VERSION < '5') {
	$oops->ouch('APP_PHP_TOO_OLD', true);
}

// Anit GLOBALS var
if (isset($_REQUEST['GLOBALS']) || isset($_FILES['GLOBALS'])) {
	$oops->ouch('APP_BAD_ATTEMPT', true);
}
// Anit long or huge request
if (isset($_SERVER['PATH_INFO'][512])) {
	$oops->ouch('APP_DATABLOCK_TOO_LARGE', true);
}
// Let's Add Slashes to REQUEST variable first, for safe.
$_MYGET = $_MYPOST = array();

// Init Security Unit here
$sec = new security($oops);

// First things will do when $sec has been loaded
if (!empty($_GET) && ($_MYGET = $sec->addSlashes($_GET))) {
	$_runtime['GetFilled'] = true;
}

if (!empty($_POST) && ($_MYPOST = $sec->addSlashes($_POST))) {
	$_runtime['PostFilled'] = true;
}
unset($_GET, $_POST); // Unset it so no one can use

// Init Cache module
$cac = new filecache($cfg['cache'], $oops, $sec);

// Init Database Unit
$db = new db($cfg['db'], $oops, $sec, $cac);

/** START OF SITE SETTING **/
$site = array();
if (!$site = $cac->read('SITE', 'SITECFG', 86400)) {
	$temp_site = array();
	
	if ($temp_site = $db->fetch_all_array('SELECT `settype`, `setkey`, `setvalue` FROM `'.$db->pre.'settings`')) { // Load sitesetting from database
		foreach($temp_site AS $key => $val) {
			$site[$val['settype']][$val['setkey']] = $val['setvalue'];
		}
		
		// Check php setting for this website
		if ($_ini = ini_get_all()) {
			$site['APIFastCGI'] = isset($_ini['cgi.check_shebang_line']);
			$site['AllowUrlFopen'] = $_ini['allow_url_fopen']['local_value'];
			$site['AllowUrlInclude'] = $_ini['allow_url_include']['local_value'];
			$site['DefaultSocketTimeout'] = $_ini['default_socket_timeout']['local_value'];
			$site['FileUploadEnabled'] = $_ini['file_uploads']['local_value'];
		}
		
		// The htaccess has been set?
		if (file_exists('.htaccess')) {
			$site['HtaccessFileExisted'] = true;
		}
		
		// Save all libraries to the libraries list
		$site['security']['Libraries'] = $sec->getLibraries();
		
		unset($temp_site);
		
		$cac->save('SITE', 'SITECFG', $site);
	} else {
		$oops->ouch('APP_CORE_INIT_FAILED|Failed on loading setting from database.', true);
	}
}
$sec->initialize($site['security']); // Will auto clean some value in it
/** END OF SITE SETTING **/

// Init Session Unit
$ses = new session($site['security'], $db, $sec, $oops, $cfg['session']['mode']);
// Save SiteSetting to Sec obj. Now do this is safe.
$sec->setSiteSetValue($site);

// Find where we are
$crscriptfilename = basename($_SERVER['SCRIPT_FILENAME']);
$_runtime['SelfRoot'] = substr($_SERVER['SCRIPT_NAME'], 0, strrpos($_SERVER['SCRIPT_NAME'], '/'.$crscriptfilename, 0));
$_runtime['WebRoot'] = $_runtime['ScriptRoot'] = (($_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_runtime['SelfRoot']);
$_runtime['Self'] = $_runtime['WebRoot'].$_SERVER['PATH_INFO'];
$_runtime['Referer'] = $_SERVER['HTTP_REFERER'];

// Will use more friendly url
if(!defined('IN_FACULA_NO_PARSEURL')) {
	if(!$cfg['facula']['PrimaryScript']) {
		$_runtime['ScriptName'] = $site['APIFastCGI'] ? $crscriptfilename.'?' : $crscriptfilename; 
	} else {
		$_runtime['ScriptName'] = $cfg['facula']['PrimaryScript'];
	}
	
	$_runtime['ScriptFull'] = $site['APIFastCGI'] ? $_SERVER['QUERY_STRING'] : $_SERVER['PATH_INFO'];
	
	// Get the self url for web page
	if ($site['general']['SiteUrlRewrite'] && $site['HtaccessFileExisted']) {
		$_runtime['BaseURL'] = $_runtime['SelfRoot'].$_runtime['ScriptFull'];
		$_runtime['RootURL'] = $_runtime['SelfRoot'];
		
		$_runtime['ActualBaseURL'] = $_runtime['ScriptRoot'].$_runtime['ScriptFull'];
		$_runtime['ActualRootURL'] = $_runtime['ScriptRoot'];
	} else {
		$_runtime['BaseURL'] = $_runtime['SelfRoot'].'/'.$_runtime['ScriptName'].$_runtime['ScriptFull'];
		$_runtime['RootURL'] = $_runtime['SelfRoot'].'/'.$_runtime['ScriptName'];
		
		$_runtime['ActualBaseURL'] = $_runtime['ScriptRoot'].'/'.$_runtime['ScriptName'].$_runtime['ScriptFull'];
		$_runtime['ActualRootURL'] = $_runtime['ScriptRoot'].'/'.$_runtime['ScriptName'];
	}
	
	$_URLPARAM = url_parameters($_runtime['ScriptFull']);
}

// Init Template Engine
$ui = new ui($cfg['template'], $site['general'], $_URLPARAM, $_runtime, $oops, $sec, $ses);
unset($cfg['template'], $cfg['cache'], $cfg['db']);

// Save all localsetting to sec class
$sec->setLocalSetValue($cfg);
unset($cfg, $site);

// Set autoload. Great thanks to tabris17.cn@hotmail
function __autoload($classname) {
	global $sec;

	return $sec->loadClass($classname);
}
$_runtime['Timer']['InitFinished'] = $_runtime['Timer']['UserCodeStart'] = getMicrotime();
?>