<?php
/*****************************************************************************
	Facula Framework Simple Home Page
	
	FaculaFramework 2009-2012 (C) Rain Lee <raincious@gmail.com>
	
	@Copyright 2009-2012 Rain Lee <raincious@gmail.com>
	@Author Rain Lee <raincious@gmail.com>
	@Package FaculaFramework
	@Version 0.2-alpha
*******************************************************************************/


$user = $ses->getSessionInfo();

$ui->assign('Returned', $sec->filterTextToWebForm($_MYPOST));

if (!$user['MemberID'] && $_MYPOST['submit']) {
	if (!$ses->signin($_MYPOST['token'], $_MYPOST['username'], $_MYPOST['password'])) {
		$ui->insertmessage($ses->error);
	}
} elseif ($user['MemberID'] && $_MYPOST['submit']) {
	if (!$ses->signout($_MYPOST['token'])) {
		$ui->insertmessage($ses->error);
	}
}

$users = new simple_users();

if ($_FILES['upload']) {
	$upload = new upload();
	if (!$upload->upload(7, "upload", "jpg,gif,png", 5000000)) {
		$ui->insertmessage($upload->error);
	}
}

$ui->assign('users', $users->getUsers());

$ui->display('test');
?>