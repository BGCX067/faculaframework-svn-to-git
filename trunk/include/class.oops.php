<?php 
/*****************************************************************************
	Facula Framework Oops & Ouch
	
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

class oops {
	private $uiobj = null;
	private $secobj = null;
	private $sesobj = null;
	
	private $errorcount = 0;
	private $ouchcount = 0;
	
	private $isexiting = false;
	
	private $set = array();
	private $pool = array();
	private $messages = array(
							'APP_CORE_ERROR' => 'Application encountered a internal problem.',
							'APP_CORE_INIT_FAILED' => 'Application encountered a internal problem while initialization.',
							'APP_ROAD_CLOSED' => 'You trying to access this app from a forbidden entry, and our clever app seems don\'t like this, so it kicked you out. Sorry.',
							'APP_DATABLOCK_TOO_LARGE' => 'Your request is already exceed the limit.',
							'APP_BAD_ATTEMPT' => 'Seriously, please don\'t try to hack our site.',
							'APP_API_NOT_FOUND' => 'The API was not found.',
							'APP_PHP_TOO_OLD' => 'You using a old version of PHP, which is not support this web app.',
							'APP_INIT_STOPED' => 'A critical problem prevents Initialize.'
							);
	
	private $errordisplay = array(
		'Screen' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><meta name="robots" content="noindex, nofollow, noarchive" /><title>Oops!</title></head><body style="font-family:\'Tahoma\', \'Helvetica\',\'Geneva\',\'sans-serif\';padding:0;margin:0 0 70px 0;background:#fff;overflow:auto;"><div style="width:900px;margin:0 auto;overflow:auto;"><div style="width:50%;float:left;height:500px;background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAWsAAADCCAMAAAC4/BPYAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA2RpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDo0QjgxNTM2RDQ2QzhFMTExQUQyMkIwRDA5RTFCQURERiIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDo5MjkwNjM3MkM4QkMxMUUxOEI1M0ZCREU1NkI4QTMwRCIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDo5MjkwNjM3MUM4QkMxMUUxOEI1M0ZCREU1NkI4QTMwRCIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ1M1IFdpbmRvd3MiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDo5MkVBNUI3REJDQzhFMTExQUQyMkIwRDA5RTFCQURERiIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo0QjgxNTM2RDQ2QzhFMTExQUQyMkIwRDA5RTFCQURERiIvPiA8L3JkZjpEZXNjcmlwdGlv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) center center no-repeat;"></div><div style="width:50%;float:right;font-size:12px;padding-top:150px;"><span style="display:block;color:#999;">Sorry, but we got an error while trying to response your request:</span><span style="display:block;color:#333;font-size:12px;font-weight:bold;padding: 3px 0;">%ERRORCODE% <span style="display:inline;color:#999;font-weight:normal;font-size:10px;padding:1px;-webkit-text-size-adjust:none;">%ERRORNOTICE%</span></span><div style="padding: 10px 0;"><span style="display:block;color:#333;padding: 3px 0;"><a href="javascript:;" onclick="window.location.reload(true);">Refresh</a> this page may fix this. If not:</span><ol><li style="padding:3px;">This problem may made by a unexpected internal process and cannot be fixed without help from a maintainer.</li><li style="padding:3px;">Report this problem to our friendly admin team is a good idea, please send email to: <a href="mailto:%ERRORREPORTMAIL%">%ERRORREPORTMAIL%</a>. We will fix it when figured out how to.</li><li style="padding:3px;">Keep trying to <a href="javascript:;" onclick="window.location.reload(true);">refresh</a> this page.</li><li style="padding:3px;">If you had enough on trying, click here to <a href="javascript:;" onclick="window.history.back();">go back</a> and browse other page. If you got same problem on all other pages, maybe the website already down.</li></ol></div><div style="padding: 10px 0;"><span style="display:block;color:#333;padding: 3px 0;">If you are the tech support guy of this website, following information will truly help you on fixing this problem.</span><ul><li style="padding:3px;">Debug is %DEBUGSTATUS%</li><li style="padding:3px;"><a href="javascript:;" onclick="document.getElementById(\'track-window\').style[\'display\'] = \'block\';document.getElementById(\'footer\').parentNode.removeChild(document.getElementById(\'footer\'));this.parentNode.removeChild(this);">Display Track information</a><div id="track-window" style="display:none;padding:1px 1px 0 1px;margin:0;border:1px solid #eee;overflow:auto;">%TRACKSTRING%</div></li><li style="padding:3px;">%DEBUGEXIT%</li></ul></div></div></div><div id="footer" style="position:fixed;bottom:0px;left:0px;z-index:0;width:100%;font-size:12px;border-top:1px solid #eee;clear:both;box-shadow:0 0 5px 0 #000;background-image:url(data:image/gif;base64,R0lGODlhAwADAIAAAOXl5f///yH/C1hNUCBEYXRhWE1QPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4gPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iQWRvYmUgWE1QIENvcmUgNS4wLWMwNjAgNjEuMTM0Nzc3LCAyMDEwLzAyLzEyLTE3OjMyOjAwICAgICAgICAiPiA8cmRmOlJERiB4bWxuczpyZGY9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkvMDIvMjItcmRmLXN5bnRheC1ucyMiPiA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDo5MkRBQ0I4QkM4QTYxMUUxQTVEMUU1RDk5RUY5QTQyRiIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDo5MkRBQ0I4QUM4QTYxMUUxQTVEMUU1RDk5RUY5QTQyRiIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ1M1IFdpbmRvd3MiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmRpZDo0ODgxNTM2RDQ2QzhFMTExQUQyMkIwRDA5RTFCQURERiIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo0ODgxNTM2RDQ2QzhFMTExQUQyMkIwRDA5RTFCQURERiIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PgH//v38+/r5+Pf29fTz8vHw7+7t7Ovq6ejn5uXk4+Lh4N/e3dzb2tnY19bV1NPS0dDPzs3My8rJyMfGxcTDwsHAv769vLu6ubi3trW0s7KxsK+urayrqqmop6alpKOioaCfnp2cm5qZmJeWlZSTkpGQj46NjIuKiYiHhoWEg4KBgH9+fXx7enl4d3Z1dHNycXBvbm1sa2ppaGdmZWRjYmFgX15dXFtaWVhXVlVUU1JRUE9OTUxLSklIR0ZFRENCQUA/Pj08Ozo5ODc2NTQzMjEwLy4tLCsqKSgnJiUkIyIhIB8eHRwbGhkYFxYVFBMSERAPDg0MCwoJCAcGBQQDAgEAACH5BAAAAAAALAAAAAADAAMAAAIETHAZBQA7);"><div style="width:900px;padding:10px;margin: 0 auto;"><a style="display:block;float:right;padding:5px;" href="http://faculaframework.googlecode.com/"><img style="margin:0;border:0;" alt="Facula Framework Logo" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAJYAAAAhCAMAAAAMEKIdAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyBpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNSBXaW5kb3dzIiB4bXBNTTpJbnN0YW5jZUlEPSJ4bXAuaWlkOjQwMTAyQUZEQzhBNzExRTE5MDUwRkU1QTI2QkZEMzg5IiB4bXBNTTpEb2N1bWVudElEPSJ4bXAuZGlkOjQwMTAyQUZFQzhBNzExRTE5MDUwRkU1QTI2QkZEMzg5Ij4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6NDAxMDJBRkJDOEE3MTFFMTkwNTBGRTVBMjZCRkQzODkiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6NDAxMDJBRkNDOEE3MTFFMTkwNTBGRTVBMjZCRkQzODkiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz4vv9S6AAAAt1BMVEXAwMDFxcVmZma7u7vR0dGDg4Pr6+v09PTu7u62travr6/KysqhoaHx8fHOzs6/v7/V1dXi4uKZmZmQkJDk5OTY2Njm5ub5+fn+/v7a2tq0tLT29vbf39+JiYmysrKmpqapqamMjIzl5eWUlJR3d3fo6OjS0tKkpKStra3e3t76+vr4+Pjc3Nz8/PzJycnMzMyqqqr7+/vh4eGcnJzb29v9/f3p6emgoKCfn5+5ubm1tbWbm5v////MDhWTAAAAPXRSTlP///////////////////////////////////////////////////////////////////////////////8ACS+sXwAABmBJREFUeNrMmAt3ojoQx5PwFgKIvFF8YcvLil2lxeb7f66bIArYds/evXvO3Tk9SiKZ/PhnZhIKyP9t/ubJKR47wf9O9Xo4HKRbC59O0V+BdXx6eTkdu0Z9eHkR/wosginXpruml87+iiW7N6vPV1nFDX4QGm9OjnN6rXPaiDb1vve5aR/Oc13vcbrii74vV/Hwcl9EfNr5XWwdwM3gNfIwBC/eKCbvtxwo0AbA6P6bAw7MjwyA/DidA8Dpl+SqX28Rn59eTqTHejKfmDnXJTbp/K/9qOiJApuvrvtqQugxLPArWBG8P+dPtZI30vBJnLtaHBiJHQHuwMF9757jys69JzO1uAGWycEWi+MesUoOAq78EiXHRyK9toGCDxwH2qu8dvPNoTzf1ALpYBqGrECZU25BeIQKJ46cThVugKW0WKKijG8iHlBeHQV8FV0boJg1p+j0UgKKonD42qkchnVrsq6koT8uLn01hh12GcfTsddprPRYfAxarDh+wJrHSo6Vx7GkfYQ4dtQYiHOoqzG1gHbatDOGQ6wq+THEClI6q5umu6veXAr8hwnTuMfSrz+LaTrGOnMpVQN+GkzNjRXex64M0jRVOAWUEnXHsYbbjZXxkWFtB5GZc0vqb88tr+jiahk8uJ0v0/4x9CXHJq6Xq3p0k71MabC5q5X7OchxOxwsqU2xCjiFKsen6SrmdFaAMFilKiDPiaDqrbGonWtL9hVcv0imLXcPbjNtNcDSlBZLW46wfKAxnfaK9l25LpaatlJ1BQb2LkhXNeY0TVtiIrqKpsUOxUI3Y0FYJWE7amW1cjmW9inFrGWPpVot1s7SRvS1ZrVRFVha/Q2Xw6mmAq5LL2kws6hxuapp6YrmAMMSaOwxY7IniX2VYf2GWyxL/ISlDbHiK5Y1wgJWfM2flfX97rZb8bdLhdMplRKJlgUi3Ib8O1r3sTVBSdga1ZDJNV8sHmtPObN6LDhLW6zZbIiFrdkKcGxbWM4s/A2VrJn37XqpFiqc+sQL5rdy+o6E+zRignpb0wzBAgof3E0Xs35dwaKVZbcYYcHFwOCXxZ3sY6Vf0E/sYywDCUFWMst4hKjGfoi6Ve2fcrFw7t7TRbtIY7UkbcHZ89ZsZWENi7Vv08Q6O0pairN7ktZbk/wMSxKQcf/lGW3pHikLaD0O+vMWfdzOR1NByNpatFgMKoE+0LOeLfT+ibAuCKpXJUnyxt9nnW5Vr/gZloqSHqFEiJWsIEGC2ZV8nLHCQnWcXBWw1+g9b7EQ6jPjuEWTexU9V+hWFotGEAT0LBksQqCNrriSsTWa9Xb+iPWBhG5lizWqBoV1i97Zhl0KNMxCVVcNmrNsBr9BSKAdkKZFp6SN0HvV2nNGTIQGk2RtMLBhE4SSqehTHVDyLEbPKAwyvvmY8GuKGX5Wa92pxaMkG/xEm23xwc26SwKh2yn1bdduupFunyqq/4E+zoM1f0cf7XHkOEk+Wn+7ZrIWkmcMt+uPKszOzHvCQrMQ9z2WmLldS87s8/CUbWedjJHNQ6iattRtHoU8pR16ec8fz57eTDrb2SAYixxn06v/Qsy7dWAVnIZLHtnVWgjZapTT0jfXifHbbz5n2ECD/+Xb+S9uhUxVRrCjROjdNng5pAGcXEvl72FFbbj7ki2TmrfpRW1G2KT6eEHp55gcsU/kI6kd2iXZIq8T/LhP6CwcaDIfP5hqLK/mKNHLpO38Tawi3GFc7KsGz8OgcfaVwdO/UDwbKlRx6IlbSZrkZqOHUxKGMm9Gk9GhazqBeztrw2FfoWTdHlC8jG6dOPoPr69eRRdxerxgsnNFw8gnmDQ2UQP7gsVqZ9jl1g2gFEpEvBwbkZhNNaLKEvTjXj08J/vmtP/v1bpgz9sXNAdtQzWg10TEEIlaOpWuQimgmaECWzTo7tJIhkz4t8uoJJXbyy0h/MD8dpbfwIraT2l/2RHTyGnT2FGsXbPP9Sh6uxyrKo8uIrGbcyMTXY8mw9iaGret35skifznsIwWy5AID/jmItH3EygSnp7/IYA5MVQCaD7NG7WpCX2nMU0SwP7kDJNk3YWQKCTb+f5PYflFOwn99MVdURzpRbEnXk78mqmS78mRzYVdOrt3JjmtVdEdC9PdUO9aZzNJhPpPYf23/4PAy/3F2H9Lkqr4K7BGssMf4Nu37n8EGACne41ukCtUnQAAAABJRU5ErkJggg==" /></a><p style="padding:3px 0;margin:0px;"><a href="http://faculaframework.googlecode.com/">Facula Framework</a> Copyright 2010-2012 (C) Ni Rui (also known as Raincious raincious@gmail.com)<br /></p><span style="font-size:9px;-webkit-text-size-adjust:none;">Facula Framework is a free software, provided under the terms of the GNU LGPL as published by the Free Software Foundation, version 3.</span><br /><span style="font-size:9px;-webkit-text-size-adjust:none;">If you got anything want to ask, please click <a style="padding:1px;background:#0063b0;color:#fff;border-radius:2px;" href="http://faculaframework.googlecode.com/">here</a> to find our contact address.</span></div></div></body></html>',
		'Message' => '%ERRORCODE% (%ERRORNOTICE%)',
	);
	
	public function __construct(&$set) {
		if ($set['Enabled']) {
			error_reporting(E_ALL ^ E_NOTICE);
			ini_set('display_errors', 'On');
			ini_set('display_startup_errors', 'On');
		} else {
			error_reporting(0);
			ini_set('display_errors', 'Off');
			ini_set('display_startup_errors', 'Off');
		}
		
		$this->set = array(
			'Time' => time(),
			'TimeString' => date(DATE_ATOM, time()),
			'DateString' => date('Y-m-d', time()),
			'DebugEmail' => $set['Mail'],
			'DebugEnabled' => $set['Enabled'],
			'DebugServer' => $set['Server'],
			'DebugServerKey' => $set['ServerKey']);
		
		if ($set['ErrorScreenTPL']) {
			$this->errordisplay['Screen'] = $set['ErrorScreenTPL'];
		}
		
		if ($set['ErrorMessageTPL']) {
			$this->errordisplay['Message'] = $set['ErrorMessageTPL'];
		}
		
		$set = null;
		
		set_error_handler(array(&$this, 'error'));
		
		return true;
	}
	
	public function setObjs($type, &$object) {
		switch($type) {
			case 'UI':
				if (!$this->uiobj && ($this->uiobj = $object)) {
					return true;
				}
				break;
				
			case 'SEC':
				if (!$this->secobj && ($this->secobj = $object)) {
					return true;
				}
				break;
				
			default:
				break;
		}
		
		return false; 
	}
	
	public function pushmsg($am) {
		$pam = array();
		
		if (empty($am)) return false;
		
		if (is_array($am)) {
			$this->messages = $this->messages + $am;
		} else {
			$pam = explode('|', $am);
			$this->messages[$pam[0]] = $pam[1];
		}
		
		return true;
	}
	
	public function error($errno, $errstr, $errfile, $errline, $errcontext) {
		$willexit = false;
		
		$this->errorcount++;
		
		switch($errno) {
			case E_ERROR:
				$willexit = true;
				break;
				
			case E_WARNING:
				break;

			case E_PARSE:
				$willexit = true;
				break;

			case E_NOTICE:
				if (!$this->set['DebugEnabled']) { // hope if is cheap
					return false;
				}
				break;
				
			case E_CORE_ERROR:
				$willexit = true;
				break;	
				
			case E_CORE_WARNING:
				$willexit = true;
				break;	
				
			case E_COMPILE_ERROR:
				$willexit = true;
				break;	
				
			case E_COMPILE_WARNING:
				$willexit = true;
				break;	
				
			case E_USER_ERROR:
				$willexit = true;
				break;	

			case E_USER_WARNING:
				break;	

			case E_USER_NOTICE:
				break;	
			
			default:
				break;
		}
		
		$this->pool['ErrorQs'][] = array('ErrorNo' => $errno, 'ErrorString' => $errstr, 'ErrorFile' => $errfile, 'ErrorLine' => $errline, 'ErrorContext' => $errcontext);
		
		if ($willexit) {
			return ($this->set['DebugEnabled'] ? $this->ouch("APP_CORE_ERROR|Error #{$errno}: {$errstr} in {$errfile} (line: {$errstr})", true) : $this->ouch("APP_CORE_ERROR|Error #{$errno}: {$errstr}", true));
		}
		
		return true;
	}
	
	public function get_errors() {
		return $this->pool['ErrorQs'];
	}
	
	public function ouch($erstring, $exit = false) {
		$btstring = $attinfo = $message = '';
		$bt = $bts = $btstmp = array();
		$calllevel = 0;
		
		if ($erstring) {
			if ($exit) {
				$message = str_replace('%DEBUGEXIT%', 'Application will exit after this error.', $this->errordisplay['Screen']);
			} else {
				$message = str_replace('%DEBUGEXIT%', 'Application will passby this error.', $this->errordisplay['Message']);
			}
			
			$err = explode('|', $erstring, 2);	
			
			$message = str_replace('%ERRORREPORTMAIL%', $this->set['DebugEmail'], $message);
			$message = str_replace('%ERRORCODE%', isset($this->messages[$err[0]]) ? $this->messages[$err[0]] : $err[0], $message);
			
			if ($err[1] && $this->set['DebugEnabled']) {
				$message = str_replace('%ERRORNOTICE%', htmlspecialchars($err[1]), $message);
			} else {
				$message = str_replace('%ERRORNOTICE%', 'That\'s all we known.', $message);
			}
			
			if ($bt = debug_backtrace()) {
				unset($bt[0], $bt[1]); // Unset the caller of Error
				$bt = array_values($bt);
				
				$calllevel = count($bt);
				
				foreach($bt AS $key => $val) {
					$bts[] = array('file' => $btstmp['file'] = (isset($val['file']) && is_string($val['file'])) ? str_replace(array(FACULA_ROOT, PROJECT_ROOT), array('[Facula Dir]', '[Project Dir]'), $val['file']) : '',
								'line' => $btstmp['line'] = (isset($val['line']) && is_integer($val['line'])) ? $val['line'] : 0,
								'class' => $btstmp['class'] = (isset($val['class']) && is_string($val['class'])) ? $val['class'] : '',
								'function' => $btstmp['function'] = (isset($val['function']) && is_string($val['function'])) ? $val['function'] : '',
								'args' => $btstmp['args'] = (isset($val['args']) && is_array($val['args'])) ? $this->convertMixToString(', ', str_replace(array(FACULA_ROOT, PROJECT_ROOT), array('[Facula Dir]', '[Project Dir]'), $val['args'])) : '',
					);
					$btstring .= '<div style="background:#f9f9f9;width:100%;overflow:auto;margin-bottom:1px;"><div style="width:10%;float:left;padding:10px 0;font-size:16px;text-align:center;color:#bbb;">'.$calllevel.'</div><div style="width:90%;float:right;"><div style="word-wrap:break-word;word-break:break-all;padding:3px;"><b>'.($btstmp['file'] ? $btstmp['file'] : 'Core Thread').'</b><br />'.($btstmp['class'] ? ($btstmp['class'].'->'.$btstmp['function'].'('.$btstmp['args'].')') : ($btstmp['function'].'('.$btstmp['args'].')')).'<br />(line: '.$btstmp['line'].')</div></div></div>';
					$calllevel--;
				}
			}
			
			if ($this->set['DebugEnabled']) {
				$message = str_replace(array('%TRACKSTRING%', '%DEBUGSTATUS%'), array($btstring, 'Enabled'), $message);
			} else {
				$message = str_replace(array('%TRACKSTRING%', '%DEBUGSTATUS%'), array('No Information', 'Disabled. If you want to debug, you have to enable it by edit the inc.config.php.'), $message);
			}
			
			// Hook up the report function so we will able to get the bug
			if ($this->secobj) {
				if (!isset($this->pool['MainErrorQs'][0])) {
					register_shutdown_function(array(&$this, 'reportErrors'));
				}
				
				$this->pool['MainErrorQs'][] = array(
											'Error' => $erstring,
											'ErrorNo' => $err[0],
											'Debug Info' => $bts,
											'IP' => $this->secobj ? $this->secobj->getUserIP() : array(0, 0, 0, 0)
				);
			}
			
			if (!$this->isexiting) {
				$this->isexiting = true; // IsExiting is a running tag for anit another error exit call in this process. or, when exit has been call again, nothing will be report
				if ($exit) {
					if (!headers_sent()) {
						ob_clean();
						
						$this->secobj->header('Connection: close');
						
						if (!ob_start('ob_gzhandler')) {
							ob_start();
						}
						
						header('HTTP/1.1 500 Internal Server Error');
						header('Connection: close');
						header('Content-Length: '.strlen($message));
						
						echo($message);
						
						ob_end_flush();
						ob_flush();
						flush();
						
						exit();
					} else {
						exit();
					}
				} elseif ($this->uiobj) {
					return $this->uiobj->insertmessage('ERROR_CORE_APPERROR|'.$message);
				}
			}
		}
		
		return false;
	}

	private function convertMixToString($split, $array = array()) {
		$tmpstr = '';
		
		if (is_array($array)) {
			foreach($array AS $key => $val) {
				if (!empty($tmpstr)) {
					$tmpstr .= $split;
				}
				
				if (!isset($val)) {
					$tmpstr .= 'NA';
				} elseif (is_array($val)) {
					$tmpstr .= 'array';
				} elseif (is_object($val)) {
					$tmpstr .= 'object '.get_class($val);
				} elseif (is_string($val)) {
					$tmpstr .= 'string '.$val;
				} elseif (is_resource($val)) {
					$tmpstr .= 'resource '.get_resource_type($val).' '.$val;
				} elseif (is_bool($val)) {
					$tmpstr .= $val ? 'true' : 'false';
				} elseif (is_numeric($val)) {
					$tmpstr .= 'number '.$val;
				} else {
					$tmpstr .= 'unknown type';
				}
			}

			return $tmpstr;
		}
		
		return false;
	}
	
	public function reportErrors() {
		$debugString = '';
		$data = $http = array();
		
		if (isset($this->pool['MainErrorQs'][0]) && $this->set['DebugServer']) {
			if ($debugString = json_encode($this->pool['MainErrorQs'])) {
				ignore_user_abort(true);
				set_time_limit(5);
				
				$data = array(
					'KEY' => $this->set['DebugServerKey'],
					'APP' => 'Facula Framework',
					'VER' => __FACULAVERSION__,
					'ERRNO' => $this->pool['MainErrorQs'][0]['ErrorNo'],
					'DATA' => $debugString,
				);
				
				$http = array(
					'http' => array(
						'method' => 'POST',
						'header' => "Content-type: application/x-www-form-urlencoded\r\n".
									"User-Agent: Facula Framework Debug Reporter\r\n",
						'timeout'=> 5,
						'content' => http_build_query($data, '', '&'), 
					),
				);
				
				return file_get_contents($this->set['DebugServer'], false, stream_context_create($http));
			}
		}
		
		return false;
	}
}

?>
