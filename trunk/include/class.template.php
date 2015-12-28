<?php
/*****************************************************************************
	Facula Framework Template Engine

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

class ui {
	var $error = '';

	private $oopsobj = null;
	private $secobj = null;
	private $sessobj = null;

	private $pool = array();
	private $set = array();
	private $sandbox = array();

	public function __construct(&$s, &$siteinfo, &$urlinfo, &$env, &$oopsobj, &$secobj, &$sessobj) {
		global $_SERVER;

		if (is_array($s) && is_array($siteinfo) && ($urlinfo && is_array($urlinfo) || !$urlinfo) && is_array($env) && is_object($oopsobj) && is_object($secobj) && is_object($sessobj)) {
			if ($this->oopsobj = $oopsobj) {
				$this->oopsobj->setObjs('UI', $this);
			}
			$this->secobj = $secobj;
			$this->sessobj = $sessobj;

			$this->set = array('Time' => $this->secobj->time(), 'TrimTemplate' => $s['TrimTemplate'], 'ForceRenew' => $s['ForceRenew'], 'CompiledPath' => $s['CompiledPath'], 'TemplatePool' => $s['TemplatePool'], 'CachePool' => $s['CachePool'], 'CacheMessagePages' => $s['CacheMessagePages'], 'TplMaxSwitchItem' => $s['TplMaxSwitchItem']);

			$this->pool = $siteinfo + array('URL' => $urlinfo, 'UseGZIP' => (strpos(strtolower($_SERVER['HTTP_ACCEPT_ENCODING']), 'gzip') !== false ? true : false)) + $env;

			$s = null;

			$this->secobj->header('Server: '.($this->pool['SiteName'] ? $this->pool['SiteName'] : 'Facula Framework '.$_SERVER['host']).'');
			$this->secobj->header('X-Powered-By: Facula Framework ('.($this->pool['FaculaVersion'] ? $this->pool['FaculaVersion'] .' <'.__FACULAVERSION__.'>' : '__FACULAVERSION__').')');

			return true;
		} else {
			exit('A critical problem prevents initialization while trying to create '. __CLASS__.'.');
		}

		$s = null;
		return false;
	}

	public function oops($erstring, $exit = false) {
		$error_codes = array('ERROR_TEMPLATE_BUILDER_TPL_FILE_NOT_FOUND' => 'Specified Template file was not found.',
							'ERROR_TEMPLATE_CACHEDPAGE_SAVE_FAILED' => 'We cannot save cached page to specified dir.',
							'ERROR_TEMPLATE_COMPILE_SAVE_FAILED' => 'We cannot save compiled template file to specified dir.',
							'ERROR_TEMPLATE_VALUE_ALREADY_ASSIGNED' => 'You trying to push data to pool, but this pool slot already assigned.',
							'ERROR_TEMPLATE_TEMPLATE_NOT_FOUND' => 'The template file you specified was not found.',
							'ERROR_TEMPLATE_LANGFILE_NO_CONTENT' => 'The template language file you specified was empty or not found.',
							'ERROR_TEMPLATE_BUILDER_TPL_IS_EMPTY' => 'The template file you specified contains nothing.',
							'ERROR_TEMPLATE_BUILDER_TPL_NOT_DEFINE' => 'The template you want to be parsed was not specified.',
							'ERROR_TEMPLATE_BUILDER_TPL_INCLUDE_IN_A_LOOP' => 'The target template you requested trying to include this template.',
							'ERROR_TEMPLATE_BUILDER_LOOP_ALL_PARAMETERS_IS_INVALID' => 'You must fit all parameters with valid value in order to make Loop work.',
							'ERROR_TEMPLATE_BUILDER_IF_CONDITION_MUST_DEFINED' => 'You must define the condition so IF will knows how it work.',
							'ERROR_TEMPLATE_BUILDER_PAGER_INVALID_PARAM' => 'You must define valid params to make Pager work.',
							'ERROR_TEMPLATE_BUILDER_VALUE_NO_VAR_TO_DO' => 'There is no any value to needs to work with.',
							'ERROR_TEMPLATE_BUILDER_LANGSTRING_NOT_DEFINED' => 'Language string not defined.',
							'ERROR_TEMPLATE_BUILDER_LANG_TAG_MUST_DEFINED' => 'You must specified the language tag to load language string.',
							'ERROR_TEMPLATE_BUILDER_FORMATSTRING_NOT_FOUND' => 'You called a text format in tpl, but the format was not found in language set.');

		if ($this->oopsobj->pushmsg($error_codes) && $this->oopsobj->ouch($erstring, $exit)) {
			return true;
		}

		return false;
	}

	public function assign($name, $value) { // Add var to template
		if (!$this->sandbox[$name]) {
			if ($value)
				$this->sandbox[$name] = $value;
			else
				$this->sandbox[$name] = 0;
			return true;
		} else {
			$this->error = 'ERROR_ERROR_TEMPLATE_VALUE_ALREADY_ASSIGNED';
		}

		return false;
	}

	public function display_httpmessage($errorno, $errorheader) {
		$msgfile = 'http_'.$errorno;

		$this->secobj->header($errorheader);

		if (!$this->set['CacheMessagePages'] || !$this->cachedPage('messages', $msgfile, 0)) {
			return $this->display($msgfile);
		} else {
			return true;
		}
	}

	public function display_showmessage($errorno, $gotoaddr = '', $gotosec = 0, $errorheader = '') {
		$msgfile = 'message_'.$errorno;

		if ($gotoaddr) {
			$this->assign('GotoAddr', $gotoaddr);
			$this->assign('GoTimeout', $gotosec);
		}

		if ($errorheader) {
			$this->secobj->header($errorheader);
		}

		if (!$this->set['CacheMessagePages'] || !$this->cachedPage('messages', $msgfile, 0)) {
			return $this->display($msgfile);
		} else {
			return true;
		}
	}

	public function display($tplname, $justfeth = false, $type = '') {
		$template_string = $compiledstring = $tplfilepatch = '';
		$tplBuilder = $tplSandbox = $tplSandboxCachePreCompiler = null;

		if ($this->select($tplname)) {
			if ($this->set['TemplateRenewNeeded']) {
				if (!$this->error) {
					$tplBuilder = new ui_builder($this->set, $this->set['UsingCachedPage'], $this->secobj);
					// Build Template
					$template_string = $tplBuilder->build();
					// Gives all error to the main error value
					$this->error = $tplBuilder->error;
					// Release this instance
					$tplBuilder = null;
					// If we got any problem, report error to user and exit;
					if ($this->error) {
						$this->oops($this->error, true); // This will exit all code
						return false; // Force return DONT DELETE
					} else {
						unlink($this->set['CompiledTemplate']);

						if (file_put_contents($this->set['CompiledTemplate'], $template_string)) {
							$tplfilepatch = $this->set['CompiledTemplate'];
						} else {
							$this->oops('ERROR_TEMPLATE_COMPILE_SAVE_FAILED|'.$this->set['CompiledTemplate'], true);
							return false;
						}
					}
				} else {
					$this->oops($this->error, true);
				}
			} else {
				$tplfilepatch = $this->set['CompiledTemplate'];
			}

			if ($this->set['UsingCachedPage'] && $this->set['CachedPageNeedsRenew']) {
				$tplSandboxCachePreCompiler = new ui_sandbox($this->pool + $this->sandbox + array('Time' => $this->set['Time']));

				if ($tplSandboxCachePreCompiler->run($this->set['CompiledTemplate'], true, $compiledstring)) {
					$compiledstring = str_replace(array('<?php /****FACULABLOCKEDCODESTART***', '****FACULABLOCKEDCODEEND***/ ?>'), '', $compiledstring);
					$compiledstring = '<?php if (!isset($_TPL_IN_FACULA)) { header(\'HTTP/1.0 404 Not Found\'); exit(\'<html><body>WebShell Message: Wrong Way(404).</body></html>\'); }?>'.$compiledstring;

					if (!is_dir($this->set['CachedPageRootDir'])) {
						$this->secobj->makedir($this->set['CachedPageRootDir'], 0777);
					}

					unlink($this->set['CachedPage']);

					if (file_put_contents($this->set['CachedPage'], $compiledstring)) {
						$tplfilepatch = $this->set['CachedPage'];
					} else {
						$this->oops('ERROR_TEMPLATE_CACHEDPAGE_SAVE_FAILED|'.$this->set['CachedPage'], true);
						return false;
					}
				}

				$tplSandboxCachePreCompiler = null;
			}

			if ($tplfilepatch) {
				if (!$type) {
					$this->secobj->header('Content-type: text/html; charset=utf-8');
				} else {
					$this->secobj->header('Content-type: '.($type).'; charset=utf-8');
				}

				$tplSandbox = new ui_sandbox($this->pool + $this->sandbox + array('Time' => $this->set['Time'], 'HeaderQueue' => $this->secobj->getHeaders(), 'Session' => $this->sessobj->getSessionInfo(false)));

				if ($justfeth) {
					$tplSandbox->run($tplfilepatch, $justfeth, $compiledstring);
					$this->pool['ExecuteCount']++;
					$tplSandbox = null;
					return $compiledstring;
				} else {
					$tplSandbox->run($tplfilepatch);
					$this->pool['ExecuteCount']++;
					$tplSandbox = null;
					return true;
				}
			}
		} else {
			$this->oops($this->error, true);
		}

		return false;
	}

	public function clearCached($tpltypename, $cachedname) {
		$validCachedFilename = $validTplTypeName = $cachedFileName = $cachedRootName = $cachedPatchName = '';
		$cachedFileID = 0;

		list($cachedFileName, $cachedFileID) = explode('|', $cachedname, 2);

		if (($validTplTypeName = $this->secobj->isFilename($tpltypename)) && ($validCachedFilename = $this->secobj->isFilename($cachedFileName))) {
			if (!$this->set['LanguageSet']) {
				$this->getLocalLangFile();
			}

			if ($cachedFileID && ($cachedPatchName = $this->idToPatch($cachedFileID)) || ($cachedPatchName = $this->secobj->isFilename($cachedFileID))) {
				$cachedRootName = $this->set['CachePool'].$validTplTypeName.DIRECTORY_SEPARATOR.$cachedPatchName.DIRECTORY_SEPARATOR;
			} else {
				$cachedRootName = $this->set['CachePool'].$validTplTypeName.DIRECTORY_SEPARATOR;
			}

			return unlink($cachedRootName.'cached.'.$this->set['LanguageSet'].'.'.$validCachedFilename.'.tpl.php');
		}

		return false;
	}

	public function cachedPage($tpltypename, $cachedname, $expired = 0, $justfeth = false, $type = '') {
		$template_string = $compiledstring = $cachedFileName = $cachedPatchName = '';
		$tplSandbox = null;
		$pageexpiredtime = $cachedFileID = 0;

		if ($this->set['CachePool']) {
			list($cachedFileName, $cachedFileID) = explode('|', $cachedname, 2);
			if (($this->set['CachedTplTypeName'] = $this->secobj->isFilename($tpltypename)) && ($this->set['CachedFileName'] = $this->secobj->isFilename($cachedFileName))) {
				if (!$this->set['LanguageSet']) {
					$this->getLocalLangFile();
				}

				if ($cachedFileID) {
					if ($cachedPatchName = $this->idToPatch($this->secobj->isFilename($cachedFileID))) {
						$this->set['CachedPageRootDir'] = $this->set['CachePool'].$this->set['CachedTplTypeName'].DIRECTORY_SEPARATOR.$cachedPatchName.DIRECTORY_SEPARATOR;
					} else {
						$this->error = 'ERROR_TEMPLATE_CACHEPAGE_FILENAME|'.$cachedPatchName;
						$this->oops($this->error, true);
					}
				} else {
					$this->set['CachedPageRootDir'] = $this->set['CachePool'].$this->set['CachedTplTypeName'].DIRECTORY_SEPARATOR;
				}

				$this->set['CachedPage'] = $this->set['CachedPageRootDir'].'cached.'.$this->set['LanguageSet'].'.'.$this->set['CachedFileName'].'.tpl.php';

				if ($expired) {
					$pageexpiredtime = $this->set['Time'] - $expired;
				}

				if (filemtime($this->set['CachedPage']) > $pageexpiredtime) {
					$this->set['CachedPageNeedsRenew'] = false;

					if (!$type) {
						$this->secobj->header('Content-type: text/html; charset=utf-8');
					} else {
						$this->secobj->header('Content-type: '.($type).'; charset=utf-8');
					}

					$tplSandbox = new ui_sandbox($this->pool + $this->sandbox + array('Time' => $this->set['Time'], 'HeaderQueue' => $this->secobj->getHeaders(), 'Session' => $this->sessobj->getSessionInfo(false)));

					if ($justfeth) {
						if ($tplSandbox->run($this->set['CachedPage'], $justfeth, $compiledstring)) {
							$this->pool['ExecuteCount']++;
						}

						$tplSandbox = null;

						return $compiledstring;
					} else {
						if ($tplSandbox->run($this->set['CachedPage'])) {
							$this->pool['ExecuteCount']++;
						}

						$this->pool['ExecuteCount']++;

						$tplSandbox = null;

						return true;
					}
				}
			}
		}

		$this->set['CachedPageNeedsRenew'] = true;

		return false;
	}

	public function display_httpheader() {
		$tplSandbox = null;

		$tplSandbox = new ui_sandbox($this->pool + $this->sandbox + array('HeaderQueue' => $this->secobj->getHeaders(), 'Session' => $this->sessobj->getSessionInfo(false)));

		if ($tplSandbox->run(null)) {
			$this->pool['ExecuteCount']++;
			return true;
		}

		return false;
	}

	public function insertmessage($errcode) {
		$errorstring = $errortype = $errordetail = '';

		if ($errcode) {
			list($errorstring, $errordetail) = explode('|', $errcode);
			list($errortype) = explode('_', $errorstring, 2);

			if (isset($this->set['LangString']) || $this->getLangString()) {
				if ($this->set['LangString']['MESSAGE_'.$errorstring]) {
					if ($errordetail) {
						$errorstring = sprintf($this->set['LangString']['MESSAGE_'.$errorstring], $this->secobj->filterTextToWebPage($errordetail));
					} else {
						$errorstring = $this->set['LangString']['MESSAGE_'.$errorstring];
					}
				}

				$this->pool['ErrorMessages'][] = array('Type' => $errortype, 'Message' => $errorstring);

				return $errorstring;
			}
		}

		return false;
	}

	private function idToPatch($id) {
		$validId = '';
		$validIDs = array();
		$last = 0;

		if ($validId = abs(intval($id))) {
			$last = $validId;
			$validIDs[] = $last;
			for ($i = 0; $i < 3; $i++) {
				$validIDs[] = $last = intval($last / 65535);
			}
			return implode(DIRECTORY_SEPARATOR, array_reverse($validIDs));
		}

		return false;
	}

	private function select($templatename) { // Select a template
		if ($this->set['SelectedTemplateName'] = $this->secobj->isFilename($templatename)) {
			if (file_exists($this->set['TemplatePool'].'template.'.$this->set['SelectedTemplateName'].'.tpl.html')) {
				$this->set['SelectedTemplate'] = $this->set['TemplatePool'].'template.'.$this->set['SelectedTemplateName'].'.tpl.html';
				$this->set['ParentTemplate'] = $this->set['SelectedTemplateName'];

				if (!$this->set['LanguageSet']) $this->getLocalLangFile();

				if ($this->set['CachedFileName'] == $this->set['SelectedTemplateName']) {
					$this->set['UsingCachedPage'] = true;
					$this->set['CompiledTemplate'] = $this->set['CompiledPath'].'cacheable.compiled.'.$this->set['LanguageSet'].'.'.$this->set['SelectedTemplateName'].'.tpl.php';
				} else {
					$this->set['CompiledTemplate'] = $this->set['CompiledPath'].'compiled.'.$this->set['LanguageSet'].'.'.$this->set['SelectedTemplateName'].'.tpl.php';
				}

				if ($this->set['ForceRenew'] || filemtime($this->set['CompiledTemplate']) < filemtime($this->set['SelectedTemplate'])) {
					$this->getLangString();
					$this->set['TemplateRenewNeeded'] = true;
				} else {
					$this->set['TemplateRenewNeeded'] = false;
				}

				return true;
			} else {
				$this->error = 'ERROR_TEMPLATE_TEMPLATE_NOT_FOUND|'.$this->set['SelectedTemplateName'];
				$this->oops($this->error, true);
			}
		}

		return false;
	}

	private function getLang() {
		global $_SERVER; // We use server env var, get it for safe.
		$filecontent = '';
		$file = null;
		$langarray = $tmp = array();

		$allowedLanguages = array('af' => 'Afrikaans', 'sq' => 'Albanian', 'ar-dz' => 'Arabic (Algeria)', 'ar-bh' => 'Arabic (Bahrain)',
									'ar-eg' => 'Arabic (Egypt)', 'ar-iq' => 'Arabic (Iraq)', 'ar-jo' => 'Arabic (Jordan)',
									'ar-kw' => 'Arabic (Kuwait)', 'ar-lb' => 'Arabic (Lebanon)', 'ar-ly' => 'Arabic (libya)',
									'ar-ma' => 'Arabic (Morocco)', 'ar-om' => 'Arabic (Oman)', 'ar-qa' => 'Arabic (Qatar)',
									'ar-sa' => 'Arabic (Saudi Arabia)', 'ar-sy' => 'Arabic (Syria)', 'ar-tn' => 'Arabic (Tunisia)',
									'ar-ae' => 'Arabic (U.A.E.)', 'ar-ye' => 'Arabic (Yemen)', 'ar' => 'Arabic',
									'hy' => 'Armenian', 'as' => 'Assamese', 'az' => 'Azeri', 'eu' => 'Basque',
									'be' => 'Belarusian', 'bn' => 'Bengali',
									'bg' => 'Bulgarian', 'ca' => 'Catalan', 'zh-cn' => 'Chinese (China)',
									'zh-hk' => 'Chinese (Hong Kong SAR)', 'zh-mo' => 'Chinese (Macau SAR)', 'zh-sg' => 'Chinese (Singapore)',
									'zh-tw' => 'Chinese (Taiwan)', 'zh' => 'Chinese', 'hr' => 'Croatian',
									'cs' => 'Czech', 'da' => 'Danish', 'div' => 'Divehi',
									'nl-be' => 'Dutch (Belgium)', 'nl' => 'Dutch (Netherlands)', 'en-au' => 'English (Australia)',
									'en-bz' => 'English (Belize)', 'en-ca' => 'English (Canada)', 'en-ie' => 'English (Ireland)',
									'en-jm' => 'English (Jamaica)', 'en-nz' => 'English (New Zealand)',
									'en-ph' => 'English (Philippines)', 'en-za' => 'English (South Africa)',
									'en-tt' => 'English (Trinidad)', 'en-gb' => 'English (United Kingdom)',
									'en-us' => 'English (United States)', 'en-zw' => 'English (Zimbabwe)',
									'en' => 'English', 'us' => 'English (United States)',
									'et' => 'Estonian', 'fo' => 'Faeroese', 'fa' => 'Farsi', 'fi' => 'Finnish',
									'fr-be' => 'French (Belgium)', 'fr-ca' => 'French (Canada)', 'fr-lu' => 'French (Luxembourg)',
									'fr-mc' => 'French (Monaco)', 'fr-ch' => 'French (Switzerland)',
									'fr' => 'French (France)', 'mk' => 'FYRO Macedonian', 'gd' => 'Gaelic',
									'ka' => 'Georgian', 'de-at' => 'German (Austria)', 'de-li' => 'German (Liechtenstein)',
									'de-lu' => 'German (Luxembourg)', 'de-ch' => 'German (Switzerland)', 'de' => 'German (Germany)',
									'el' => 'Greek', 'gu' => 'Gujarati', 'he' => 'Hebrew', 'hi' => 'Hindi',
									'hu' => 'Hungarian', 'is' => 'Icelandic', 'id' => 'Indonesian',
									'it-ch' => 'Italian (Switzerland)', 'it' => 'Italian (Italy)', 'ja' => 'Japanese',
									'kn' => 'Kannada', 'kk' => 'Kazakh', 'kok' => 'Konkani', 'ko' => 'Korean',
									'kz' => 'Kyrgyz', 'lv' => 'Latvian', 'lt' => 'Lithuanian',
									'ms' => 'Malay', 'ml' => 'Malayalam', 'mt' => 'Maltese', 'mr' => 'Marathi',
									'mn' => 'Mongolian (Cyrillic)', 'ne' => 'Nepali (India)', 'nb-no' => 'Norwegian (Bokmal)',
									'nn-no' => 'Norwegian (Nynorsk)', 'no' => 'Norwegian (Bokmal)', 'or' => 'Oriya',
									'pl' => 'Polish', 'pt-br' => 'Portuguese (Brazil)', 'pt' => 'Portuguese (Portugal)',
									'pa' => 'Punjabi', 'rm' => 'Rhaeto-Romanic', 'ro-md' => 'Romanian (Moldova)',
									'ro' => 'Romanian', 'ru-md' => 'Russian (Moldova)', 'ru' => 'Russian', 'sa' => 'Sanskrit',
									'sr' => 'Serbian', 'sk' => 'Slovak', 'ls' => 'Slovenian', 'sb' => 'Sorbian',
									'es-ar' => 'Spanish (Argentina)', 'es-bo' => 'Spanish (Bolivia)',
									'es-cl' => 'Spanish (Chile)', 'es-co' => 'Spanish (Colombia)',
									'es-cr' => 'Spanish (Costa Rica)', 'es-do' => 'Spanish (Dominican Republic)',
									'es-ec' => 'Spanish (Ecuador)', 'es-sv' => 'Spanish (El Salvador)',
									'es-gt' => 'Spanish (Guatemala)', 'es-hn' => 'Spanish (Honduras)',
									'es-mx' => 'Spanish (Mexico)', 'es-ni' => 'Spanish (Nicaragua)',
									'es-pa' => 'Spanish (Panama)', 'es-py' => 'Spanish (Paraguay)',
									'es-pe' => 'Spanish (Peru)', 'es-pr' => 'Spanish (Puerto Rico)',
									'es-us' => 'Spanish (United States)', 'es-uy' => 'Spanish (Uruguay)',
									'es-ve' => 'Spanish (Venezuela)', 'es' => 'Spanish (Traditional Sort)',
									'sx' => 'Sutu', 'sw' => 'Swahili', 'sv-fi' => 'Swedish (Finland)',
									'sv' => 'Swedish', 'syr' => 'Syriac', 'ta' => 'Tamil', 'tt' => 'Tatar',
									'te' => 'Telugu','th' => 'Thai', 'ts' => 'Tsonga', 'tn' => 'Tswana',
									'tr' => 'Turkish', 'uk' => 'Ukrainian', 'ur' => 'Urdu', 'uz' => 'Uzbek',
									'vi' => 'Vietnamese','xh' => 'Xhosa', 'yi' => 'Yiddish', 'zu' => 'Zulu');

		if (!$this->set['ClientLanguageSet'] || !$this->set['ClientLanguageName']) {
			if ($tmp = explode(',', str_replace(' ', '', strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 16))), 2)) {
				if (isset($allowedLanguages[$tmp[0]])) {
					$this->set['ClientLanguageSet'] = $tmp[0];
					$this->set['ClientLanguageName'] = $allowedLanguages[$tmp[0]];
				} else {
					$this->set['ClientLanguageSet'] = 'defalut';
					$this->set['ClientLanguageName'] = 'Defalut Language Set';
				}

				return $this->set['ClientLanguageSet'];
			}
		} else {
			return $this->set['ClientLanguageSet'];
		}

		return false;
	}

	private function getLocalLangFile() {
		if (!$this->set['ClientLanguageSet'] || !$this->set['ClientLanguageName']) {
			$this->getLang();
		}

		if (file_exists($this->set['TemplatePool'].'language.'.$this->set['ClientLanguageSet'].'.txt')) {
			$this->set['LanguageFile'] = $this->set['TemplatePool'].'language.'.$this->set['ClientLanguageSet'].'.txt';
			$this->set['LanguageFilename'] = 'language.'.$this->set['ClientLanguageSet'].'.txt';
			$this->set['LanguageSet'] = $this->set['ClientLanguageSet'];
		} else {
			$this->set['LanguageFile'] = $this->set['TemplatePool'].'language.default.txt';
			$this->set['LanguageFilename'] = 'language.default.txt';
			$this->set['LanguageSet'] = 'default';
		}

		return true;
	}

	private function getLangString() {
		$filecontent = '';
		$filecontentarray = $filetmparray = array();

		if ($this->set['LangString']) return true; // It's already done, so to back then continue.
		if (!$this->set['LanguageFile']) $this->getLocalLangFile();

		$this->set['CompiledLangFile'] = $this->set['CompiledPath'].DIRECTORY_SEPARATOR.'compiled.'.$this->set['LanguageFilename'];

		if (filemtime($this->set['CompiledLangFile']) <= filemtime($this->set['LanguageFile'])) {
			if ($filecontent = file_get_contents($this->set['LanguageFile'])) {
				$filecontentarray = explode("\r", $filecontent);

				foreach($filecontentarray as $arrayitem) {
					$filetmparray = explode('=', $arrayitem, 2);
					if ($filetmparray[1]) {
						$langarray[trim($filetmparray[0])] = trim($filetmparray[1]);
					}
				}

				unlink($this->set['CompiledLangFile']);

				file_put_contents($this->set['CompiledLangFile'], serialize($langarray));

				unset($filecontent, $filetmparray, $filecontentarray);

				$this->set['LangString'] = $langarray;

				return true;
			} else {
				$this->error = 'ERROR_TEMPLATE_LANGFILE_NO_CONTENT|'.$this->set['LanguageFilename'];
				$this->oops($this->error, true);
			}
		} else {
			$langarray = unserialize(file_get_contents($this->set['CompiledLangFile']));

			$this->set['LangString'] = $langarray;
			return true;
		}

		return false;
	}
}

