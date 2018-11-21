<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	define_safe('MIU_NAME', 'Multilingual Image Upload');
	define_safe('MIU_GROUP', 'multilingual_image_upload');

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
			return Symphony::Database()
				->create(self::FIELD_TABLE)
				->ifNotExists()
				->charset('utf8')
				->collate('utf8_unicode_ci')
				->fields([
					'id' => [
						'type' => 'int(11)',
						'auto' => true,
					],
					'field_id' => 'int(11)',
					'destination' => 'varchar(255)',
					'validator' => 'varchar(50)',
					'unique' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
					'default_main_lang' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'no',
					],
					'required_languages' => [
						'type' => 'varchar(255)',
						'null' => true,
					],
					'min_width' => 'int(11)',
					'min_height' => 'int(11)',
					'max_width' => 'int(11)',
					'max_height' => 'int(11)',
					'resize' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'yes',
					],
				])
				->keys([
					'id' => 'primary',
					'field_id' => 'key',
				])
				->execute()
				->success();
		}

		public function update($previousVersion = false)
		{
			// Before 1.3
			if (version_compare($previousVersion, '1.3', '<')) {
				Symphony::Database()
					->alter(self::FIELD_TABLE)
					->add([
						'def_ref_lang' => [
							'type' => 'enum',
							'values' => ['yes','no'],
							'default' => 'no',
						],
					])
					->execute()
					->success();

				Symphony::Database()
					->update(self::FIELD_TABLE)
					->set([
						'def_ref_lang' => 'no',
					])
					->execute()
					->success();
			}

			// Before 1.7
			if (version_compare($previousVersion, '1.7', '<')) {
				// get all langs
				$cols = array();
				foreach(FLang::getLangs() as $lc) {
					$cols['file-' . $lc] = substring_index('file-' . $lc, '/', -1);
				}

				// Remove directory from the upload fields, #1719
				$upload_tables = Symphony::Database()
					->select('field_id')
					->from(self::FIELD_TABLE)
					->execute()
					->column('field_id');

				if (is_array($upload_tables) && !empty($upload_tables)) {
					foreach($upload_tables as $field) {
						Symphony::Database()
							->update('tbl_entries_data_' . $field)
							->set(array_merge([
								'file' => substring_index('file', '/', -1)
							], $cols))
							->execute()
							->success();
					}
				}
			}

			// Before 1.7.1
			if (version_compare($previousVersion, '1.7.1', '<')) {
				try {
					Symphony::Database()
						->alter(self::FIELD_TABLE)
						->add([
							'resize' => [
								'type' => 'enum',
								'values' => ['yes','no'],
								'default' => 'yes',
							],
						])
						->execute()
						->success();
				}
				catch (Exception $e) {
					// ignore
				}
			}

			if (version_compare($previousVersion, '2.0.0', '<')) {
				Symphony::Database()
					->alter(self::FIELD_TABLE)
					->change(['def_ref_lang' => [
						'default_main_lang' => [
							'type' => 'enum',
							'values' => ['yes', 'no'],
							'default' => 'no',
						],
					]])
					->add([
						'required_languages' => [
							'type' => 'varchar(255)',
							'null' => true,
						],
					])
					->execute()
					->success();
			}

			return true;
		}

		public function uninstall()
		{
			return Symphony::Database()
				->drop(self::FIELD_TABLE)
				->ifExists()
				->execute()
				->success();
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
			$label->prependChild(Widget::Input('settings['.MIU_GROUP.'][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));
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
						$show_columns = Symphony::Database()
							->showColumns()
							->from($entries_table)
							->like('file-%%')
							->execute()
							->rows();
					}
					catch( DatabaseException $dbe) {
						// Field doesn't exist. Better remove it's settings
						Symphony::Database()
							->delete(self::FIELD_TABLE)
							->where(['field_id' => $field['field_id']])
							->execute()
							->success();

						continue;
					}

					$columns = array();

					// Remove obsolete fields
					if (is_array($show_columns) && !empty($show_columns))

						foreach ($show_columns as $column) {
							$lc = substr($column['Field'], strlen($column['Field']) - 2);

							// If not consolidate option AND column lang_code not in supported languages codes -> Drop Column
							if (($consolidate !== 'yes') && !in_array($lc, $context['new_langs']))
								Symphony::Database()
									->alter($entries_table)
									->drop([
										'file-' . $lc,
										'size-' . $lc,
										'mimetype-' . $lc,
										'meta-' . $lc,
									])
									->execute()
									->success();
							else
								$columns[] = $column['Field'];
						}

					// Add new fields
					foreach ($context['new_langs'] as $lc) {
						if (!in_array('file-'.$lc, $columns)) {
							Symphony::Database()
								->alter($entries_table)
								->add([
									'file-' . $lc => [
										'type' => 'varchar(255)',
										'null' => true,
									],
									'size-' . $lc => [
										'type' => 'int(11)',
										'null' => true,
									],
									'mimetype-' . $lc => [
										'type' => 'varchar(50)',
										'null' => true,
									],
									'meta-' . $lc => [
										'type' => 'varchar(255)',
										'null' => true,
									],
								])
								->execute()
								->success();
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
