<?php

	class extension_multiplefileuploadfield extends Extension {

		public function about() {
			return array(
				'name'			=> 'Field: Multiple File Upload',
				'version'		=> '1.0',
				'release-date'	=> '2013-05-17',
				'author'		=> array(
					'name'			=> 'Alistair Kearney',
					'website'		=> 'http://www.alistairkearney.com',
					'email'			=> 'hi@alistairkearney.com'
				),
				'description'	=> 'Select and upload multiple files at once'
			);
		}

		public function uninstall() {
			Symphony::Database()->query("DROP TABLE `tbl_fields_multiplefileupload`");
		}

		public function install() {
			return Symphony::Database()->query(
				"CREATE TABLE `tbl_fields_multiplefileupload` (
				 `id` int(11) unsigned NOT NULL auto_increment,
				 `field_id` int(11) unsigned NOT NULL,
				 `destination` varchar(255) NOT NULL,
				 `validator` varchar(50),
				  PRIMARY KEY (`id`),
				  KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
			);
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'InitialiseAdminPageHead',
					'callback'	=> 'addAssetsToPageHead'
				),
			);
		}
		
		public function addAssetsToPageHead($context){
			$page = Administration::instance()->Page->getContext();
		
			if(!isset($page['section_handle']) || !isset($page['page']) || !in_array($page['page'], array('new', 'edit'))){
				return;
			}
			
			Administration::instance()->Page->addStylesheetToHead(
				URL . '/extensions/multiplefileuploadfield/assets/styles.css', 'screen', 300
			);
			
			Administration::instance()->Page->addScriptToHead(
				URL . '/extensions/multiplefileuploadfield/assets/scripts.js', 300
			);
		}

	}