class ui_builder {
	var $error = '';

	private $s = NULL;

	private $set = array();
	private $pool = array(
						'nocache' => array(
							'enabledpreg' => array('Tag' => 'nocache', 'Searchs' => '/\{_TAG\}(.*){\/_TAG\}/seU', 'Replaces' => "\$this->do_nocached('\\1')"),
							'disabledpreg' => array('Tag' => 'nocache', 'Searchs' => '/\{_TAG\}(.*){\/_TAG\}/sU', 'Replaces' => '\\1'),
								),
						);

	private $tags = array(
						array('Tag' => 'template', 'Searchs' => '/\{_TAG (.*)\}/eU', 'Replaces' => "\$this->do_include('\\1')"),
						array('Tag' => 'lang', 'Searchs' => '/\{_TAG (.*)\}/eU', 'Replaces' => "\$this->do_lang('\\1')"),
						array('Tag' => ':', 'Searchs' => '/\{\_TAG(.*)\_TAG\}/eU', 'Replaces' => "\$this->do_value('\\1')"),
						array('Tag' => 'if', 'Searchs' => '/\{_TAG (.*)\}(.*)\{\/_TAG\}/seU', 'Replaces' => "\$this->do_if('\\1', '\\2')"),
						array('Tag' => 'lo', 'Searchs' => '/\{_TAG (.*) (.*)\}(.*)\{\/_TAG\}/seU', 'Replaces' => "\$this->do_loop('\\1', '\\2', '\\3')"),
						array('Tag' => 'case', 'Searchs' => '/\{_TAG (.*)\}(.*)\{\/_TAG\}/seU', 'Replaces' => "\$this->do_case('\\1', '\\2')"),
						array('Tag' => 'pager', 'Searchs' => '/\{_TAG (.*) (.*) (.*) (.*) (.*) (.*)\}/eU', 'Replaces' => "\$this->do_pager('\\1', '\\2', '\\3', '\\4', '\\5', '\\6')"),
					);

