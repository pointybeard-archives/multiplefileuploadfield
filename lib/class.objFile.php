<?php
	Final Class objFileException extends Exception{}
	
	Class objFile{
		
		protected static $_imageMimeTypes = array(
			'image/gif',
			'image/jpg',
			'image/jpeg',
			'image/pjpeg',
			'image/png',
			'image/x-png',
		);
		
		protected $_name, $_path, $_size, $_mimetype, $_meta, $_file;
		
		public function __construct($file, $size=NULL, $type=NULL, array $meta=NULL){
			
			if(!file_exists($file)) throw new objFileException(sprintf("%s does not exist.", $file));
			
			if(!is_readable($file)) throw new objFileException(sprintf("%s is not readable.", $file));
				
			if (is_null($type)) {
				$type = (function_exists('mime_content_type')
						? mime_content_type($file)
						: 'application/octet-stream'
				);
			}

			if (is_null($size)) $size = filesize($file);
			
			if (is_null($meta)) $meta = self::getMetaInfo($file, $type);
			
			$this->_file = $file;
			$this->_name = basename($file); 
			$this->_path = dirname($file); 
			$this->_size = $size; 
			$this->_mimetype = $type; 
			$this->_meta = $meta;
			
			return true;
		}
		
		public static function getMetaInfo($file, $type){
			$meta = array();

			if(!file_exists($file) || !is_readable($file)) return $meta;

			$meta['creation'] = DateTimeObj::get('c', filemtime($file));

			if(in_array(strtolower($type), self::$_imageMimeTypes) && $array = @getimagesize($file)){
				$meta['width'] = $array[0];
				$meta['height'] = $array[1];
			}

			return $meta;
		}
		
		public function __get($name){
			return $this->{"_" . $name};
		}
		
		public function delete(){
			return General::deleteFile($this->_file);
		}
		
		public function rename($name){
			
		}
		
		public function move($dest){
			
		}
	}

	
	Final Class objTmpFileException extends Exception{}
		
	Final Class objTmpFile extends objFile{
		
		private static $_uploadCodes = array(
			UPLOAD_ERR_OK => 'UPLOAD_ERR_OK',
			UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE',
			UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
			UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
			UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE',
			UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
			UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
			UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION'
		);
		
		public static function uploadCodeToString($code){
			return self::$_uploadCodes[$code];
		}
		
		private $_tmp, $_realname, $_data;
		
		public function __construct($data){
			
			if ($data['error'] != UPLOAD_ERR_OK) {
				throw new objTmpFileException(sprintf(
					"An error was encountered while uploaded. Error %d: %s was returned.", $data['error'], self::uploadCodeToString($data['error'])), 
					$data['error']
				);
				break;
			}
			
			/*
			array(5) {
			  ["name"]=>
			  string(48) "Screen Shot 2013-05-19 at 10.2-51981ae54393a.png"
			  ["type"]=>
			  string(9) "image/png"
			  ["tmp_name"]=>
			  string(14) "/tmp/phpNRpngB"
			  ["error"]=>
			  int(0)
			  ["size"]=>
			  int(7590)
			}
			*/
			
			// public function __construct($file, $size=NULL, $type=NULL, $meta=NULL){
			$this->_tmp = $data['tmp_name']; 
			$this->_realname = $data['name']; 
			$this->_data = $data;
			
			return parent::__construct($data['tmp_name'], $data['size'], $data['type']);
		}
		
		public function __get($name){
			return $this->{"_" . $name};
		}
		
		public function upload($dest_path, $dest_name){
			$result = General::uploadFile(
				$dest_path, $dest_name, $this->_tmp,
				Symphony::Configuration()->get('write_mode', 'file')
			);
			
			if($result === false) throw new objTmpFileException('An error occurred while attempting to upload.');
			
			return new objFile($dest_path . '/' . $dest_name, $this->_size, $this->_mimetype, $this->_meta);
		}
	}
	
