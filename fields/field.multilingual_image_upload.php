<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(EXTENSIONS.'/image_upload/fields/field.image_upload.php');
	require_once(EXTENSIONS.'/frontend_localisation/extension.driver.php');
	require_once(EXTENSIONS.'/frontend_localisation/lib/class.FLang.php');

	final class fieldMultilingual_image_upload extends fieldImage_upload
	{

		/*------------------------------------------------------------------------------------------------*/
		/*  Definition  */
		/*------------------------------------------------------------------------------------------------*/

		private $currentLc = null;

		public function __construct(){
			parent::__construct();

			$this->_name = __('Multilingual Image Upload');
		}

		public static function generateTableColumns()
		{
			$cols = array();
			foreach (FLang::getLangs() as $lc) {
				$cols[] = "`file-{$lc}` varchar(255) default NULL,";
				$cols[] = "`size-{$lc}` int(11) unsigned NULL,";
				$cols[] = "`mimetype-{$lc}` varchar(50) default NULL,";
				$cols[] = "`meta-{$lc}` varchar(255) default NULL,";
			}
			return $cols;
		}

		public static function generateTableKeys()
		{
			$keys = array();
			foreach (FLang::getLangs() as $lc) {
				$keys[] = "KEY `file-{$lc}` (`file-{$lc}`),";
			}
			return $keys;
		}

		public function createTable()
		{
			$query = "
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$this->get('id')}` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`entry_id` int(11) unsigned NOT NULL,
					`file` varchar(255) default NULL,
					`size` int(11) unsigned NULL,
					`mimetype` varchar(50) default NULL,
					`meta` varchar(255) default NULL,";

			$query .= implode('', self::generateTableColumns());

			$query .= "
					PRIMARY KEY (`id`),
					UNIQUE KEY `entry_id` (`entry_id`)
			";
			
			$query .= implode('', self::generateTableKeys());
			
			$query .= "
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			return Symphony::Database()->query($query);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Settings  */
		/*------------------------------------------------------------------------------------------------*/

		public function set($field, $value)
		{
			if ($field == 'required_languages' && !is_array($value)) {
				$value = array_filter(explode(',', $value));
			}

			$this->_settings[$field] = $value;
		}

		public function get($field = null)
		{
			if ($field == 'required_languages') {
				return (array) parent::get($field);
			}

			return parent::get($field);
		}

		public function findDefaults(array &$settings)
		{
			$settings['default_main_lang'] = 'no';
			return parent::findDefaults($settings);
		}

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
		{
			Extension_Multilingual_Image_Upload::appendAssets(Extension_Multilingual_Image_Upload::SETTINGS_HEADERS);

			parent::displaySettingsPanel($wrapper, $errors);

			$required_pos = $wrapper->getNumberOfChildren() - 3;
			$wrapper->removeChildAt($required_pos);

			$fieldset = new XMLElement('fieldset');

			$div = new XMLElement('div', null, array('class' => 'two columns'));

			$this->appendDefLangValCheckbox($div);
			$this->appendShowColumnCheckbox($div);
			$fieldset->appendChild($div);

			$div = new XMLElement('div', null, array('class' => 'two columns'));
			$this->appendRequiredLanguages($div);
			$fieldset->appendChild($div);

			$wrapper->appendChild($fieldset);
		}

		private function appendDefLangValCheckbox(XMLElement &$wrapper)
		{
			$label = Widget::Label(null, null, 'column');
			$input = Widget::Input("fields[{$this->get('sortorder')}][default_main_lang]", 'yes', 'checkbox');
			if ($this->get('default_main_lang') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}
			$label->setValue(__('%s Use value from main language if selected language has empty value.', array($input->generate())));

			$wrapper->appendChild($label);
		}

		protected function appendRequiredLanguages(XMLElement &$wrapper)
		{
			$name = "fields[{$this->get('sortorder')}][required_languages][]";

			$required_languages = $this->get('required_languages');

			$displayed_languages = FLang::getLangs();

			if (($key = array_search(FLang::getMainLang(), $displayed_languages)) !== false) {
				unset($displayed_languages[$key]);
			}

			$options = Extension_Languages::findOptions($required_languages, $displayed_languages);

			array_unshift(
				$options,
				array('all', $this->get('required') == 'yes', __('All')),
				array('main', in_array('main', $required_languages), __('Main language'))
			);

			$label = Widget::Label(__('Required languages'));
			$label->setAttribute('class', 'column');
			$label->appendChild(
				Widget::Select($name, $options, array('multiple' => 'multiple'))
			);

			$wrapper->appendChild($label);
		}

		public function commit()
		{
			$required_languages = $this->get('required_languages');

			// all are required
			if (in_array('all', $required_languages)) {
				$this->set('required', 'yes');
				$required_languages = array('all');
			}
			else {
				$this->set('required', 'no');
			}

			// if main is required, remove the actual language code
			if (in_array('main', $required_languages)) {
				if (($key = array_search(FLang::getMainLang(), $required_languages)) !== false) {
					unset($required_languages[$key]);
				}
			}

			$this->set('required_languages', $required_languages);

			if (!parent::commit()) {
				return false;
			}

			return Symphony::Database()->query(sprintf("
				UPDATE
					`tbl_fields_%s`
				SET
					`default_main_lang` = '%s',
					`required_languages` = '%s'
				WHERE
					`field_id` = '%s';",
				$this->handle(),
				$this->get('default_main_lang'),
				implode(',', $this->get('required_languages')),
				$this->get('id')
			));
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Publish  */
		/*------------------------------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null)
		{
			Extension_Frontend_Localisation::appendAssets();
			Extension_Multilingual_Image_Upload::appendAssets(Extension_Multilingual_Image_Upload::PUBLISH_HEADERS);

			$main_lang = FLang::getMainLang();
			$all_langs = FLang::getAllLangs();
			$langs = FLang::getLangs();

			$wrapper->setAttribute('class', $wrapper->getAttribute('class').' field-multilingual');
			$container = new XMLElement('div', null, array('class' => 'container'));


			/*------------------------------------------------------------------------------------------------*/
			/*  Label  */
			/*------------------------------------------------------------------------------------------------*/

			$label = Widget::Label($this->get('label'), null, 'file');
			$labeliValue = $this->generateHelpMessage();
			$required_languages = $this->getRequiredLanguages();
			$title = '';
			$optional = '';
			$required = in_array('all', $required_languages) || count($langs) == count($required_languages);
			if (!$required) {
				if (empty($required_languages)) {
					$optional .= __('All languages are optional');
				} else {
					$optional_langs = array();
					foreach ($langs as $lang) {
						if (!in_array($lang, $required_languages)) {
							$optional_langs[] = $all_langs[$lang];
						}
					}
					
					foreach ($optional_langs as $idx => $lang) {
						$optional .= ' ' . __($lang);
						if ($idx < count($optional_langs) - 2) {
							$optional .= ',';
						} else if ($idx < count($optional_langs) - 1) {
							$optional .= ' ' . __('and');
						}
					}
					if (count($optional_langs) > 1) {
						$optional .= __(' are optional');
					} else {
						$optional .= __(' is optional');
					}
				}
				if ($this->get('default_main_lang') == 'yes') {
					$title .= __('Empty values defaults to %s', array($all_langs[$main_lang]));
				}
			}

			$label->appendChild(new XMLElement('i', $labeliValue . $optional, array(
				'title' => $title
			)));
			$container->appendChild($label);


			/*------------------------------------------------------------------------------------------------*/
			/*  Tabs  */
			/*------------------------------------------------------------------------------------------------*/

			$ul = new XMLElement('ul', null, array('class' => 'tabs'));
			foreach ($langs as $lc) {
				$li = new XMLElement('li', $all_langs[$lc], array('class' => $lc));
				$lc === $main_lang ? $ul->prependChild($li) : $ul->appendChild($li);
			}

			$container->appendChild($ul);


			/*------------------------------------------------------------------------------------------------*/
			/*  Panels  */
			/*------------------------------------------------------------------------------------------------*/

			foreach ($langs as $lc) {
				$div = new XMLElement('div', null, array('class' => 'file tab-panel tab-'.$lc));

				$file = 'file-'.$lc;

				if ($data[$file]) {
					$filePath = $this->get('destination').'/'.$data[$file];
					
					$div->appendChild(
						Widget::Anchor($filePath, URL.$filePath)
					);
				}

				$div->appendChild(
					Widget::Input(
						"fields{$fieldnamePrefix}[{$this->get('element_name')}][{$lc}]{$fieldnamePostfix}",
						$data[$file],
						$data[$file] ? 'hidden' : 'file'
					)
				);

				$container->appendChild($div);
			}


			/*------------------------------------------------------------------------------------------------*/
			/*  Errors  */
			/*------------------------------------------------------------------------------------------------*/

			if (!@is_dir(DOCROOT.$this->get('destination').'/')) {
				$flagWithError = __('The destination directory, <code>%s</code>, does not exist.', array($this->get('destination')));
			}
			else if (!$flagWithError && !is_writable(DOCROOT.$this->get('destination').'/')) {
				$flagWithError = __('Destination folder, <code>%s</code>, is not writable. Please check permissions.', array($this->get('destination')));
			}

			if ($flagWithError != null) {
				$wrapper->appendChild(Widget::Error($container, $flagWithError));
			}
			else {
				$wrapper->appendChild($container);
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Input  */
		/*------------------------------------------------------------------------------------------------*/

		public function checkPostFieldData($data, &$message, $entry_id = null)
		{
			$error = self::__OK__;
			$field_data = $data;
			$all_langs = FLang::getAllLangs();
			$required_languages = $this->getRequiredLanguages();
			$original_required  = $this->get('required');

			foreach (FLang::getLangs() as $lc) {
				$this->currentLc = $lc;
				$this->set('required', in_array($lc, $required_languages) ? 'yes' : 'no');

				$file_message = '';
				$data = $this->_getData($field_data[$lc]);

				$status = parent::checkPostFieldData($data, $file_message, $entry_id);

				// if one language fails, all fail
				if ($status != self::__OK__) {
					$local_msg = "<br />[$lc] {$all_langs[$lc]}: {$file_message}";

					if ($lc === $main_lang) {
						$message = $local_msg . $message;
					}
					else {
						$message = $message . $local_msg;
					}

					$error = self::__ERROR__;
				}
			}

			$this->set('required', $original_required);
			$this->currentLc = null;

			return $error;
		}

		public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null)
		{
			if (!is_array($data) || empty($data)) {
				return parent::processRawFieldData($data, $status, $message, $simulate, $entry_id);
			}

			$status = self::__OK__;
			$result = array();
			$field_data = $data;
			$main_lang = FLang::getMainLang();
			$missing_langs = array();

			foreach (FLang::getLangs() as $lc) {
				$this->currentLc = $lc;
				if (!isset($field_data[$lc])) {
					$missing_langs[] = $lc;
					continue;
				}

				$data = $this->_getData($field_data[$lc]);

				// Make this language the default for now
				// parent::processRawFieldData needs this.
				if ($entry_id) {
					Symphony::Database()->query(sprintf(
						"UPDATE `tbl_entries_data_%d`
							SET
							`file` = `file-$lc`,
							`mimetype` = `mimetype-$lc`,
							`size` = `size-$lc`,
							`meta` = `meta-$lc`
							WHERE `entry_id` = %d",
						$this->get('id'),
						$entry_id
					));
				}

				$local_status = self::__OK__;
				$local_messsage = '';
				$file_result = parent::processRawFieldData($data, $local_status, $local_messsage, $simulate, $entry_id);
				if ($local_status != self::__OK__) {
					$message .= $local_messsage;
					$status = $local_status;
				}

				if (is_array($file_result)) {
					foreach ($file_result as $key => $value) {
						$result[$key.'-'.$lc] = $value;
					}
				}
			}

			$this->currentLc = null;

			if (!empty($missing_langs) && $entry_id) {
				$crt_data = $this->getCurrentData($entry_id);

				foreach ($missing_langs as $lc) {
					$result["file-$lc"]     = $crt_data["file-$lc"];
					$result["size-$lc"]     = $crt_data["size-$lc"];
					$result["meta-$lc"]     = $crt_data["meta-$lc"];
					$result["mimetype-$lc"] = $crt_data["mimetype-$lc"];
				}
			}

			// Update main lang
			$result['file']     = $result["file-$main_lang"];
			$result['size']     = $result["size-$main_lang"];
			$result['meta']     = $result["meta-$main_lang"];
			$result['mimetype'] = $result["mimetype-$main_lang"];

			return $result;
		}

		protected function getCurrentData($entry_id) {
			$query = sprintf(
				'SELECT * FROM `tbl_entries_data_%d`
				WHERE `entry_id` = %d',
				$this->get('id'),
				$entry_id
			);

			return Symphony::Database()->fetchRow(0, $query);
		}

		/*------------------------------------------------------------------------------------------------*/
		/*  Output  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null){
			$data = $this->localizeValues($data);
			parent::appendFormattedElement($wrapper, $data);
		}

		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null) {
			if ($link) {
				$link->setAttribute('style', 'border-bottom: none !important;');
			}
			if (is_array($data)) {
				$data = $this->localizeValues($data);
			}
			return parent::prepareTableValue($data, $link, $entry_id);
		}

		public function prepareTextValue($data, $entry_id = null) {
			if (!is_array($data)) {
				return null;
			}
			$data = $this->localizeValues($data);
			return $data['file'];
		}

		public function getExampleFormMarkup(){
			$label = Widget::Label($this->get('label').'
		<!-- '.__('Modify just current language value').' -->
		<input name="fields['.$this->get('element_name').'][{$url-fl-language}]" type="file" />

		<!-- '.__('Modify all values').' -->');

			foreach( FLang::getLangs() as $lc) {
				$label->appendChild(Widget::Input("fields[{$this->get('element_name')}][{$lc}]", null, 'file'));
			}

			return $label;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Utilities  */
		/*------------------------------------------------------------------------------------------------*/

		/**
		 * Returns required languages for this field.
		 */
		public function getRequiredLanguages()
		{
			$required = $this->get('required_languages');

			$languages = FLang::getLangs();

			if (in_array('all', $required)) {
				return $languages;
			}

			if (($key = array_search('main', $required)) !== false) {
				unset($required[$key]);

				$required[] = FLang::getMainLang();
				$required   = array_unique($required);
			}

			return $required;
		}

		protected function getLang($data = null)
		{
			$required_languages = $this->getRequiredLanguages();
			// Get Lang from Frontend Localisation
			$lc = FLang::getLangCode();

			if (!FLang::validateLangCode($lc)) {
				// Revert to backend language
				$lc = Lang::get();
			}

			// If value is empty for this language, load value from main language
			if (is_array($data) && $this->get('default_main_lang') == 'yes') {
				// If value is empty
				if (empty($data["file-$lc"])) {
					$lc = FLang::getMainLang();
				}
				// If value if still empty try to use the value from the first
				// required language
				if (empty($data["file-$lc"]) && count($required_languages) > 0) {
					$lc = $required_languages[0];
				}
			}
			return $lc;
		}

		public function localizeValues(array $data) {
			$lang_code = $this->getLang($data);
			$data['file'] = $data["file-$lang_code"];
			$data['size'] = $data["size-$lang_code"];
			$data['meta'] = $data["meta-$lang_code"];
			$data['mimetype'] = $data["mimetype-$lang_code"];
			return $data;
		}

		public function entryDataCleanup($entry_id, $data = null)
		{
			foreach (FLang::getLangs() as $lc) {
				$file_location = WORKSPACE.'/'.ltrim($data['file-'.$lc], '/');

				if (is_file($file_location)) {
					General::deleteFile($file_location);
				}
			}

			parent::entryDataCleanup($entry_id, $data);

			return true;
		}

		public function getParameterPoolValue(array $data, $entry_id = null) {
			$lc = $this->getLang();
			return $data["file-$lc"];
		}

		/*------------------------------------------------------------------------------------------------*/
		/*  In-house  */
		/*------------------------------------------------------------------------------------------------*/

		protected function getUniqueFilename($filename)
		{
			if (empty($filename)) {
				return $filename;
			}
			
			if (!$this->currentLc) {
				throw new Exception('No current language set!');
			}
			
			$unique = $this->get('unique') == 'yes';
			$lang_code = $this->currentLc;
			
			return preg_replace_callback('/(.*)(\.[^\.]+)/', function ($matches) use ($lang_code, $unique) {
				if ($unique) {
					$lang_code .= '-' . time();
				}
				return substr($matches[1], 0, 150) . '-' . $lang_code . $matches[2];
			}, $filename);
		}


		/**
		 * It is possible that data from Symphony won't come as expected associative array.
		 *
		 * @param array $data
		 */
		private function _getData($data)
		{
			if (is_string($data)) {
				return $data;
			}

			if (!is_array($data)) {
				return null;
			}

			if (array_key_exists('name', $data)) {
				return $data;
			}
			
			return array(
				'name' => $data[0],
				'type' => $data[1],
				'tmp_name' => $data[2],
				'error' => $data[3],
				'size' => $data[4]
			);
		}


		/*------------------------------------------------------------------------------------------------*/
		/*  Field schema  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFieldSchema(XMLElement $f)
		{
			$required_languages = $this->getRequiredLanguages();
			$required = new XMLElement('required-languages');

			foreach ($required_languages as $lc) {
				$required->appendChild(new XMLElement('item', $lc));
			}

			$f->appendChild($required);
		}

	}