	public function __construct(&$setting, &$cached, &$secobj) {
		$this->set = $setting;
		$this->pool['UsedTpl'][] = $this->set['ParentTemplate'];
		$this->s = $secobj;

		if ($cached) {
			$this->tags = array_merge(array($this->pool['nocache']['enabledpreg']), $this->tags);
		} else {
			$this->tags[] = $this->pool['nocache']['disabledpreg'];
		}

		return true;
	}

	public function build() {
		foreach($this->tags AS $key => $val) {
			$this->pool['Tags']['Searchs'][] = str_replace('_TAG', $val['Tag'], $val['Searchs'], $this->pool['Tags']['TagsData'][$val['Tag']]['Count']);
			$this->pool['Tags']['Replaces'][] = $val['Replaces'];
			$this->pool['Tags']['Tags'][] = $val['Tag'];
			$this->pool['Tags']['TagsData'][$val['Tag']] = array('Tag' => $val['Tag'],
																	'Coupled' => $this->pool['Tags']['TagsData'][$val['Tag']]['Count']%2 ? 1 : 0,
																	'Using' => array());
			$this->pool['Tags']['SearchTags'][] = '{'.$val['Tag'];
		}

		return $this->parse();
	}

	private function parse($tplstring = '') {
		$templatecontent = '';
		$tags = array();

		if ($tplstring || $this->set['SelectedTemplate']) {
			if ($tplstring) {
				$templatecontent = $tplstring;
			} else {
				if ($templatecontent = file_get_contents($this->set['SelectedTemplate'])) {
					$templatecontent = '<?php if (!isset($_TPL_IN_FACULA)) { header(\'HTTP/1.0 404 Not Found\'); exit(\'<html><body>WebShell Message: Wrong Way(404).</body></html>\'); }?>'.trim($templatecontent);
				}
			}

			if ($templatecontent) {
				$templatecontent = preg_replace($this->pool['Tags']['Searchs'], $this->pool['Tags']['Replaces'], $templatecontent);
				while($this->matchdelimiter($templatecontent, $this->tags)) {
					$templatecontent = preg_replace($this->pool['Tags']['Searchs'], $this->pool['Tags']['Replaces'], $templatecontent);
				}

				return $this->set['TrimTemplate'] ? str_replace(array("\r", "\n", "\t", '{', '}', '  '), array('', '', '', '{ ', ' }', ' '), $templatecontent) : $templatecontent;
			} else {
				$this->error = 'ERROR_TEMPLATE_BUILDER_TPL_IS_EMPTY';
			}
		} else {
			$this->error = 'ERROR_TEMPLATE_BUILDER_TPL_NOT_DEFINE';
		}

		return false;
	}

