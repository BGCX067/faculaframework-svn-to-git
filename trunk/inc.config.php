<?php
/*****************************************************************************
	Facula Framework Configuration

	FaculaFramework 2009-2012 (C) Rain Lee <raincious@gmail.com>
	
	@Copyright 2009-2012 Rain Lee <raincious@gmail.com>
	@Author Rain Lee <raincious@gmail.com>
	@Package FaculaFramework
	@Version 0.2-alpha
	
	BRAIN-IF (LICENSES-MUST-DEFINE && CHECK-COMPATIBILITY(WTFPL, LGPL)):
		This program is free software. It comes without any warranty, to
		the extent permitted by applicable law. You can redistribute it
		and/or modify it under the terms of the Do What The Fuck You Want
		To Public License, Version 2, as published by Sam Hocevar. See
		http://sam.zoy.org/wtfpl/COPYING for more details.
	BRAIN-ELSE:
		LANGUAGE "JUST DO WHATEVER YOU WANT TO DO WITH THIS FILE"
				"COME, THIS JUST FOR CONFIGING"
	BRAIN-ENDIF
	
*******************************************************************************/

if(!defined('IN_FACULA')) {
	exit('Access Denied');
}

/**********************************************
Database Connection
**********************************************/
$cfg['db']['Tablepre']								=		'facula_';
$cfg['db']['ErrorExit']								=		true;

// First data base
$cfg['db']['Server'][0]['Host']						=		'localhost';
$cfg['db']['Server'][0]['Username']					=		'faculadbuser';
$cfg['db']['Server'][0]['Password']					=		'faculadbpassword';
$cfg['db']['Server'][0]['Database']					=		'faculadb';

// Second data base, if the first database cannot connect, this server will be connect.
$cfg['db']['Server'][1]['Host']						=		'localhost:6032';
$cfg['db']['Server'][1]['Username']					=		'faculadbuser';
$cfg['db']['Server'][1]['Password']					=		'faculadbpassword';
$cfg['db']['Server'][1]['Database']					=		'faculadb';

/**********************************************
Debug
**********************************************/
// Enable or disable the debug function
$cfg['debug']['Enabled']							=		true;

// Remote debug server
$cfg['debug']['Server']								=		'http://reports.app.r1cs.com/interface';
$cfg['debug']['ServerKey']							=		'3f8871562ed0f1e8d1a69cbf4d20c664';

// Email address that will be display on the error screen
$cfg['debug']['Mail']								=		'weboperator@letsmod.com';

// Core error screen (Full page) template
$cfg['debug']['ErrorScreenTPL']						=		'';

// Core error message (Just like message common message displayed by call ui->insertmessage) template
$cfg['debug']['ErrorMessageTPL']					=		'';

// Dir for save error logs
$cfg['debug']['LogDir']								=		PROJECT_ROOT.'/data/log/';

/**********************************************
Cache
**********************************************/
// Enable or disable the cache function
$cfg['cache']['Enabled']							=		true;

// File cache Dir
$cfg['cache']['CacheDir']							=		PROJECT_ROOT.'/data/cache/run/';

// If expired time not set, use this time for default. Set it to a very large number to keep cache not update
$cfg['cache']['DefaultExpire']						=		99999999999;

// Memcache server address
$cfg['cache']['MencachedHost']						=		'localhost';

// Memcache server post
$cfg['cache']['MencachedPort']						=		11211;

/**********************************************
Template Setting
**********************************************/
// Remove useless characters (return, newline, tab) from template file
$cfg['template']['TrimTemplate']					=		true;

// Template file path
$cfg['template']['TemplatePool']					=		PROJECT_ROOT.'/data/pool/templates/';

// When a template has been compiled, it will be save to below path for next call (Save run time)
$cfg['template']['CompiledPath']					=		PROJECT_ROOT.'/data/cache/template/';

// Cached Page will be save to this path
$cfg['template']['CachePool']						=		PROJECT_ROOT.'/data/cache/pages/';

// Always compile template.
$cfg['template']['ForceRenew']						=		false;

// Cache all page called by ui->display_showmessage
$cfg['template']['CacheMessagePages']				=		true;

// Max item limit of page switcher
$cfg['template']['TplMaxSwitchItem']				=		20;

/**********************************************
Upload API
**********************************************/
// API setting, may not included in Facula Framework
// Uploaded file will be move to this dir
$cfg['upload']['UploadDir']							=		PROJECT_ROOT.'/data/temp/uploads/';
?>