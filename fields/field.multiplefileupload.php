<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(TOOLKIT . '/fields/field.upload.php');
	require_once(realpath(dirname(__FILE__) . '/../lib') . '/class.objFile.php');
	
	
	class FieldMultipleFileUpload extends FieldUpload {
		public function __construct(){
			parent::__construct();
			$this->_name = __('Multiple File Upload');
		}

		
		/*-------------------------------------------------------------------------
			Publish:
		-------------------------------------------------------------------------*/
		
		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null){
			
			$hide_input = false;
			
			if(!is_null($data) && is_array($data) && count($data) > 0){
				$d = array();
				foreach($data as $key => $values){
					for($ii = 0; $ii < count($values); $ii++){
						$d[$ii][$key] = $values[$ii];
					}
				}
				$data = $d;
			}
			
			if (is_dir(DOCROOT . $this->get('destination') . '/') === false) {
				$flagWithError = __('The destination directory, %s, does not exist.', array(
					'<code>' . $this->get('destination') . '</code>'
				));
			}

			else if ($flagWithError && is_writable(DOCROOT . $this->get('destination') . '/') === false) {
				$flagWithError = __('Destination folder is not writable.')
					. ' '
					. __('Please check permissions on %s.', array(
						'<code>' . $this->get('destination') . '</code>'
					));
			}

			$label = Widget::Label($this->get('label'));
			$label->setAttribute('class', 'file');
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));

			$span = new XMLElement('span', NULL, array('class' => 'frame'));
			if (is_array($data) && count($data) > 0) {
				foreach($data as $d){
					if(isset($d['file'])){
						// Check to see if the file exists without a user having to
						// attempt to save the entry. RE: #1649
						$file = WORKSPACE . preg_replace(array('%/+%', '%(^|/)\.\./%'), '/', $d['file']);

						if (file_exists($file) === false || !is_readable($file)) {
							$flagWithError = __('One or more of the files uploaded are no longer available. Please check that they exist, and are readable.');
						}

						$span->appendChild(new XMLElement('p', Widget::Anchor('/workspace' . preg_replace("![^a-z0-9]+!i", "$0&#8203;", $d['file']), URL . '/workspace' . $d['file'])));
						
						$hide_input = true;
					}
				}
			}

			$span->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').'][]'.$fieldnamePostfix, NULL, ($hide_input ? 'hidden' : 'file'), array('multiple' => 'multiple')));
			
			$span->appendChild(new XMLElement('ul', NULL, array('class' => 'output')));
			
			$label->appendChild($span);

			if($flagWithError != NULL) $wrapper->appendChild(Widget::Error($label, $flagWithError));
			else $wrapper->appendChild($label);
		}


		/*-------------------------------------------------------------------------
			Saving Publish Data:
		-------------------------------------------------------------------------*/
		
		public function checkPostFieldData($data, &$message, $entry_id = NULL) {
			
			if (is_dir(DOCROOT . $this->get('destination') . '/') === false) {
				$message = __('The destination directory, %s, does not exist.', array(
					'<code>' . $this->get('destination') . '</code>'
				));

				return self::__ERROR__;
			}

			else if (is_writable(DOCROOT . $this->get('destination') . '/') === false) {
				$message = __('Destination folder is not writable.')
					. ' '
					. __('Please check permissions on %s.', array(
						'<code>' . $this->get('destination') . '</code>'
					));

				return self::__ERROR__;
			}
			
			if(count($data) == 1 && is_string($data[0])){
				
				// Empty Array => retain existing files
				//array(1) { [0]=> string(0) "" }
				
				// Get existing values, if any, and load in to $data array
				
				if(!isset($entry_id)){
					// Oh oh, how did it get here? crap out please.
					$message = __(
						'No data found, but entry_id was passed.'
					);
					return self::__ERROR__;
				}

				$data = Symphony::Database()->fetchCol('file', sprintf(
					"SELECT `file` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d",
					$this->get('id'),
					$entry_id
				));
				
			}

			while (list($index, $item) = each($data)) {
				
				// Retaining existing files
				if(is_string($item)){
					/**
					 * Ensure the file exists in the `WORKSPACE` directory
					 * @link http://symphony-cms.com/discuss/issues/view/610/
					 */
					$file = WORKSPACE . preg_replace(array('%/+%', '%(^|/)\.\./%'), '/', $item);

					if (file_exists($file) === false || !is_readable($file)) {
						$message = __('The file ‘%s’ is no longer available. Please check that it exists, and is readable.', array($file));
						return self::__INVALID_FIELDS__;
					}

					// Ensure that the file still matches the validator and hasn't
					// changed since it was uploaded.
					if ($this->get('validator') != null) {
						$rule = $this->get('validator');

						if (General::validateString($file, $rule) === false) {
							$message = __('File chosen in ‘%s’ does not match allowable file types for that field.', array(
								$this->get('label')
							));
							return self::__INVALID_FIELDS__;
						}
					}
					
					continue;
				}
				
				
				// New or empty file upload field
				
				try{
					$file = new objTmpFile($item);
				}
				
				catch(objTmpFileException $e){
					
					if($e->getCode() == UPLOAD_ERR_NO_FILE){
						if ($this->get('required') == 'yes') {
							$message = __('‘%s’ is a required field.', array($this->get('label')));
							return self::__MISSING_FIELDS__;
						}
						return self::__OK__;
					}

					switch ($e->getCode()) {
						case UPLOAD_ERR_INI_SIZE:
							$message = __(
								'File chosen in ‘%1$s’ exceeds the maximum allowed upload size of %2$s specified by your host.',
								array(
									$this->get('label'), 
									(is_numeric(ini_get('upload_max_filesize')) 
										? General::formatFilesize(ini_get('upload_max_filesize')) 
										: ini_get('upload_max_filesize'))
								)
							);
							break;

						case UPLOAD_ERR_FORM_SIZE:
							$message = __(
								'File chosen in ‘%1$s’ exceeds the maximum allowed upload size of %2$s, specified by Symphony.',
								array(
									$this->get('label'), 
									General::formatFilesize($_POST['MAX_FILE_SIZE'])
								)
							);
							break;

						case UPLOAD_ERR_PARTIAL:
							$message = __('File chosen in ‘%s’ was only partially uploaded due to an error.', array($this->get('label')));
							break;
						
						case UPLOAD_ERR_NO_TMP_DIR:
							$message = __(
								'Unable to upload any files as TMP directory could not be located. Check server configuration.'
							);
							break;
						
						case UPLOAD_ERR_CANT_WRITE:
							$message = __('Uploading ‘%s’ failed. Could not write temporary file to disk.', array($this->get('label')));
							break;

						case UPLOAD_ERR_EXTENSION:
							$message = __('Uploading ‘%s’ failed. File upload stopped by extension.', array($this->get('label')));
							break;
					}
					
					return self::__ERROR_CUSTOM__;
					
				}
				
				catch(objFileException $e){
					$message = $e->getMessage();
					return self::__ERROR__;
				}

				if ($this->get('validator') != null) {
					$rule = $this->get('validator');

					if (!General::validateString(Lang::createFilename($file->realname), $rule)) {
						$message = __('File chosen in ‘%s’ does not match allowable file types for that field.', array($this->get('label')));

						return self::__INVALID_FIELDS__;
					}
				}
				
			}
			
			return self::__OK__;
		}

		public function processRawFieldData($data, &$status, &$message = NULL, $simulate = false, $entry_id = NULL) {
			
			$status = self::__OK__;

			$result = array(
				'file' =>		array(),
				'mimetype' =>	array(),
				'size' =>		array(),
				'meta' =>		array()
			);
			
			$abs_path = DOCROOT . '/' . trim($this->get('destination'), '/');
			$rel_path = str_replace('/workspace', '', $this->get('destination'));
			
			if(count($data) == 1 && is_string($data[0])){
				
				// Empty Array => retain existing files
				//array(1) { [0]=> string(0) "" }
				
				// Get existing values, if any, and load in to $data array
				
				if(!isset($entry_id)){
					// Oh oh, how did it get here? crap out please.
					$message = __(
						'No data found, but entry_id was passed.'
					);
					return self::__ERROR__;
				}

				$rows = Symphony::Database()->fetch(sprintf(
					"SELECT `file`, `mimetype`, `size`, `meta` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d",
					$this->get('id'),
					$entry_id
				));
				
				$data = array();
				foreach($rows as $r){
					try{
						// Load the existing file as an object so various validation & meta data healing can be done
						$file = new objFile(
							(WORKSPACE . preg_replace(array('%/+%', '%(^|/)\.\./%'), '/', $r['file'])), 
							$r['size'], $r['mimetype'], unserialize($r['meta']));
						
						// Extract the data back out and put in the results array
						$result['file'][] = rtrim($rel_path, '/') . '/' . $file->name;
						$result['size'][] = $file->size;
						$result['mimetype'][] = $file->mimetype;
						$result['meta'][] = serialize($file->meta);
					}
					
					catch(objFileException $e){
						$message = __('The file ‘%s’ is no longer available. Please check that it exists, and is readable.', array($r['file']));
						$status = self::__INVALID_FIELDS__;
						return false;
					}
				}
				
				return $result;
			}
			
			// Look for existing and remove them
			if (!is_null($entry_id)) {
				$row = Symphony::Database()->fetchCol('file', sprintf(
					"SELECT `file` FROM `tbl_entries_data_%s` WHERE `entry_id` = %d LIMIT 1",
					$this->get('id'),
					$entry_id
				));
				
				foreach($row as $f){
					try{
						$file = new objFile(WORKSPACE . preg_replace(array('%/+%', '%(^|/)\.\./%'), '/', $f));
						$file->delete();
						unset($file);
					}
					catch(objFileException $e){
						// File is either not readable or does not exist. Just carry on.
					}
					catch(Exception $e){
						// Deletion failed for some reason. Just carry on.
					}
				}
			}
			
			// Uploading or Removal has occurred
			while (list($index, $item) = each($data)) {
				
				// $data[$ii]['name'] = self::getUniqueFilename($data[$ii]['name']);
				
				try{
					$tmpFile = new objTmpFile($item);
				}
				catch(objTmpFileException $e){
					return false;
				}
				
				// Sanitize the filename
				$name = self::getUniqueFilename(Lang::createFilename($tmpFile->realname));
				
				// Upload the file
				try{
					$file = $tmpFile->upload($abs_path, $name);
				}
				catch(objFileException $e){
						$message = __(
							'There was an error while trying to upload the file %1$s to the target directory %2$s.',
							array(
								'<code>' . $tmpFile->realname . '</code>',
								'<code>workspace/' . ltrim($rel_path, '/') . '</code>'
							)
						);
						$status = self::__ERROR_CUSTOM__;
						return false;
				}
				
				$result['file'][] = rtrim($rel_path, '/') . '/' . $file->name;
				$result['size'][] = $file->size;
				$result['mimetype'][] = $file->mimetype;
				$result['meta'][] = serialize($file->meta);
				
			}
			
			return $result;
		}

		public function appendFormattedElement(&$wrapper, $data){
			parent::appendFormattedElement($wrapper, $data);
			$field = $wrapper->getChildrenByName($this->get('element_name'));
			if(!empty($field))
				end($field)->appendChild(new XMLElement('clean-filename', General::sanitize(self::getCleanFilename(basename($data['file'])))));
		}

		private static function getUniqueFilename($filename) {
			## since uniqid() is 13 bytes, the unique filename will be limited to ($crop+1+13) characters;
			$crop  = '30';
			return preg_replace("/([^\/]*)(\.[^\.]+)$/e", "substr('$1', 0, $crop).'-'.uniqid().'$2'", $filename);
		}

		private static function getCleanFilename($filename) {
			return preg_replace("/([^\/]*)(\-[a-f0-9]{13})(\.[^\.]+)$/", '$1$3', $filename);
		}
		
		/*-------------------------------------------------------------------------
			Setup:
		-------------------------------------------------------------------------*/

			public function createTable(){
				return Symphony::Database()->query("
					CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
					  `id` int(11) unsigned NOT NULL auto_increment,
					  `entry_id` int(11) unsigned NOT NULL,
					  `file` varchar(255) default NULL,
					  `size` int(11) unsigned NULL,
					  `mimetype` varchar(100) default NULL,
					  `meta` varchar(255) default NULL,
					  PRIMARY KEY  (`id`),
					  KEY `entry_id` (`entry_id`),
					  KEY `file` (`file`),
					  KEY `mimetype` (`mimetype`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
				");
			}
	}