	public function matchdelimiter(&$content, &$tags = array()) {
		foreach($tags AS $key => $val) {
			if (preg_match('/\{'.$val['Tag'].'.*\}.*\{\/'.$val['Tag'].'\}/isU', $content)) {
				return true;
			}
		}

		return false;
	}

	private function formatIt($valuedata) {
		$valuename = $value = $format = $param = $tag = '';
		$phpcode = '';

		list($valuename, $value) = explode('|', $valuedata);

		if ($value) {
			list($tag, $format) = explode(':', $value);

			if (!$valuename) $valuename = '0';

			if ($tag && $format) {
				if ($this->set['LangString']['FORMAT_'.$format] || intval($format)) {
					switch($tag) {
						case 'time':
							$phpcode = "<?php echo(gmdate(\"".$this->s->filterTextToWebPage($this->set['LangString']['FORMAT_'.$format])."\", ".$valuename." + \$Session['TimeOffset'])); ?>";
							break;

						case 'maxnumber':
							$phpcode = '<?php echo(('.$valuename.' > '.$format.') ? \''.$format.'+\' : '.$valuename.'); ?>';
							break;

						default:
							$phpcode = "<?php printf(\"{$tag}\", {$valuename}); ?>";
							break;
					}
				} else {
					$this->error = 'ERROR_TEMPLATE_BUILDER_FORMATSTRING_NOT_FOUND|'.$format;
				}
			} else {
				list($tag, $param) = explode(',', $tag);

				switch($tag) {
					case 'friendlytime':
						$phpcode = "<?php \$temptime = \$Time - (".$valuename."); if (\$temptime < 60) { printf(\"".$this->s->filterTextToWebPage($this->set['LangString']['FORMAT_TIME_SNDBEFORE'])."\", \$temptime); } elseif (\$temptime < 3600) { printf(\"".$this->s->filterTextToWebPage($this->set['LangString']['FORMAT_TIME_MINBEFORE'])."\", intval(\$temptime / 60)); } elseif (\$temptime < 86400) { printf(\"".$this->s->filterTextToWebPage($this->set['LangString']['FORMAT_TIME_HRBEFORE'])."\", intval(\$temptime / 3600)); } elseif (\$temptime < 604800) { printf(\"".$this->s->filterTextToWebPage($this->set['LangString']['FORMAT_TIME_DAYBEFORE'])."\", intval(\$temptime / 86400)); } elseif (\$temptime) { echo(gmdate(\"".$this->s->filterTextToWebPage($this->set['LangString']['FORMAT_TIME_MOREBEFORE'])."\", ".$valuename." + \$Session['TimeOffset'])); } \$temptime = 0; ?>";
						break;

					case 'bytes':
						$phpcode = '<?php $tempsize = '.$valuename.'; if ($tempsize < 1024) { echo (($tempsize).\''.($this->s->filterTextToWebPage($this->set['LangString']['FORMAT_FILESIZE_BYTES'])).'\'); } elseif ($tempsize < 1048576) { echo (intval($tempsize / 1024).\''.($this->s->filterTextToWebPage($this->set['LangString']['FORMAT_FILESIZE_KILOBYTES'])).'\'); } elseif ($tempsize < 1073741824) { echo (round($tempsize / 1048576, 1).\''.($this->s->filterTextToWebPage($this->set['LangString']['FORMAT_FILESIZE_MEGABYTES'])).'\'); } elseif ($tempsize < 1099511627776) { echo (round($tempsize / 1073741824, 2).\''.($this->s->filterTextToWebPage($this->set['LangString']['FORMAT_FILESIZE_GIGABYTES'])).'\'); } elseif ($tempsize < 1125899906842624) { echo (round($tempsize / 1099511627776, 3).\''.($this->s->filterTextToWebPage($this->set['LangString']['FORMAT_FILESIZE_TRILLIONBYTES'])).'\'); } $tempsize = 0; ?>';
						break;

					case 'json':
						$phpcode = '<?php echo(json_encode('.$valuename.', JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG)) ?>';
						break;

					case 'jsonData':
						$phpcode = '<?php echo(htmlspecialchars(json_encode('.$valuename.'))) ?>';
						break;

					case 'urlchar':
						$phpcode = '<?php echo(urlencode('.$valuename.')) ?>';
						break;

					case 'slashed':
						$phpcode = '<?php echo(addslashes('.$valuename.')) ?>';
						break;

					case 'html':
						$phpcode = '<?php echo(htmlspecialchars('.$valuename.', ENT_QUOTES)) ?>';
						break;

					default:
						$phpcode = '<?php printf("'.$tag.'", "'.$this->s->filterTextToWebPage($valuename).'"); ?>';
						break;
				}
			}
		} else {
			$phpcode = '<?php echo('.$valuename.'); ?>';
		}

		return $phpcode;
	}

