<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	define_safe(MIU_NAME, 'Multilingual Image Upload');
	define_safe(MIU_GROUP, 'multilingual_image_upload');

	class Extension_Multilingual_Image_Upload extends Extension
	{
		const FIELD_TABLE = 'tbl_fields_multilingual_image_upload';

		protected static $appendedHeaders = 0;
		
		const PUBLISH_HEADERS = 1;
		const SETTINGS_HEADERS = 4;

		/*------------------------------------------------------------------------------------------------*/
		/*  Installation  */
		/*------------------------------------------------------------------------------------------------*/

		public function install()
		{
			return Symphony::Database()->query(sprintf(
				"CREATE TABLE `%s` (
					`id` INT(11) unsigned NOT NULL auto_increment,
					`field_id` INT(11) unsigned NOT NULL,
					`destination` VARCHAR(255) NOT NULL,
					`validator` VARCHAR(50),
					`unique` enum('yes','no') NOT NULL DEFAULT 'yes',
					`default_main_lang` ENUM('yes', 'no') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'no',
					`required_languages` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
					`min_width` INT(11) unsigned,
					`min_height` INT(11) unsigned,
					`max_width` INT(11) unsigned,
					`max_height` INT(11) unsigned,
					`resize` enum('yes','no') NOT NULL DEFAULT 'yes',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;",
				self::FIELD_TABLE
			));
		}

		public function update($previousVersion = false)
		{
			// Before 1.3
			if (version_compare($previousVersion, '1.3', '<')) {
				Symphony::Database()->query(sprintf(
					"ALTER TABLE `%s` ADD COLUMN `def_ref_lang` ENUM('yes','no') DEFAULT 'no'",
					self::FIELD_TABLE
				));

				Symphony::Database()->query(sprintf(
					"UPDATE `%s` SET `def_ref_lang` = 'no'",
					self::FIELD_TABLE
				));
			}
			
			// Before 1.7
			if (version_compare($previousVersion, '1.7', '<')) {
				// get all langs
				$cols = '';
				foreach(FLang::getLangs() as $lc) {
					$cols .= sprintf(', `file-%1$s` = substring_index(`file-%1$s`, \'/\', -1)', $lc);
				}
				
				// Remove directory from the upload fields, #1719
				$upload_tables = Symphony::Database()->fetchCol("field_id", sprintf("SELECT `field_id` FROM `%s`", self::FIELD_TABLE));

				if (is_array($upload_tables) && !empty($upload_tables)) {
					foreach($upload_tables as $field) {
						Symphony::Database()->query(sprintf(
							"UPDATE tbl_entries_data_%d SET 
								`file` = substring_index(file, '/', -1)%s",
							$field, $cols
						));
					}
				}
			}

			// Before 1.7.1
			if (version_compare($previousVersion, '1.7.1', '<')) {
				$query = sprintf("ALTER TABLE `%s`
								ADD COLUMN `resize` enum('yes','no') NOT NULL DEFAULT 'yes'
							", self::FIELD_TABLE);
				try {
					$ret = Symphony::Database()->query($query);
				}
				catch (Exception $e) {
					// ignore
				}
			}

			if (version_compare($previousVersion, '2.0.0', '<')) {
				Symphony::Database()->query(sprintf(
					"ALTER TABLE `%s`
						CHANGE COLUMN `def_ref_lang` `default_main_lang` ENUM('yes', 'no') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'no',
						ADD `required_languages` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL;",
					self::FIELD_TABLE
				));
			}

			return true;
		}

		public function uninstall()
		{
			return Symphony::Database()->query(sprintf(
				"DROP TABLE IF EXISTS `%s`",
				self::FIELD_TABLE
			));
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Delegates  */
		/*------------------------------------------------------------------------------------------------*/

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'dAddCustomPreferenceFieldsets'
				),
				array(
					'page'     => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => 'dSave'
				),
				array(
					'page' => '/extensions/frontend_localisation/',
					'delegate' => 'FLSavePreferences',
					'callback' => 'dFLSavePreferences'
				),
			);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  System preferences  */
		/*------------------------------------------------------------------------------------------------*/

		/**
		 * Display options on Preferences page.
		 *
		 * @param array $context
		 */
		public function dAddCustomPreferenceFieldsets($context){
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', MIU_NAME));

			$label = Widget::Label(__('Consolidate entry data'));
			$label->appendChild(Widget::Input('settings['.MIU_GROUP.'][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', __('Check this field if you want to consolidate database by <b>keeping</b> entry values of removed/old Language Driver language codes. Entry values of current language codes will not be affected.'), array('class' => 'help')));

			$context['wrapper']->appendChild($group);
		}

		/**
		 * Edits the preferences to be saved
		 *
		 * @param array $context
		 */
		public function dSave($context) {
			// prevent the saving of the values
			unset($context['settings'][MIU_GROUP]);
		}

		/**
		 * Save options from Preferences page
		 *
		 * @param array $context
		 */
		public function dFLSavePreferences($context)
		{
			$fields = Symphony::Database()->fetch(sprintf(
				'SELECT `field_id` FROM `%s`',
				self::FIELD_TABLE
			));

			if (is_array($fields) && !empty($fields)){
				$consolidate = $context['context']['settings'][MIU_GROUP]['consolidate'];

				// Foreach field check multilanguage values foreach language
				foreach ($fields as $field) {
					$entries_table = 'tbl_entries_data_'.$field["field_id"];

					try{
						$show_columns = Symphony::Database()->fetch(sprintf(
							"SHOW COLUMNS FROM `%s` LIKE 'file-%%'",
							$entries_table
						));
					}
					catch( DatabaseException $dbe) {
						// Field doesn't exist. Better remove it's settings
						Symphony::Database()->query(sprintf(
							"DELETE FROM `%s` WHERE `field_id` = '%s';",
							self::FIELD_TABLE, $field["field_id"]
						));
						continue;
					}

					$columns = array();

					// Remove obsolete fields
					if (is_array($show_columns) && !empty($show_columns))

						foreach ($show_columns as $column) {
							$lc = substr($column['Field'], strlen($column['Field']) - 2);

							// If not consolidate option AND column lang_code not in supported languages codes -> Drop Column
							if (($consolidate !== 'yes') && !in_array($lc, $context['new_langs']))
								Symphony::Database()->query(sprintf(
									'ALTER TABLE `%1$s`
										DROP COLUMN `file-%2$s`,
										DROP COLUMN `size-%2$s`,
										DROP COLUMN `mimetype-%2$s`,
										DROP COLUMN `meta-%2$s`;',
									$entries_table, $lc
								));
							else
								$columns[] = $column['Field'];
						}

					// Add new fields
					foreach ($context['new_langs'] as $lc) {
						if (!in_array('file-'.$lc, $columns)) {
							Symphony::Database()->query(sprintf(
								'ALTER TABLE `%1$s`
									ADD COLUMN `file-%2$s` varchar(255) default NULL,
									ADD COLUMN `size-%2$s` int(11) unsigned NULL,
									ADD COLUMN `mimetype-%2$s` varchar(50) default NULL,
									ADD COLUMN `meta-%2$s` varchar(255) default NULL;',
								$entries_table, $lc
							));
						}
					}
				}
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Public utilities  */
		/*------------------------------------------------------------------------------------------------*/

		public static function appendAssets($type)
		{
			
			if ((self::$appendedHeaders & $type) !== $type
				&& class_exists('Administration')
				&& Administration::instance() instanceof Administration
				&& Administration::instance()->Page instanceof HTMLPage) {

				$page = Administration::instance()->Page;

				if ($type === self::PUBLISH_HEADERS) {
					$page->addScriptToHead(URL.'/extensions/'.MIU_GROUP.'/assets/'.MIU_GROUP.'.publish.js', null, false);
				}
				
				if ($type === self::SETTINGS_HEADERS) {
					$page->addScriptToHead(URL.'/extensions/'.MIU_GROUP.'/assets/'.MIU_GROUP.'.settings.js', null, false);
				}
				
				self::$appendedHeaders |= $type;
			}
			
		}
	}
