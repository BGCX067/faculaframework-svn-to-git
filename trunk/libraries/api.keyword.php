<?php 
/*****************************************************************************
	Facula Framework Keyword API
	
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

class keyword {
	private $secobj = null;
	private $set = array();
	public $swap = array();
	
	public function __construct() {
		global $sec;
		
		if ($this->secobj = $sec) {
			$this->swap = array('Required' => array('SiteSetting' => array(
																			'keyword' => array(),
																			)
													)
			);
			
			return true;
		}
		
		return false;
	}
	
	public function _init() {
		if ($this->set = array(
							'MaxWordsLen' => $this->swap['Required']['SiteSetting']['keyword']['KeywordMaxWordLen'] > 2 && $this->swap['Required']['SiteSetting']['keyword']['KeywordMaxWordLen'] < 12 ? $this->swap['Required']['SiteSetting']['keyword']['KeywordMaxWordLen'] : 12,
							'MaxSplitStringLen' => 128,
							'MaxSqueezeKeywords' => $this->swap['Required']['SiteSetting']['keyword']['MaxSelectedKeywords'] > 1 && $this->swap['Required']['SiteSetting']['keyword']['MaxSelectedKeywords'] < 12 ? $this->swap['Required']['SiteSetting']['keyword']['MaxSelectedKeywords'] : 5,
							'SearchEngines' => $this->swap['Required']['SiteSetting']['keyword']['SearchEngineMash'] ? $this->swap['Required']['SiteSetting']['keyword']['SearchEngineMash'] : '',
							)) {
			return true;
		}
		
		return false;
	}
	
	// Split long string in to singe word
	private function split(&$content, $maxlen = 0, $skipAZaz = false, $unique = false) {
		$keymash = $splited = array();
		$breakedLast = $iloop = $iwordloop = 0;
		$imaxwordloop = $this->set['MaxWordsLen'];
		$validcontent = $tempWord = '';
		
		if ($content) { // Never work with empty
			if ($validcontent = $this->secobj->subString($content, $maxlen ? $maxlen : $this->set['MaxSplitStringLen'])) {
				if ($keymash['StringLen'] = $this->secobj->strLength($validcontent)) {
					// Try split english words
					if (!$skipAZaz && preg_match_all('/([\x{0041}-\x{005A}\x{0061}-\x{007A}\x{0030}-\x{0039}\x{00C0}-\x{00D6}\x{00D8}-\x{00F6}\x{00F8}-\x{00FF}]{3,'.$imaxwordloop.'}+)/', ucwords(utf8_encode($validcontent)), $splited)) {
						$keymash['AZaz'] = false;
						$keymash['Raw'] = $unique ? array_unique($splited[1]) : $splited[1];
						
						// Convert raw format to countable format
						foreach($keymash['Raw'] AS $key => $val) {
							if (!isset($keymash['Indexed'][$val])) {
								$keymash['Indexed'][$val] = 0;
							}
						}
						
						// We done, return keymash;
						return $keymash;
					} else { // If there is no any english words able, we will enter a slow check and use guess to sure which string is word
						$keymash['AZaz'] = true;
						// Split every string to a smaller word
						$breakedLast = $keymash['StringLen'] - ($imaxwordloop - ($imaxwordloop - 1));
						for ($iloop = 0; $iloop < $breakedLast; $iloop++) {
							for($iwordloop = $imaxwordloop; $iwordloop > 1; $iwordloop--) {
								if ($tempWord = $this->secobj->subString($validcontent, $iwordloop, '', $iloop)) {
									$keymash['Raw'][] = $tempWord;
									
									if (!isset($keymash['Indexed'][$tempWord])) {
										$keymash['Indexed'][$tempWord] = 0;
									}
								}
							}
						}
						
						// Make all key in array is uniqued
						if ($unique) {
							$keymash['Raw'] = array_unique($keymash['Raw']);
						}
						
						return $keymash;
					}
				}
			}
		} 
		
		return false; 
	}
	
	private function get_keyword_from_referer() {
		$sitemashs = $tempset = $tempdata = array();
		$serootname = $refparam = $result = '';
		global $_SERVER;
		
		if ($this->set['SearchEngines'] && ($sitemashs = $this->secobj->readCfgStr($this->set['SearchEngines']))) {
			foreach($sitemashs AS $key => $val) {
				$tempdata = $tempset = array();
				$serootname = '';
				
				if ($tempset = $this->secobj->parseCfgStr($val)) {
					if ($serootname = preg_replace('/([\.\-\_\,\'\[\]\~\`\!\@\#\$\%\^\&\*\(\)\-\+\;])/iU', '\\\\\\1', $tempset[0])) {
						if (preg_match('/^.*\:\/\/.*'.$serootname.'\//', $_SERVER['HTTP_REFERER'])) {
							if ($refparam = str_replace('#', '', preg_replace('/(^.*\:\/\/.*'.$serootname.'\/)/iU', '', $_SERVER['HTTP_REFERER']))) {
								switch($tempset[1]) {
									case 'getparam':
										parse_str($refparam, $tempdata);
										
										if ($tempdata[$tempset[2]]) {
											return $this->secobj->addSlashes($tempdata[$tempset[2]]);
										}
										
										break;
										
									case 'preg':
										if (preg_match('/'.preg_replace('/([\-\_\,\'\[\]\~\`\!\@\#\$\%\^\&\-\+\;])/iU', '\\\\\\1', htmlspecialchars_decode($tempset[2])).'/iU', $refparam, $tempdata)) {
											return $this->secobj->addSlashes($tempdata[1]);
										}
										
										break;
										
									case 'split':
										if ($tempdata = explode($tempset[2], $refparam, 64)) {
											if ($tempdata[$tempset[3]]) {
												return $tempdata[$tempset[3]];
											}
										}
										
										break;
										
									default:
										break;
								}
							}
						}
					}
				}
			}
		}
		
		return false;
	}
	
	// Simplely calc the rate of words to select init keywords
	public function squeeze(&$keycontent, &$poolcontent, &$keyArray = array()) {
		$selectedkey = $tempArray = $keyArray = $poolArray = array();
		$newkeyword = true;
		$validcontent = str_replace($keycontent, '', $poolcontent);
		$maxselectedkeywords = $this->set['MaxSqueezeKeywords'];
		$selectedkeycount = $iloop = 0;
		
		if ($validcontent) {
			if (($keyArray = $this->split($keycontent, 0, false, true)) && ($poolArray = $this->split($validcontent, $keyArray['StringLen'] * 10, $keyArray['AZaz'], false))) {
				// Count the rate of keyword's use
				foreach($poolArray['Raw'] AS $key => $val) {
					if (in_array($val, $keyArray['Raw'], true)) {
						$keyArray['Indexed'][$val]++;
					}
				}
				
				foreach($keyArray['Indexed'] AS $key => $val) {
					$newkeyword = true; // Guess all the words is new
					
					foreach ($tempArray['Tag'] AS $validKey => $validVal) {
						if ($this->secobj->strPosition($validVal, $key) !== false) {
							// If found something smaller, set the worlds to false, then break loop;
							$newkeyword = false;
							break;
						}
					}
					
					if ($newkeyword) {
						for ($iloop = 0; $iloop < $this->set['MaxSqueezeKeywords']; $iloop++) {
							if ($tempArray['Count'][$iloop] < $val) {
								// Set the new large number to the cur large one
								$tempArray['Count'][$iloop] = $val;
								
								// set the new keyword
								$selectedkey[$iloop] = $key;
								
								// Save the keywords to the rid list
								$tempArray['Tag'][] = $key; 
								
								if ($maxselectedkeywords-- >= 0) {
									break;
								} else {
									break; break;
								}
							}
						}
					}
				}
				
				return $selectedkey;
			}
		}

		return false;
	}
	
	// Enhance keyword when a user enters from a search engine
	public function interfere(&$poolcontent, &$curkeycontent = '') {
		$sekeyword = '';
		
		if ($sekeyword = $this->get_keyword_from_referer()) {
			$sekeyword .= ' '.$curkeycontent;
			return $this->squeeze($curkeycontent, $poolcontent);
		}
		
		return false;
	}
}
?>