	private function do_nocached($content) {
		$php = '';
		$preg_search = str_replace('_TAG', $this->pool['nocache']['disabledpreg']['Tag'], $this->pool['nocache']['disabledpreg']['Searchs']);

		if ($content) {
			if (!preg_match($preg_search, $content)) {
				$content = $this->parse($content);
				$content = str_replace('\"', '"', $this->s->addSlashes($this->s->delSlashes($content)));
				return '<?php echo(\'<?php /****FACULABLOCKEDCODESTART***'.$content.'****FACULABLOCKEDCODEEND***/ ?>\'); ?>';
			}
		}

		return $this->s->delSlashes($content);
	}

	private function do_include($param) {
		$parloop = $tplreplace = $params = array();
		$filecontent = $file = $tpl = $paramstr = '';

		$preg_patterns		= array('/\{le (.*)\}/sU');
		$preg_replacements	= array("<?php echo(\${$n}['\\1']); ?>");

		if ($param) {
			$params = explode(' ', $param);
			$tpl = $params[0];
			if ($params[1]) { // If we got other params, that's means we got some setting here
				unset($params[0]);
				$params = array(); // Clear this array, we already given the tpl name to $tpl above
				$params = explode(',', str_replace($tpl, '', $param));

				foreach($params as $paramitem) {
					$parloop = explode('=', $paramitem);
					$parloop[0] = trim($parloop[0]);
					$parloop[1] = trim($parloop[1]);

					switch($parloop[0][0]) {
						case '$':
							$tplreplace['searchs'][] = $parloop[0];
							$tplreplace['replaces'][] = $parloop[1];

							break;

						default:
							$tplreplace['searchs'][] = '{'.$parloop[0].'}';
							$tplreplace['replaces'][] = '{'.$parloop[1].'}';

							break;
					}
				}
			} else {
				unset($params); // Nothing in $params[1] means we dont have more param so unset it, we will not use this below
			}

			if (!in_array($tpl, $this->pool['UsedTpl'])) {
				$this->pool['UsedTpl'][] = $tpl;
				$file = $this->set['TemplatePool'].'template.'.$tpl.'.tpl.html';
				if ($filecontent = file_get_contents($file)) {
					if (isset($params)) {
						$filecontent = str_replace($tplreplace['searchs'], $tplreplace['replaces'], $filecontent);
					}
					return $this->parse($filecontent);
				} else {
					$this->error = 'ERROR_TEMPLATE_BUILDER_TPL_FILE_NOT_FOUND|'.$tpl;
				}
			} else {
				$this->error = 'ERROR_TEMPLATE_BUILDER_TPL_INCLUDE_IN_A_LOOP|'.$tpl;
			}
		} else {
				$this->error = 'ERROR_TEMPLATE_BUILDER_TPL_INCLUDE_PARAMS_MUST_DEFINED';
		}

		return false;
	}

