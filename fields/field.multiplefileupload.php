<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(TOOLKIT . '/fields/field.upload.php');

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
							$flagWithError = __('The file uploaded is no longer available. Please check that it exists, and is readable.');
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


		private function __checkPostFieldData($data, &$message, $entry_id=NULL){
			/**
			 * For information about PHPs upload error constants see:
			 * @link http://php.net/manual/en/features.file-upload.errors.php
			 */
			$message = null;

			if (
				empty($data)
				|| (
					is_array($data)
					&& isset($data['error'])
					&& $data['error'] == UPLOAD_ERR_NO_FILE
				)
			) {
				if ($this->get('required') == 'yes') {
					$message = __('‘%s’ is a required field.', array($this->get('label')));

					return self::__MISSING_FIELDS__;
				}

				return self::__OK__;
			}

			// Its not an array, so just retain the current data and return
			if (is_array($data) === false) {
				/**
				 * Ensure the file exists in the `WORKSPACE` directory
				 * @link http://symphony-cms.com/discuss/issues/view/610/
				 */
				$file = WORKSPACE . preg_replace(array('%/+%', '%(^|/)\.\./%'), '/', $data);

				if (file_exists($file) === false || !is_readable($file)) {
					$message = __('The file uploaded is no longer available. Please check that it exists, and is readable.');

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

				return self::__OK__;
			}

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

			if ($data['error'] != UPLOAD_ERR_NO_FILE && $data['error'] != UPLOAD_ERR_OK) {
				switch ($data['error']) {
					case UPLOAD_ERR_INI_SIZE:
						$message = __('File chosen in ‘%1$s’ exceeds the maximum allowed upload size of %2$s specified by your host.', array($this->get('label'), (is_numeric(ini_get('upload_max_filesize')) ? General::formatFilesize(ini_get('upload_max_filesize')) : ini_get('upload_max_filesize'))));
						break;

					case UPLOAD_ERR_FORM_SIZE:
						$message = __('File chosen in ‘%1$s’ exceeds the maximum allowed upload size of %2$s, specified by Symphony.', array($this->get('label'), General::formatFilesize($_POST['MAX_FILE_SIZE'])));
						break;

					case UPLOAD_ERR_PARTIAL:
					case UPLOAD_ERR_NO_TMP_DIR:
						$message = __('File chosen in ‘%s’ was only partially uploaded due to an error.', array($this->get('label')));
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

			// Sanitize the filename
			$data['name'] = Lang::createFilename($data['name']);

			if ($this->get('validator') != null) {
				$rule = $this->get('validator');

				if (!General::validateString($data['name'], $rule)) {
					$message = __('File chosen in ‘%s’ does not match allowable file types for that field.', array($this->get('label')));

					return self::__INVALID_FIELDS__;
				}
			}

			return self::__OK__;
		}

		private function __processRawFieldData($data, &$status, &$message=null, $simulate = false, $entry_id = null) {
			$status = self::__OK__;
			var_dump($data); die();
			/*
			array(1) { [0]=> string(0) "" }
			*/

			// No file given, save empty data:
			if ($data === null) {
				return array(
					'file' =>		null,
					'mimetype' =>	null,
					'size' =>		null,
					'meta' =>		null
				);
			}

			// Its not an array, so just retain the current data and return:
			if (is_array($data) === false) {
				// Ensure the file exists in the `WORKSPACE` directory
				// @link http://symphony-cms.com/discuss/issues/view/610/
				$file = WORKSPACE . preg_replace(array('%/+%', '%(^|/)\.\./%'), '/', $data);

				$result = array(
					'file' =>		$data,
					'mimetype' =>	null,
					'size' =>		null,
					'meta' =>		null
				);

				// Grab the existing entry data to preserve the MIME type and size information
				if (isset($entry_id)) {
					$row = Symphony::Database()->fetchRow(0, sprintf(
						"SELECT `file`, `mimetype`, `size`, `meta` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d",
						$this->get('id'),
						$entry_id
					));

					if (empty($row) === false) {
						$result = $row;
					}
				}

				// Found the file, add any missing meta information:
				if (file_exists($file) && is_readable($file)) {
					if (empty($result['mimetype'])) {
						$result['mimetype'] = (
							function_exists('mime_content_type')
								? mime_content_type($file)
								: 'application/octet-stream'
						);
					}

					if (empty($result['size'])) {
						$result['size'] = filesize($file);
					}

					if (empty($result['meta'])) {
						$result['meta'] = serialize(self::getMetaInfo($file, $result['mimetype']));
					}
				}

				// The file was not found, or is unreadable:
				else {
					$message = __('The file uploaded is no longer available. Please check that it exists, and is readable.');
					$status = self::__INVALID_FIELDS__;
				}

				return $result;
			}

			if ($simulate && is_null($entry_id)) return $data;

			// Check to see if the entry already has a file associated with it:
			if (is_null($entry_id) === false) {
				$row = Symphony::Database()->fetchRow(0, sprintf(
					"SELECT * FROM `tbl_entries_data_%s` WHERE `entry_id` = %d LIMIT 1",
					$this->get('id'),
					$entry_id
				));

				$existing_file = '/' . trim($row['file'], '/');

				// File was removed:
				if (
					$data['error'] == UPLOAD_ERR_NO_FILE
					&& !is_null($existing_file)
					&& is_file(WORKSPACE . $existing_file)
				) {
					General::deleteFile(WORKSPACE . $existing_file);
				}
			}

			// Do not continue on upload error:
			if ($data['error'] == UPLOAD_ERR_NO_FILE || $data['error'] != UPLOAD_ERR_OK) {
				return false;
			}

			// Where to upload the new file?
			$abs_path = DOCROOT . '/' . trim($this->get('destination'), '/');
			$rel_path = str_replace('/workspace', '', $this->get('destination'));

			// If a file already exists, then rename the file being uploaded by
			// adding `_1` to the filename. If `_1` already exists, the logic
			// will keep adding 1 until a filename is available (#672)
			if (file_exists($abs_path . '/' . $data['name'])) {
				$extension = General::getExtension($data['name']);
				$new_file = substr($abs_path . '/' . $data['name'], 0, -1 - strlen($extension));
				$renamed_file = $new_file;
				$count = 1;

				do {
					$renamed_file = $new_file . '_' . $count . '.' . $extension;
					$count++;
				} while (file_exists($renamed_file));

				// Extract the name filename from `$renamed_file`.
				$data['name'] = str_replace($abs_path . '/', '', $renamed_file);
			}

			// Sanitize the filename
			$data['name'] = Lang::createFilename($data['name']);
			$file = rtrim($rel_path, '/') . '/' . trim($data['name'], '/');

			// Attempt to upload the file:
			$uploaded = General::uploadFile(
				$abs_path, $data['name'], $data['tmp_name'],
				Symphony::Configuration()->get('write_mode', 'file')
			);

			if ($uploaded === false) {
				$message = __(
					'There was an error while trying to upload the file %1$s to the target directory %2$s.',
					array(
						'<code>' . $data['name'] . '</code>',
						'<code>workspace/' . ltrim($rel_path, '/') . '</code>'
					)
				);
				$status = self::__ERROR_CUSTOM__;

				return false;
			}

			// File has been replaced:
			if (
				isset($existing_file)
				&& $existing_file !== $file
				&& is_file(WORKSPACE . $existing_file)
			) {
				General::deleteFile(WORKSPACE . $existing_file);
			}

			// If browser doesn't send MIME type (e.g. .flv in Safari)
			if (strlen(trim($data['type'])) == 0) {
				$data['type'] = (
					function_exists('mime_content_type')
						? mime_content_type(WORKSPACE . $file)
						: 'application/octet-stream'
				);
			}

			return array(
				'file' =>		$file,
				'size' =>		$data['size'],
				'mimetype' =>	$data['type'],
				'meta' =>		serialize(self::getMetaInfo(WORKSPACE . $file, $data['type']))
			);
		}



		public function checkPostFieldData($data, &$message, $entry_id = NULL) {
			for($ii = 0; $ii < count($data); $ii++){
				if (is_array($data[$ii]) and isset($data[$ii]['name'])) 
					$data[$ii]['name'] = self::getUniqueFilename($data[$ii]['name']);
					
				$return = $this->__checkPostFieldData($data[$ii], $message, $entry_id);
				if($return != self::__OK__){
					return $return;
				}
			}
			return self::__OK__;
		}

		public function processRawFieldData($data, &$status, &$message = NULL, $simulate = false, $entry_id = NULL) {
			//if (is_array($data) and isset($data['name'])) $data['name'] = self::getUniqueFilename($data['name']);

			/*
			array(1) { [0]=> string(0) "" }
			*/
			
			
			$result = array(
				'file' => array(),
				'size' =>  array(),
				'mimetype' => array(),
				'meta' => array()
			);
			
			for($ii = 0; $ii < count($data); $ii++){
				if (is_array($data[$ii]) and isset($data[$ii]['name'])) 
					$data[$ii]['name'] = self::getUniqueFilename($data[$ii]['name']);
					
				$r = $this->__processRawFieldData($data[$ii], $status, $message, $simulate, $entry_id);
				
				if($return === false){
					return false;
				}
				
				foreach($r as $k => $v){
					$result[$k][] = $v;
				}
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
