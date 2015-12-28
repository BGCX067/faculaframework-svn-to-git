<?php
/*****************************************************************************
	Facula Framework Uploader API

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

class upload {
	private $secobj = null;
	
	private $set = array();
	private $server = '';
	private $timeout = 1;
	
	private $allowedext = array('jpg' => 'Image',
								'png' => 'Image',
								'bmp' => 'Image',
								'gif' => 'Image',
								'jpeg' => 'Image',
								'txt' => 'Text',
								'svg' => 'Text',
								'pdf' => 'RichText',
								'doc' => 'RichText',
								'rtf' => 'RichText',
								'xml' => 'Code',
								'exe' => 'Binary',
								'bin' => 'Binary',
								'zip' => 'Compressed',
								'7z' => 'Compressed',
								'rar' => 'Compressed',
								'mp3' => 'Media',
								'mp4' => 'Media',
								'wma' => 'Media',
								'mpv' => 'Media',
								'dvi' => 'Media',
								'avi' => 'Media',
								'flv' => 'Media',
								'ape' => 'Media',
								'cue' => 'Media',
								'mp3' => 'Media',
								'mov' => 'Media',
								'rm' => 'Media',
								'rmvb' => 'Media');
	
	public $uploaded = array();
	
	public $swap = array();

	private		$link_id = 0;
	public		$error = '';
	
	public function __construct() {
		global $sec;
		
		$settings = $localsetting = array();
		if (is_object($sec)) {
			$this->link_id = 0;
			$this->secobj = $sec;

			$this->swap['Required'] = array(
											'LocalSetting' => array('upload' => array()),
											'SiteSetting' => array('upload' => array()),
											);
			
			return true;
		} else {
			unset($this);
		}
		
		return false;
	}
	
	public function _init() {
		$uploadlimits = array();
		
		if ($this->swap['Required']['Filled']) {
			if ($this->secobj->addRegular('mimetype', '/^[a-zA-Z0-9]+\/[a-zA-Z0-9]+$/u', '')) {
				$this->set = array('UploadDir' => $this->swap['Required']['LocalSetting']['upload']['UploadDir'],
									'SrvMaxUploadSize' => $this->getMaxUploadSize($uploadlimits),
									'SrvMemoryLimit' => $uploadlimits['memory_limit'],
									'SrvMaxUploads' => $uploadlimits['max_file_uploads'],
									'DefaultMaxsize' => $this->swap['Required']['SiteSetting']['upload']['UploadDefaultMaxsize'] > 1024 && $this->swap['Required']['SiteSetting']['upload']['UploadDefaultMaxsize'] < $this->getMaxUploadSize() ? $this->swap['Required']['SiteSetting']['upload']['UploadDefaultMaxsize'] : $this->getMaxUploadSize(),
									'DefaultMaxfiles' => $this->swap['Required']['SiteSetting']['upload']['UploadDefaultMaxfiles'] > 0 && $this->swap['Required']['SiteSetting']['upload']['UploadDefaultMaxfiles'] < 32 ? $this->swap['Required']['SiteSetting']['upload']['UploadDefaultMaxfiles'] : 15,
									'Time' => $this->secobj->time(),
									'Timeout' => $this->swap['Required']['SiteSetting']['upload']['UploadTimeout'] > 3 && $this->swap['Required']['SiteSetting']['upload']['UploadTimeout'] < 5 ? $this->swap['Required']['SiteSetting']['upload']['UploadTimeout'] : 5,
									'Servers' => $this->secobj->readCfgStr($this->swap['Required']['SiteSetting']['upload']['UploadFTPServers']),
									'WatermarkTextFontFile' => $this->swap['Required']['LocalSetting']['upload']['WatermarkTextFontFile'],
									);
				
				if (!empty($this->swap['Required']['SiteSetting']['upload']['AllowedFileType'])) {
					$this->allowedext = array_merge($this->allowedext, $this->swap['Required']['SiteSetting']['upload']['AllowedFileType']);
				}
				
				return true;
			}
		}
		
		return false;
	}
	
	public function _free() {
		$this->kill();
		return true;
	}
	
	private function getMaxUploadSize(&$uploadlimit = array()) {
		$uploadlimit = array(
			'upload_max_filesize' => $this->secobj->convertKMGtoB(ini_get('upload_max_filesize')),
			'post_max_size' => $this->secobj->convertKMGtoB(ini_get('post_max_size')),
			'memory_limit' => intval($this->secobj->convertKMGtoB(ini_get('memory_limit')) * 0.6),
			'max_file_uploads' => $this->secobj->convertKMGtoB(ini_get('max_file_uploads')),
		);
		
		return min($uploadlimit['upload_max_filesize'], $uploadlimit['post_max_size'], $uploadlimit['memory_limit']);
	}
	
	/*
	Array(
		'FieldName' => array('AllowedType' => "jpg,png,gif", 'Maxsize' => "1000000", 'ResizeImage' => "ResizeIt|MakeThumbnail", 'MaxWidth' => 500, 'MaxHeight' => 500, 'WatermarkText' = 'Texts', 'Maxfiles' = 1),
		'FieldName2' => array('AllowedType' => "jpg,png,gif", 'Maxsize' => "1000000", 'ResizeImage' => "ResizeIt|MakeThumbnail", 'MaxWidth' => 500, 'MaxHeight' => 500, 'WatermarkText' = 'Texts', 'Maxfiles' = 1),
		'FieldName3' => array('AllowedType' => "jpg,png,gif", 'Maxsize' => "1000000", 'ResizeImage' => "ResizeIt|MakeThumbnail", 'MaxWidth' => 500, 'MaxHeight' => 500, 'WatermarkText' = 'Texts', 'Maxfiles' = 1)
	);*/
	
	public function upload(&$set) {
		$sizesArray = $this->uploaded = array(); // clear uploaded array for new upload
		$resized_tmp_file = $tmp_filename = '';
		$filetoupload = $imageraw = 0;
		
		if ($this->set['Servers'][0]) {
			if ($this->getuploads($set)) {
				if (function_exists(ImageTypes)) {
					// Counting how many file we need upload, and btw, resize image.
					foreach($this->uploaded AS $key => $val) {
						$filetoupload++;
						if ($val['ResizeImage'] && $val['Type'] == 'Image') { // Will we need to do something to those images?
							// YES
							if (is_array($val['MaxHeight'])) {
								foreach($val['MaxHeight'] AS $sizeKey => $sizeVal) {
									$sizesArray[$sizeKey] = array('Height' => $sizeVal, 'Width' => isset($val['MaxWidth'][$sizeKey]) && $val['MaxWidth'][$sizeKey] ? $val['MaxWidth'][$sizeKey] : $sizeVal);
								}
							} else {
								$sizesArray[0] = array('Height' => $val['MaxHeight'], 'Width' => $val['MaxWidth']);
							}
							
							foreach($sizesArray AS $resizeKey => $resizeVal) {
								$resized_tmp_file = $val['File'].'_Resized_'.intval($resizeVal['Height']).'_'.intval($resizeVal['Width']).'.'.$val['Extension'];
								if (file_exists($resized_tmp_file)) {
									unlink($resized_tmp_file); // Remove the old temp file (if it not been deleted)
								}
								
								// Deal with images
								if ($tmp_filename = $this->resizeImage($val['File'], $resized_tmp_file, $resizeVal['Height'], $resizeVal['Width'], $val['WatermarkText'])) {
									switch($val['ResizeImage']) {
										case 'ResizeIt':
											$this->uploaded[$key]['File'] = $tmp_filename;
											$this->uploaded[$key]['Root'] = true;
											unlink($val['File']);
											break; break; // Only one file is allowed in ResizeIt Mode
										
										case 'MakeThumbnail':
											$this->uploaded[$key.'_Resized_'.$resizeVal['Height'].'_'.$resizeVal['Width']] = array('File' => $tmp_filename,
																						'Parent' => $key,
																						'Type' => $val['Type'],
																						'Extension' => $val['Extension'],
																						'Name' => 'Resized_'.$val['Name'],
																						'Size' => @filesize($val['File'].'_Resized.'.$val['Extension']));
											$this->uploaded[$key]['Thumbnails'][] = $resizeVal['Height'].'_'.$resizeVal['Width'];
											$this->uploaded[$key]['Root'] = true;
											break;
											
										default:
											break;
									}
								} else {
									switch($val['ResizeImage']) {
										case 'ResizeIt':
											$this->uploaded[$key]['File'] = $val['File'];
											$this->uploaded[$key]['Root'] = true;
											unlink($val['File']);
											break; break;
										
										case 'MakeThumbnail':
											$this->uploaded[$key.'_Resized_'.$resizeVal['Height'].'_'.$resizeVal['Width']] = array('File' => $val['File'],
																						'Parent' => $key,
																						'Type' => $val['Type'],
																						'Extension' => $val['Extension'],
																						'Name' => 'Resized_'.$val['Name'],
																						'Size' => @filesize($val['File'].'_Resized.'.$val['Extension']));
											$this->uploaded[$key]['Thumbnails'][] = $resizeVal['Height'].'_'.$resizeVal['Width'];
											$this->uploaded[$key]['Root'] = true;
											break;
											
										default:
											break;
									}
								}
							}
							$sizesArray = array();
						} else {
							$this->uploaded[$key]['Root'] = true;
						}
					}
				}
				
				if (!$this->error && $this->ftpUpload() && $this->removeAllUploaded()) {
					// If it's done, remove all file and return done
					return $this->uploaded;
				} else {
					$this->removeAllUploaded();
				}
			} else {
				$this->removeAllUploaded();
			}
		}
		
		return false;
	}
	
	private function removeAllUploaded() {
		if (!empty($this->uploaded)) {
			foreach($this->uploaded AS $key => $val) {if (file_exists($val['File'])) @unlink($val['File']);}
			return true;
		}
		
		return false;
	}
	
	private function getuploads(&$set) {
		global $_FILES;
		$gotcount = $maxsize = 0;
		$tmptype = $tmpfile = '';
		$allowedfiletype = array();
		$maxuploadfiles = 0;
		
		if ($this->set['UploadDir']) {
			foreach($set AS $key => $val) {
				// Getting data from setting array and upload files
				if (!$val['Maxfiles']) {
					$val['Maxfiles'] = $this->set['DefaultMaxfiles'];
				} 
				
				if (!$val['Maxsize'] || $val['Maxsize'] > $this->set['DefaultMaxsize']) {
					$val['Maxsize'] = $this->set['DefaultMaxsize'];
				}
				
				if (is_array($_FILES[$key]['name'])) {
					$maxuploadfiles = $this->set['DefaultMaxfiles'] > $this->set['SrvMaxUploads'] ? $this->set['SrvMaxUploads'] : $this->set['DefaultMaxfiles'];
					foreach($_FILES[$key]['name'] AS $upkey => $upval) {
						if ($upval) {
							if ($val['Maxfiles'] >= 0 && $maxuploadfiles >= 0) {
								$tmptype = $this->getExtend($_FILES[$key]['name'][$upkey]);
								$tmpfile = $this->set['UploadDir'].DIRECTORY_SEPARATOR.$this->secobj->randSeed(3).'.'.$tmptype;
								$allowedfiletype = explode(',', strtolower(trim($val['AllowedType'])));
								if ($_FILES[$key]['size'][$upkey] <= $val['Maxsize'] && $_FILES[$key]['size'][$upkey]) { // If size is 0, may also means file too large
									if ($this->allowedExt($_FILES[$key]['name'][$upkey]) && in_array($tmptype, $allowedfiletype)) {
										if (move_uploaded_file($_FILES[$key]['tmp_name'][$upkey], $tmpfile)) {
											$this->uploaded[$key.'_'.$upkey] = array('File' => $tmpfile,
																				'Type' => $this->allowedext[$tmptype],
																				'Extension' => $tmptype,
																				'OutType' => $this->secobj->regularMatch('mimetype', $_FILES[$key]['type'][$upkey], 32),
																				'Name' => $this->secobj->isFilename($_FILES[$key]['name'][$upkey]),
																				'Size' => $_FILES[$key]['size'][$upkey],
																				'ResizeImage' => $val['ResizeImage'],
																				'WatermarkText' => $val['WatermarkText'],
																				'MaxWidth' => $val['MaxWidth'],
																				'MaxHeight' => $val['MaxHeight']);
											$gotcount++;
											$val['Maxfiles']--;
											$maxuploadfiles--;
										} else {
											$this->error = 'ERROR_UPLOAD_FILE_ACCESS_DENIED|'.$_FILES[$key]['name'][$upkey];
											break;break;
										}
									} else {
										$this->error = 'ERROR_UPLOAD_FILE_NOT_ALLOWED|'.$_FILES[$key]['name'][$upkey];
										break;break;
									}
								} else {
									$this->error = 'ERROR_UPLOAD_FILE_TOO_LARGE|'.$_FILES[$key]['name'][$upkey];
									break;break;
								}
							} else {
								$this->error = 'ERROR_UPLOAD_FILE_COUNT_EXCEED|'.$_FILES[$key]['name'][$upkey];
								break;break;
							}
						}
					}
				} else {
					$tmptype = $this->getExtend($_FILES[$key]['name']);
					$tmpfile = $this->set['UploadDir'].DIRECTORY_SEPARATOR.$this->secobj->randSeed(3).'.'.$tmptype;
					$allowedfiletype = explode(',', strtolower(trim($val['AllowedType'])));
					if ($_FILES[$key]['size'] <= $val['Maxsize'] && $_FILES[$key]['size']) {
						if ($this->allowedExt($_FILES[$key]['name']) && in_array($tmptype, $allowedfiletype)) {
							if (move_uploaded_file($_FILES[$key]['tmp_name'], $tmpfile)) {
								$this->uploaded[$key] = array('File' => $tmpfile,
															'Type' => $this->allowedext[$tmptype],
															'Extension' => $tmptype,
															'OutType' => $this->secobj->regularMatch('mimetype', $_FILES[$key]['type'], 32),
															'Name' => $this->secobj->isFilename($_FILES[$key]['name']),
															'Size' => $_FILES[$key]['size'],
															'ResizeImage' => $val['ResizeImage'],
															'WatermarkText' => $val['WatermarkText'],
															'MaxWidth' => $val['MaxWidth'],
															'MaxHeight' => $val['MaxHeight']);
								
								$gotcount++; // Total Counter: In this section, we only have one file here so we just make count here, this number should only be one
							} else {
								$this->error = 'ERROR_UPLOAD_FILE_ACCESS_DENIED|'.$_FILES[$key]['name'];
								break;
							}
						} else {
							$this->error = 'ERROR_UPLOAD_FILE_NOT_ALLOWED|'.$_FILES[$key]['name'];
							break;
						}
					} else {
						$this->error = 'ERROR_UPLOAD_FILE_TOO_LARGE|'.$_FILES[$key]['name'];
						break;
					}
				}
			}
			
			if ($gotcount && !$this->error) {
				return true;
			}
		} else {
			$this->error = 'ERROR_UPLOAD_CONFIG_MISSING';
		}
		
		return false;
	}
		
	public function ftpConnect() {
		if (!empty($this->set['Servers'])) {
			foreach ($this->set['Servers'] AS $key => $val) {
				$this->error = '';
				if (($this->server = $val)) {
					if ($this->doFTPConnect()) {
						return true;
					}
				}
			}
		}
		
		$this->error = 'ERROR_FTP_NO_SERVER_AVAILABLE';
		return false;
	}
	
	private function doFTPConnect(){
		$server = array();
		$host = $port = $user = $pass = $workingdir = $accessurl = '';
		
		if ($this->isFTPConnected()) {
			return true;
		} else {
			if ($this->server) {
				if ($this->isFTPConnected()) ftp_close($this->link_id);
				$this->set['WorkingDir'] = $this->set['AccessURL'] = '';
				list($host, $user, $pass, $workingdir, $accessurl) = $this->secobj->parseCfgStr($this->server);
				$server = explode(':', $host);
				$server[1] = intval($server[1] ? $server[1] : 21);
				
				if ($server[0] && $server[1] && $user && $pass && $workingdir && $accessurl) {
					if ($this->link_id = ftp_connect($server[0], $server[1], $this->set['Timeout'])) {
						if (ftp_login($this->link_id, $user, $pass)) {
							if ($this->ftpMkdirs($workingdir, true)) {
								$this->set['WorkingDir'] = ftp_pwd($this->link_id).'/';
								$this->set['AccessURL'] = $accessurl;
								
								return $this->link_id;
							} else {
								$this->error = 'ERROR_FTP_SET_WORKINGDIR_FAILED';
								@ftp_close($this->link_id);
							}
						} else {
							$this->error = 'ERROR_FTP_LOGIN_FAILED';
							@ftp_close($this->link_id);
						}
					} else {
						$this->error = 'ERROR_FTP_CONNECT_FAILED';
					}
				} else {
					$this->error = 'ERROR_FTP_CONNECT_FAILED_MISS_SETTING';
				}
			} else {
				$this->error = 'ERROR_FTP_CONNECT_FAILED_NO_SETTING';
			}
		}

		$this->link_id = 0;
		return false;
	}
	
	private function isFTPConnected() {
		if ($this->link_id) {
			if (ftp_raw($this->link_id, 'NOOP')) {
				return true;
			}
		}
		
		return false;
	}

	public function ftpBye() {
		if ($this->isFTPConnected()) {
			@ftp_close($this->link_id);
			$this->link_id = 0;
			
			return true;
		}
		
		return true;
	}
	
	public function kill() {
		if ($this->isFTPConnected()) {
			$this->ftpBye();
		}
		
		return true;
	}
	
	public function ftpUpload() {
		$downloadurls = array();
		$newpath = $accesspath = '';
		$remote_file = '';
		$uploaded = 0;
		
		if (!empty($this->uploaded)) {
			if ($this->isFTPConnected() || $this->ftpConnect()) {
				if (!$newpath = $this->ftpMkdirs($this->makeFilePath(), true)) { // Make sure this return true when folder be created or folder existed.
					$this->error = 'ERROR_FTP_NOT_MAKE_DIR';
				} else { // IfTheShame
					$accesspath = substr_replace($newpath, '', strpos($newpath, $this->set['WorkingDir']), strlen($this->set['WorkingDir']) - 1);
					
					foreach ($this->uploaded AS $key => $val) {
						if (file_exists($val['File'])) {
							if ($this->allowedExt($val['File'])) {
								if ($val['Size'] < 5368709120) { // 3 ways to hashing file: if file is small, using md5
									$remote_file = $this->secobj->str2tiny(md5_file($val['File']), 16).'.'.($val['Extension'] ? $val['Extension'] : 'file');
								} elseif (function_exists('hash_file')) { // 3 ways to hashing file: if file is big, using crc32 if able
									$remote_file = $this->secobj->str2tiny(hash_file('crc32', $val['File']), 16).'.'.($val['Extension'] ? $val['Extension'] : 'file');
								} else { // 3 ways to hashing file: if hash_file not exists, just give a name randomly
									$remote_file = $this->secobj->str2tiny($val['File'].$this->secobj->randSeed(6), 16).'.'.($val['Extension'] ? $val['Extension'] : 'file');
								}
								if (ftp_pasv($this->link_id, true) && ftp_put($this->link_id, $remote_file ? $remote_file : md5($val['File']).'.'.$val['Extension'], $val['File'], FTP_BINARY)) {
									if ($this->uploaded[$key]['RemoteFile'] = str_replace(':/', '://', str_replace('//', '/', $this->set['AccessURL'].'/'.$accesspath.'/'.$remote_file))) {
										$uploaded++;
									} else {
										$this->error = 'ERROR_FTP_EMPTY_FILE_PATH|'.$remote_file;
									}
								} else {
									$this->error = 'ERROR_FTP_CANNOT_UPLOAD|'.$remote_file;
								}
							} else {
								$this->error = 'ERROR_FTP_FILE_NOT_ALLOWED|'.$key;
							}
						}
					}
					
					$this->ftpBye();

					if ($uploaded) {
						return true;
					}
				}
			}
		}
		
		return false;
	}
	
	public function ftpChd($dir) {
		$dirpath = array();
		$mylocal = '';
		$dir_changed = 0;
		if ($this->isFTPConnected() || $this->ftpConnect()) {
			$dir = str_replace('//', '/', str_replace('\\', '/', str_replace('\\\\', '\\', $dir)));
			if ($dir[strlen($dir) - 1] == '/') {
				$dir = substr($dir, 0, strlen($dir) - 1);
			} // Remove the last / so we will not go back to root unexpectly
			if (!empty($dir)) {
				$dirpath = explode('/', $dir);
				foreach($dirpath AS $key => $val) {
					if (!empty($val)) {
						if (ftp_chdir($this->link_id, $val)) {
							$dir_created++;
						} elseif ($this->ftpMkdir($val) && @ftp_chdir($this->link_id, $val)) {
							$dir_created++;
						} else {
							return false;
						}
					} else {
						ftp_chdir($this->link_id, '/');
					}
				}
				
				return ftp_pwd($this->link_id);
			}
		}
		
		return false;
	}
	
	public function ftpMkdir($dirname){
		if ($this->isFTPConnected() || $this->ftpConnect()) {
			if (!empty($dirname)) {
				if (ftp_mkdir($this->link_id, $dirname)) {
					return true;
				} else {
					return true;
				}
			}
		}
		
		return false;
	}
	
	private function ftpMkdirs($path, $holdpos = false, $linkid = 0) {
		$i = 0;
		$dirs = '';
		$countdirs = 0;
		$chdpath = $newpath = '';
		
		if ($this->isFTPConnected() || $this->ftpConnect()) {
			if ($this->set['WorkingDir']) {
				$chdpath = $this->ftpChd($this->set['WorkingDir']).'/';
			}
			
			if ($chdpath == $this->set['WorkingDir']) {
				$dirs = explode('/', str_replace('//', '/', str_replace('\\', '/', str_replace('\\\\', '\\', $path))));
			} else {
				$dirs = explode('/', str_replace('//', '/', str_replace('\\', '/', str_replace('\\\\', '\\', $this->set['WorkingDir'].$path))));
			}
			
			$countdirs = count($dirs);
			
			for ($i = 0; $i < $countdirs; $i++) {
				if($dirs[$i] && !$this->ftpChd($dirs[$i])){
					if($this->ftpMkdir($dirs[$i])) {
						$this->ftpChd($dirs[$i]);
					}
				}
			}
			
			if (!$holdpos) {
				$this->ftpChd($this->set['WorkingDir']);
				$newpath = ftp_pwd($this->link_id);
			} else {
				$newpath = ftp_pwd($this->link_id);
			}

			return $newpath;
				
		} else {
			$this->error = 'ERROR_FTP_CONNECTION_LOST';
		}
		
		return false;
	}
	
	private function makeFilePath() {
		return $this->secobj->str2tiny(date('Y')).'/'.$this->secobj->str2tiny(date('md')).'/';
	}
	
	public function allowedExt($filename){
		$ext = '';
		
		$ext = $this->getExtend($filename);
		if ($ext && isset($this->allowedext[$ext])) {
			return true;
		}
		return false;
	}
	
	public function addAllowedExt($ext){
		if (is_array($ext)) {
			$this->allowedext = array_merge($this->allowedext, $ext);
		} else {
			$this->allowedext[] = $ext;
		}

		return true;
	}
	
	public function getExtend($file_name) {
		$ext = $extinfo = '';
		$extinfocount = 0;
		
		$extinfo = explode('.', $file_name);
		
		if (isset($extinfo[1])) {
			$ext = $extinfo[sizeof($extinfo) - 1];
			
			if ($ext)
				return strtolower($ext);
			else
				return false;
		}

		return false;
	}
	
	public function resizeImage($fullFilename, $thumbFilename, $size_h, $size_w, &$watermarktext = '') {
		if (!$size_h || !$size_w) return false;
		
		if ($this->imageResizeGD($fullFilename, $thumbFilename, $size_h, $size_w, $watermarktext)) {
			return $thumbFilename;
		}
		return false;
	}
	
	private function imageResizeGD(&$fullFilename, &$thumbFilename, &$size_h, &$size_w, &$watermarktext = '') {
		$imagearea = $old_ratio = $new_ratio = $target_width = $target_height = $org_width = $org_height = $type = 0;
		$image_org = $image_new = $image_textcolor_f = $image_textcolor_b = null;
		
		if (is_readable($fullFilename)) {
			list($org_width, $org_height, $type) = GetImageSize($fullFilename);
			
			$imagearea = $org_width * $org_height; // get total px for mem check
			
			$old_ratio = $org_width / $org_height;
			$new_ratio = $size_w / $size_h;
			
			if (($imagearea * 4) < $this->set['SrvMemoryLimit'] && ($size_h * $size_w) * 4 < $this->set['SrvMemoryLimit']) { // Just for anit memory leak, if it large than our allowed size, just don't resize it.
				if($image_org = $this->readImageGD($fullFilename, $type)){
					if ($old_ratio > $new_ratio) {
						$target_width = ceil($org_height * $new_ratio);
						$target_height = $org_height;
						
						$area_x = ceil(($org_width - $target_width) / 2);
						$area_y = 0;
					} else {
						$target_width = $org_width;
						$target_height = ceil($org_width / $new_ratio);
						
						$area_x = 0;
						$area_y = ceil(($org_height - $target_height) /2 );
					}
					
					if ($image_new = imagecreatetruecolor($size_w, $size_h)) {
						if (imagecopyresampled($image_new, $image_org, 0, 0, $area_x, $area_y, $size_w, $size_h, $target_width, $target_height)) {
							if ($watermarktext && $size_w > 300 && $size_h > 300) {
								$image_textcolor_f = imagecolorallocate($image_new, 255, 255, 255);
								$image_textcolor_b = imagecolorallocate($image_new, 0, 0, 0);
								if (file_exists($this->set['WatermarkTextFontFile'])) {
									imagettftext($image_new, 10, 0, 21, $size_h - 19, $image_textcolor_b, $this->set['WatermarkTextFontFile'], $watermarktext);
									imagettftext($image_new, 10, 0, 20, $size_h - 20, $image_textcolor_f, $this->set['WatermarkTextFontFile'], $watermarktext);
								} else {
									imagestring($image_new, 5, 21, $size_h - 19, $watermarktext, $image_textcolor_b);
									imagestring($image_new, 5, 20, $size_h - 20, $watermarktext, $image_textcolor_f);
								}
							}
							
							if ($this->saveImageGD($image_new, $thumbFilename, $type)) {
								imagedestroy($image_org);
								imagedestroy($image_new);
								
								return true;
							}
						}
					}
				}
			}
		}

		return false;
	}

	private function readImageGD($filename, $type) {
		switch($type) {
			case 1:
				return imagecreatefromgif($filename);
			break;
			
			case 2:
				return imagecreatefromjpeg($filename);
			break;
			
			case 3:
				return imagecreatefrompng($filename);
			break;
			
			default:
				$this->error = 'ERROR_UPLOAD_IMAGE_SERVER_NOT_SUPPORTED';
			break;
		}
		// Code definitely C&V from other place, but i forgot where.
		return false;
	}

	private function saveImageGD($image, $filename, $type) {
		switch ($type) {
			case 1 : return imagegif($image, $filename);
			case 2 : return imagejpeg($image, $filename, 80);
			case 3 : return imagepng($image, $filename);
			default: break;
		}
		return false;
	}
}
?>