	private function do_loop($n, $v, $f) {
		$subloops = $sublooptags = $do = array();

		$phpcode = $complied	= '';


		$preg_patterns			= array('/\{le ([\$a-zA-Z0-9\_]+)\|(.*)\}/eU', '/\{le (.*)\}/sU');
		$preg_replacements		= array("\$this->formatIt('\${$n}[\'\\1\']\|\\2')", "<?php echo(\${$n}\['\\1'\]); ?>");
		$tags					= array(
										array('Tag' => 'slo', 'Searchs' => '/\{slo _TARGET_ (.*)\}(.*)\{\/slo:_TARGET_\}/seU', 'Replaces' => "\$this->do_sub_loop('_TARGET_', '\\1', '\\2')"),
										);



		if ($n && $v && $f && "\${$n}" != $v) {
			$do = explode('{loelse}', $f);
			$phpcode .= "<?php if (is_array({$v})): ?>";
			$phpcode .= "<?php foreach ({$v} AS \$key_{$n} => \${$n}): ?>";

			$complied = $do[0];

			$complied = preg_replace($preg_patterns, $preg_replacements, $do[0]);

			if (preg_match_all('/\{\/slo:(.*)\}/sU', $complied, $subloops)) {
				if ($subloops[0]) {
					foreach($subloops[1] AS $key => $val) {
						$sublooptags['Searchs'][] = str_replace('_TARGET_', $val, $tags[0]['Searchs']);
						$sublooptags['Replace'][] = str_replace('_TARGET_', $val, $tags[0]['Replaces']);
					}
					$complied = preg_replace($sublooptags['Searchs'], $sublooptags['Replace'], $complied);
				}
			}

			$phpcode .= $complied;
			$phpcode .= "<?php endforeach; unset(\${$n});?>";
			if ($do[1]) $phpcode .= "<?php else: ?>{$do[1]}";
			$phpcode .= '<?php endif;?>';

			return $this->s->delSlashes($phpcode);
		} else {
			$this->error = 'ERROR_TEMPLATE_BUILDER_LOOP_ALL_PARAMETERS_IS_INVALID';
		}

		return false;
	}

