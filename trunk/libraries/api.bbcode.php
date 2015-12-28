<?php 
/*****************************************************************************
	Facula Framework BBCode API
	
	FaculaFramework 2009-2011 (C) Rain Lee <raincious@gmail.com>
	
	@Copyright 2009-2011 Rain Lee <raincious@gmail.com>
	@Author Rain Lee <raincious@gmail.com>
	@Package FaculaFramework
	@Version 0.1-prototype
	
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

class bbcode {
	private $cacobj = null;
	private $secobj = null;
	private $set = array();
	private $pool = array();
	private $tags = array(
							array('Tag' => 'quote', 'Searchs' => "/\[_TAG\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_quote('\\1')"),
							array('Tag' => 'quote', 'Searchs' => "/\[_TAG=(.*),(.*)\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_quote('\\3', '\\1', '\\2')"),
							array('Tag' => 'code', 'Searchs' => "/\[_TAG\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_code('\\1')"),
							array('Tag' => 'url', 'Searchs' => "/\[_TAG\](.*)\[\/_TAG\]/ieU", 'Replaces' => "\$this->do_link('\\1')"),
							array('Tag' => 'url', 'Searchs' => "/\[_TAG=(.*)\](.*)\[\/_TAG\]/ieU", 'Replaces' => "\$this->do_link('\\1', '\\2')"),
							array('Tag' => 'img', 'Searchs' => "/\[_TAG\](.*)\[\/_TAG\]/ieU", 'Replaces' => "\$this->do_image('\\1')"),
							array('Tag' => 'img', 'Searchs' => "/\[_TAG=(.*),(.*)\](.*)\[\/_TAG\]/ieU", 'Replaces' => "\$this->do_image('\\3', '\\1', '\\2')"),
							array('Tag' => 'b', 'Searchs' => "/\[_TAG\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_simpleTag('\\1', 'b')"),
							array('Tag' => 'i', 'Searchs' => "/\[_TAG\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_simpleTag('\\1', 'i')"),
							array('Tag' => 'u', 'Searchs' => "/\[_TAG\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_simpleTag('\\1', 'u')"),
							array('Tag' => 'del', 'Searchs' => "/\[_TAG\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_simpleTag('\\1', 'del')"),
							array('Tag' => 'right', 'Searchs' => "/\[_TAG\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_align('\\1', 'right')"),
							array('Tag' => 'left', 'Searchs' => "/\[_TAG\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_align('\\1', 'left')"),
							array('Tag' => 'center', 'Searchs' => "/\[_TAG\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_align('\\1', 'center')"),
							array('Tag' => 'fleft', 'Searchs' => "/\[_TAG\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_float('\\1', 'left')"),
							array('Tag' => 'fright', 'Searchs' => "/\[_TAG\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_float('\\1', 'right')"),
							array('Tag' => 'fleft', 'Searchs' => "/\[_TAG=(.*)\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_float('\\2', 'left', '\\1')"),
							array('Tag' => 'fright', 'Searchs' => "/\[_TAG=(.*)\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_float('\\2', 'right', '\\1')"),
							array('Tag' => 'size', 'Searchs' => "/\[_TAG=(.*)\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_size('\\1', '\\2')"),
							array('Tag' => 'color', 'Searchs' => "/\[_TAG=(.*)\](.*)\[\/_TAG\]/iesU", 'Replaces' => "\$this->do_color('\\1', '\\2')"),
						);
	public $swap = array();
	
	public function __construct() {
		global $cac, $sec;
		
		if (($this->cacobj = $cac) && ($this->secobj = $sec)) {
			$this->swap['Required']['SiteSetting']['setting'] = array();
			return true;
		} else {
			exit("A critical problem prevents initialization while trying to create ". __CLASS__.".");
		}
		
		return false;
	}
	
	public function _init() {
		$this->set = array('MaxFontSize' => $this->swap['Required']['SiteSetting']['BBCodeMaxFontSize'] > 12 && $this->swap['Required']['SiteSetting']['BBCodeMaxFontSize'] < 38 ? $this->swap['Required']['SiteSetting']['BBCodeMaxFontSize'] : 38,
							'MinFontSize' => $this->swap['Required']['SiteSetting']['BBCodeMinFontSize'] > 9 && $this->swap['Required']['SiteSetting']['BBCodeMinFontSize'] < 13 ? $this->swap['Required']['SiteSetting']['BBCodeMinFontSize'] : 9);
		
		return true;
	}
	
	public function addTag($tag, $preg_search, $preg_replace, $needDHandler = false, &$data = array(), &$format = array(), $formatkey = '') {
		if (!$this->pool['AddedTag'][$tag] && $preg_search && $preg_replace) {
			$this->tags[] = array('Tag' => $tag, 'Searchs' => $preg_search, 'Replaces' => $needDHandler ? str_replace('\"', '\'', str_replace("\"", '\'', str_replace('BBDHandler(', '$this->do_dataHandler(\''.$tag.'\', ', $preg_replace))) : $preg_replace);
			$this->pool['AddedTag'][$tag] = true;
			
			if ($needDHandler && $data && $formatkey && $format) {
				$this->pool['Datas'][$tag]['Data'] = $data;
				$this->pool['Datas'][$tag]['FormatKey'] = $formatkey;
				$this->pool['Datas'][$tag]['Format'] = $format;
			}
			
			return true;
		}
		
		return true;
	}
	
	public function addData($tag, &$data, &$format, $formatkey = '') {
		if (!isset($this->pool['Datas'][$tag])) {
			$this->pool['Datas'][$tag]['Data'] = $data;
			$this->pool['Datas'][$tag]['FormatKey'] = $formatkey;
			$this->pool['Datas'][$tag]['Format'] = $format;
			
			return true;
		}
		
		return true;
	}
	
	private function prepareit() {
		if (empty($this->pool['TempRegs'])) {
			$this->pool['TempRegs'] = array();
			foreach($this->tags AS $key => $val) {
				if ($val['Tag']) {
					$this->pool['TempRegs']['Tags'][] = $val['Tag'];
					$this->pool['TempRegs']['Searchs'][] = str_replace('_TAG', $val['Tag'], $val['Searchs']);
					$this->pool['TempRegs']['Replaces'][] = $val['Replaces'];
					$this->pool['TempRegs']['Split'][] = "/(\[\/".$val['Tag']."\])|(\[".$val['Tag']."\])|(\[".$val['Tag'].".*\])/isU";
				}
			}
			
			if (!empty($this->pool['TempRegs'])) return true;
		} else {
			return true;
		}
		
		return false;
	}
	
	public function parse(&$content, $cachename = '', $expired = 0, $convertHTML = false) {
		$bbcodes = array();
		$temp = '';
		
		if ($cachename && $expired) {
			if (!$cachename || (!$bbcodes = $this->cacobj->read('BBCODE', $cachename, $expired))) {
				// Cache Unit Only allow array be cached, so we need to convert it to array first 
				if ($convertHTML) {
					$temp = htmlspecialchars($content);
				} else {
					$temp = str_replace(array('&'), array('&amp;'), $content);
				}
				$bbcodes = array('Content' => $this->do_parse($temp));
				// Save the thing to a cache file.
				if ($cachename) $this->cacobj->save('BBCODE', $cachename, $bbcodes);
				// Them return it
				return $bbcodes['Content'];
			} else {
				// If we got cache, just return it, we don't need to save the cache or parse it.
				return $bbcodes['Content'];
			}
		} else {
			if ($convertHTML) {
				$temp = htmlspecialchars($content);
			} else {
				$temp = $content
			}
			return $this->do_parse($temp);
		}
		
		return false;
	}
	
	public function removebbcode(&$content) {
		$bbcoded = '';
		
		if ($bbcoded = $this->parse($content)) {
			return $this->secobj->removeHtml($bbcoded);
		}
		
		return false;
	}
	
	private function do_parse($content) {
		if ($this->prepareit()) {
			// Why i just start litte damn php now https://bugs.php.net/bug.php?id=10666
			// Remember the memory and time waste because of the bug in php since preg_replace has been built.
			while($this->isRemainBBCode($content)) {
				$content = $this->secobj->delSlashes(stripslashes(preg_replace($this->pool['TempRegs']['Searchs'], $this->pool['TempRegs']['Replaces'], $content)));
			}
			$content = '<div class="bbcode-area">'.trim(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', trim(nl2br($content)))).'</div>';
		}
		
		return $content;
	}
	
	private function isRemainBBCode(&$content) {
		foreach($this->tags AS $key => $val) {
			if (preg_match(str_replace('_TAG', $val['Tag'], $val['Searchs']), $content)) {
				return true;
			}
		}
		
		return false;
	}
	
	public function matchbbcode(&$content, $justcheck = true, &$results = array(), $tag = '') {
		$tempResult = array();
		$tag_searchstr = '';
		$found = false;
		
		foreach($this->tags AS $key => $val) {
			if (!$tag || $tag == $val['Tag']) {
				$tag_searchstr = str_replace('_TAG', $val['Tag'], $val['Searchs']);
				if (preg_match_all($tag_searchstr, $content, $tempResult)) {
					if ($justcheck) {
						return true;
					} else {
						$results[] = $tempResult;
						$found = true;
					}
				}
			}
		}
		
		if ($found) {
			return true;
		}
		
		return false;
	}
	
	public function split($content) {
		if ($this->prepareit()) {
			return preg_replace($this->pool['TempRegs']['Split'], '', $content);
		} else {
			return false;
		}
	}
	
	private function do_color($color, $content) {
		$color = strtolower($color);
		
		// List from http://www.w3school.com.cn/tags/html_ref_colornames.asp
		$htmlcolors = array(
						'red' => '#FF0000', 
						'green' => '#00FF00', 
						'blue' => '#0000FF', 
						'aliceblue' => '#F0F8FF', 
						'antiquewhite' => '#FAEBD7', 
						'aqua' => '#00FFFF', 
						'aquamarine' => '#7FFFD4', 
						'azure' => '#F0FFFF', 
						'beige' => '#F5F5DC', 
						'bisque' => '#FFE4C4', 
						'black' => '#000000', 
						'blanchedalmond' => '#FFEBCD', 
						'blue' => '#0000FF', 
						'blueviolet' => '#8A2BE2', 
						'brown' => '#A52A2A', 
						'burlywood' => '#DEB887', 
						'cadetblue' => '#5F9EA0', 
						'chartreuse' => '#7FFF00', 
						'chocolate' => '#D2691E', 
						'coral' => '#FF7F50', 
						'cornflowerblue' => '#6495ED', 
						'cornsilk' => '#FFF8DC', 
						'crimson' => '#DC143C', 
						'cyan' => '#00FFFF', 
						'darkblue' => '#00008B', 
						'darkcyan' => '#008B8B', 
						'darkgoldenrod' => '#B8860B', 
						'darkgray' => '#A9A9A9', 
						'darkgreen' => '#006400', 
						'darkkhaki' => '#BDB76B', 
						'darkmagenta' => '#8B008B', 
						'darkolivegreen' => '#556B2F', 
						'darkorange' => '#FF8C00', 
						'darkorchid' => '#9932CC', 
						'darkred' => '#8B0000', 
						'darksalmon' => '#E9967A', 
						'darkseagreen' => '#8FBC8F', 
						'darkslateblue' => '#483D8B', 
						'darkslategray' => '#2F4F4F', 
						'darkturquoise' => '#00CED1', 
						'darkviolet' => '#9400D3', 
						'deeppink' => '#FF1493', 
						'deepskyblue' => '#00BFFF', 
						'dimgray' => '#696969', 
						'dodgerblue' => '#1E90FF', 
						'feldspar' => '#D19275', 
						'firebrick' => '#B22222', 
						'floralwhite' => '#FFFAF0', 
						'forestgreen' => '#228B22', 
						'fuchsia' => '#FF00FF', 
						'gainsboro' => '#DCDCDC', 
						'ghostwhite' => '#F8F8FF', 
						'gold' => '#FFD700', 
						'goldenrod' => '#DAA520', 
						'gray' => '#808080', 
						'green' => '#008000', 
						'greenyellow' => '#ADFF2F', 
						'honeydew' => '#F0FFF0', 
						'hotpink' => '#FF69B4', 
						'indianred' => '#CD5C5C', 
						'indigo' => '#4B0082', 
						'ivory' => '#FFFFF0', 
						'khaki' => '#F0E68C', 
						'lavender' => '#E6E6FA', 
						'lavenderblush' => '#FFF0F5', 
						'lawngreen' => '#7CFC00', 
						'lemonchiffon' => '#FFFACD', 
						'lightblue' => '#ADD8E6', 
						'lightcoral' => '#F08080', 
						'lightcyan' => '#E0FFFF', 
						'lightgoldenrodyellow' => '#FAFAD2', 
						'lightgrey' => '#D3D3D3', 
						'lightgreen' => '#90EE90', 
						'lightpink' => '#FFB6C1', 
						'lightsalmon' => '#FFA07A', 
						'lightseagreen' => '#20B2AA', 
						'lightskyblue' => '#87CEFA', 
						'lightslateblue' => '#8470FF', 
						'lightslategray' => '#778899', 
						'lightsteelblue' => '#B0C4DE', 
						'lightyellow' => '#FFFFE0', 
						'lime' => '#00FF00', 
						'limegreen' => '#32CD32', 
						'linen' => '#FAF0E6', 
						'magenta' => '#FF00FF', 
						'maroon' => '#800000', 
						'mediumaquamarine' => '#66CDAA', 
						'mediumblue' => '#0000CD', 
						'mediumorchid' => '#BA55D3', 
						'mediumpurple' => '#9370D8', 
						'mediumseagreen' => '#3CB371', 
						'mediumslateblue' => '#7B68EE', 
						'mediumspringgreen' => '#00FA9A', 
						'mediumturquoise' => '#48D1CC', 
						'mediumvioletred' => '#C71585', 
						'midnightblue' => '#191970', 
						'mintcream' => '#F5FFFA', 
						'mistyrose' => '#FFE4E1', 
						'moccasin' => '#FFE4B5', 
						'navajowhite' => '#FFDEAD', 
						'navy' => '#000080', 
						'oldlace' => '#FDF5E6', 
						'olive' => '#808000', 
						'olivedrab' => '#6B8E23', 
						'orange' => '#FFA500', 
						'orangered' => '#FF4500', 
						'orchid' => '#DA70D6', 
						'palegoldenrod' => '#EEE8AA', 
						'palegreen' => '#98FB98', 
						'paleturquoise' => '#AFEEEE', 
						'palevioletred' => '#D87093', 
						'papayawhip' => '#FFEFD5', 
						'peachpuff' => '#FFDAB9', 
						'peru' => '#CD853F', 
						'pink' => '#FFC0CB', 
						'plum' => '#DDA0DD', 
						'powderblue' => '#B0E0E6', 
						'purple' => '#800080', 
						'red' => '#FF0000', 
						'rosybrown' => '#BC8F8F', 
						'royalblue' => '#4169E1', 
						'saddlebrown' => '#8B4513', 
						'salmon' => '#FA8072', 
						'sandybrown' => '#F4A460', 
						'seagreen' => '#2E8B57', 
						'seashell' => '#FFF5EE', 
						'sienna' => '#A0522D', 
						'silver' => '#C0C0C0', 
						'skyblue' => '#87CEEB', 
						'slateblue' => '#6A5ACD', 
						'slategray' => '#708090', 
						'snow' => '#FFFAFA', 
						'springgreen' => '#00FF7F', 
						'steelblue' => '#4682B4', 
						'tan' => '#D2B48C', 
						'teal' => '#008080', 
						'thistle' => '#D8BFD8', 
						'tomato' => '#FF6347', 
						'turquoise' => '#40E0D0', 
						'violet' => '#EE82EE', 
						'violetred' => '#D02090', 
						'wheat' => '#F5DEB3', 
						'white' => '#FFFFFF', 
						'whitesmoke' => '#F5F5F5', 
						'yellow' => '#FFFF00', 
						'yellowgreen' => '#9ACD32', 
		);
		
		if (($color[0] == '#' && !isset($color[7])) || $color = $htmlcolors[$color]) {
			return '<span class="bbcode-tag-color" style="display: inline; color:'.$color.';\">'.$content.'</span>';
		}

		return $content;
	}
	
	private function do_size($size, $content) {
		$size = intval($size);
		
		if ($size < $this->set['MaxFontSize'] && $size > $this->set['MinFontSize']) {
			return '<span class="bbcode-tag-size" style="display: inline; font-size: '.$size.'pt;">'.$content.'</span>';
		}
		
		return $content;
	}
	
	private function do_simpleTag($content, $htmltag) {
		return '<'.$htmltag.' class="bbcode-tag-'.$htmltag.'" >'.$content.'</'.$htmltag.'>';
	}
	
	private function do_align($content, $htmltag) {
		switch($htmltag) {
			case 'left':
				return '<span class="bbcode-tag-align" style="display: block; text-align: left;">'.$content.'</span>';
				break;
				
			case 'right':
				return '<span class="bbcode-tag-align" style="display: block; text-align: right;">'.$content.'</span>';
				break;
				
			case 'center':
				return '<span class="bbcode-tag-align" style="display: block; text-align: center;">'.$content.'</span>';
				break;
				
			default:
				break;
		}
		
		return $content;
	}
	
	private function do_float($content, $htmltag, $marginsize = 0) {
		$marginsize = intval($marginsize && $marginsize <= 10 ? $marginsize : '0');
		
		switch($htmltag) {
			case 'left':
				return '<span class="bbcode-float" style="display: block; margin: '.$marginsize.'px; float: left;">'.$content.'</span>';
				break;
				
			case 'right':
				return '<span class="bbcode-float" style="display: block; margin: '.$marginsize.'px; float: right;">'.$content.'</span>';
				break;
				
			default:
				break;
		}
		
		return $content;
	}
	
	private function do_quote($content, $quotedpeople = '', $quotepost = '') {
		if ($quotedpeople && $quotepost)
			return '<span class="bbcode-quote" style="display: block;">'.$quotedpeople.':'.$content.'<br /></span>';
		else
			return '<span class="bbcode-quote" style="display: block;">'.$content.'</span>';
	}
	
	private function do_code($code) {
		return '<span class="bbcode-code" style=\"display: block;\">'.$code.'</span>';
	}
	
	private function do_link($link, $content = '') {
		if ($this->secobj->isURL($link)) {
			if ($this->secobj->isBackLink($link) || $link[0] == '/') {
				if (!$content) {
					return '<a class="bbcode-link" href="'.$link.'" style="display: inline;">'.$link.'</a>';
				} else {
					return '<a class="bbcode-link" href="'.$link.'" style="display: inline;">'.$content.'</a>';
				}
			} else {
				if (!$content)
					return '<a class="bbcode-link bbcode-link-ext" href="'.$link.'" onclick="externalLinkClicked(this);" rel="nofollow" style=\"display: inline;\">'.$link.'</a>';
				else
					return '<a class="bbcode-link bbcode-link-ext" href="'.$link.'" onclick="externalLinkClicked(this);" rel="nofollow" style=\"display: inline;\">'.$content.'</a>';
			}
		}
		
		return $link;
	}
	
	private function do_image($url, $width = 0, $height = 0) {
		$width = intval($width);
		$height = intval($height);
		
		if ($this->secobj->isURL($url)) {
			if ($url = $this->split($url)) {
				if ($width && $height)
					return '<img class="bbcode-image" style="max-width: 100%; overflow: hiden;" src="'.$url.'" width="'.$width.'" height="'.$height.'" onmouseover="hightlightImage(this);" alt="'.$url.'"/>';
				else
					return '<img class="bbcode-image" style="max-width: 100%; overflow: hiden;" src="'.$url.'" onmouseover="hightlightImage(this);" alt="'.$url.'" />';
			}
		}
		
		return $url;
	}
	
	private function do_dataHandler($tag, $targetKey) {
		global $_runtime;
		
		$string = $type = '';
		
		if (isset($this->pool['Datas'][$tag]['Data'][$targetKey])) {
			$type = $this->pool['Datas'][$tag]['Format'][$this->pool['Datas'][$tag]['Data'][$targetKey][$this->pool['Datas'][$tag]['FormatKey']]] ? $this->pool['Datas'][$tag]['Data'][$targetKey][$this->pool['Datas'][$tag]['FormatKey']] : 'Default';
			
			$string = $this->pool['Datas'][$tag]['Format'][$type];
			foreach($this->pool['Datas'][$tag]['Data'][$targetKey] AS $key => $val) {
				$string = str_replace('%'.$key.'%', $val, $string);
			}
			
			$string = str_replace('%SelfRoot%', $_runtime['SelfRoot'], $string);
			$string = str_replace('%RootURL%', $_runtime['RootURL'], $string);
			$string = str_replace('%BaseURL%', $_runtime['BaseURL'], $string);
			$string = str_replace('%ActualBaseURL%', $_runtime['ActualBaseURL'], $string);
			$string = str_replace('%ActualRootURL%', $_runtime['ActualRootURL'], $string);
			
			return $string;
		}
		
		return false;
	}
}

?>