	private function do_sub_loop($n, $v, $f) {
		$do = array();

		$phpcode			= '';

		$preg_patterns		= array('/\{sle\:'.$n.' ([\$a-zA-Z0-9\_]+)\|(.*)\}/eU', '/\{sle\:'.$n.' (.*)\}/U');
		$preg_replacements	= array("\$this->formatIt('\${$n}[\'\\1\']\|\\2')", "<?php echo(\${$n}\['\\1'\]); ?>");

		if ($n && $v && $f && "\${$n}" != $v) {
			$do = explode('{sloelse:'.$n.'}', $f);
			$phpcode .= "<?php if (is_array({$v})): ?>";
			$phpcode .= "<?php foreach ({$v} AS \$key_{$n} => \${$n}): ?>";
			$phpcode .= preg_replace($preg_patterns, $preg_replacements, $do[0]);
			$phpcode .= "<?php endforeach; unset(\${$n});?>";
			if ($do[1]) $phpcode .= "<?php else: ?>{$do[1]}";
			$phpcode .= '<?php endif;?>';

			return $this->s->delSlashes($phpcode);
		} else {
			$this->error = 'ERROR_TEMPLATE_BUILDER_LOOP_ALL_PARAMETERS_IS_INVALID';
		}

		return false;
	}

	private function do_case($r, $v) {
		$phpcode = '';
		$preg_patterns		= array('/\{ce (.*)\}(.*)\{\/ce\}/sU', '/\{ce\}(.*)\{\/ce\}/sU');
		$preg_replacements	= array("case '\\1': ?>\\2<?php break;", 'default: ?>\\1<?php break;');

		if ($r && $v) {
			$phpcode .= "<?php switch({$r}):";
			$phpcode .= preg_replace($preg_patterns, $preg_replacements, $v);
			$phpcode .= 'endswitch; ?>';

			return $this->s->delSlashes($phpcode);
		} else {
			$this->error = 'ERROR_TEMPLATE_BUILDER_CASE_ALL_PARAMETERS_IS_INVALID';
		}

		return false;
	}

	private function do_if($r, $v) {
		$phpcode = '';
		$preg_patterns		= array("/\{elseif (.*)\}/sU",
									"/\{else\}/sU");
		$preg_replacements	= array("<?php elseif (\\1):?>",
									"<?php else:?>");

		if ($r) {
			$phpcode .= "<?php if ({$r}):?>";
			$phpcode .= preg_replace($preg_patterns, $preg_replacements, str_replace('\'', '\\\'', $v));
			$phpcode .= '<?php endif;?>';

			return $this->s->delSlashes($phpcode);
		} else {
			$this->error = 'ERROR_TEMPLATE_BUILDER_IF_CONDITION_MUST_DEFINED';
		}

		return false;
	}

	private function do_pager($name, $styleclass, $c, $t, $_s, $_e = '') {
		$phpcode = '';
		$maxpage = $this->set['TplMaxSwitchItem'] > 0 && $this->set['TplMaxSwitchItem'] < 30 ? $this->set['TplMaxSwitchItem'] : 15;

		if ($styleclass && $this->s->isURLCHARS($_s) && $this->s->isURLCHARS($_e) || !$_e) {
			$_s = $this->s->addSlashes($_s);
			$_e = $this->s->addSlashes($_e);

			if ($totalpage > $maxpage) {

			} else {
				$phpcode .= "<?php if ({$t} > 1) { echo(\"<ul id=\\\\\"{$name}\\\\\" class=\\\\\"{$styleclass}\\\\\">\"); if ({$t} > 0 && {$c} <= {$t}) { if ({$c} > 1) echo(\"<li><a href=\\\\\"{$_s}1{$_e}\\\\\">&laquo;</a></li><li><a href=\\\\\"{$_s}\".({$c} - 1).\"{$_e}\\\\\">&lsaquo;</a></li>\"); \$loop = intval({$maxpage} / 2); if ({$c} - \$loop > 0) { for (\$i = {$c} - \$loop; \$i <= {$t} && \$i <= {$c} + \$loop; \$i++) { if (\$i == {$c}) { echo(\"<li class=\\\\\"this\\\\\"><a href=\\\\\"{$_s}{\$i}{$_e}\\\\\">{\$i}</a></li>\"); } else { echo(\"<li><a href=\\\\\"{$_s}{\$i}{$_e}\\\\\">{\$i}</a></li>\"); }}} else { for (\$i = 1; \$i <= {$t} && \$i <= {$maxpage}; \$i++) { if (\$i == {$c}) { echo(\"<li class=\\\\\"this\\\\\"><a href=\\\\\"{$_s}{\$i}{$_e}\\\\\">{\$i}</a></li>\"); } else { echo(\"<li><a href=\\\\\"{$_s}{\$i}{$_e}\\\\\">{\$i}</a></li>\"); }}} unset(\$loop); if ({$t} > {$c}) echo(\"<li><a href=\\\\\"{$_s}\".({$c} + 1).\"{$_e}\\\\\">&rsaquo;</a></li><li><a href=\\\\\"{$_s}\{{$t}\}{$_e}\\\\\">&raquo;</a></li>\");} echo(\"</ul>\");} ?>";
			}

			return $this->s->delSlashes($phpcode);
		} else {
			$this->error = 'ERROR_TEMPLATE_BUILDER_PAGER_INVALID_PARAM';
		}

		return false;
	}

	private function do_value($n) {
		if ($n) {
			return $this->formatIt($n);
		} else {
			$this->error = 'ERROR_TEMPLATE_BUILDER_VALUE_NO_VAR_TO_DO';
		}

		return false;
	}

	private function do_lang($s) {
		$langtag = $langval = $phpcode = '';
		$langvals = $langarray = array();

		$langarray = explode('|', $s);

		$langtag = trim($langarray[0]);
		$langval = trim($langarray[1]);

		if ($langtag) {
			if ($langstring = $this->set['LangString'][$langtag]) {
				return $this->s->delSlashes($langstring);
			} else {
				$this->error = 'ERROR_TEMPLATE_BUILDER_LANGSTRING_NOT_DEFINED|'.$langtag;
			}
		} else {
			$this->error = 'ERROR_TEMPLATE_BUILDER_LANG_TAG_MUST_DEFINED';
		}

		return false;
	}
}

class ui_sandbox {
	private $pool = array();
	private $mappedvalues = array(
							'True' => true, 'False' => false, 'A' => 'A',
							'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E', 'F' => 'F',
							'G' => 'G', 'H' => 'H', 'I' => 'I', 'J' => 'J', 'K' => 'K',
							'L' => 'L', 'M' => 'M', 'N' => 'N', 'O' => 'O', 'P' => 'P',
							'Q' => 'Q', 'I' => 'I', 'S' => 'S', 'T' => 'T', 'U' => 'U',
							'V' => 'V', 'W' => 'W', 'X' => 'X', 'Y' => 'Y', 'Z' => 'Z',
							'a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd', 'e' => 'e',
							'f' => 'f', 'g' => 'g', 'h' => 'h', 'i' => 'i', 'j' => 'j',
							'k' => 'k', 'l' => 'l', 'm' => 'm', 'n' => 'n', 'o' => 'o',
							'p' => 'p', 'q' => 'q', 'i' => 'i', 's' => 's', 't' => 't',
							'u' => 'u', 'v' => 'v', 'w' => 'w', 'x' => 'x', 'y' => 'y',
							'z' => 'z', '0' => '0', '1' => '1', '2' => '2', '3' => '3',
							'4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8',
							'9' => '9'
	);

	public function __construct($v = array()) {
		$this->pool = $v;

		return true;
	}

	private function headerQueue() {
		if (!headers_sent()) {
			foreach ($this->pool['HeaderQueue'] AS $key => $val) {
				header($val);
			}

			$this->pool['HeaderQueue'] = array();
		}

		return true;
	}

	public function run($tplpath, $justfeth = false, &$rback = '') {
		$buffer = $output = '';
		$_TPL_IN_FACULA = true;
		$buffersize = 0;

		ob_start();

		extract($this->pool);
		extract($this->mappedvalues);

		if ($tplpath) {
			include($tplpath);
		}

		$buffer = ob_get_contents();
		ob_end_clean();

		// Clear the ob again to disable the all possible buffer


		if ($justfeth) {
			$rback = $buffer;
		} else {
			$buffer .= ob_get_clean();

			if ($this->pool['UseGZIP'] && function_exists('gzcompress')) {
				$output = "\x1f\x8b\x08\x00\x00\x00\x00\x00".substr(gzcompress($buffer, 2), 0, -4);;
				$this->pool['HeaderQueue'][] = 'Content-Encoding: gzip';
			} else {
				$output = $buffer;
			}

			if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
				$this->pool['HeaderQueue'][] = 'X-Runtime: ' . (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
			}

			$this->pool['HeaderQueue'][] = 'Content-Length: '.strlen($output);

			ob_start();

			$this->headerQueue();

			echo $output;

			ob_end_flush();
			flush();
		}

		return true;
	}
}

?>