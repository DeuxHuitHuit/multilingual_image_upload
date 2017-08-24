<?php
	/*
	Copyright: Deux Huit Huit 2016
	LICENCE: MIT http://deuxhuithuit.mit-license.org;
	*/

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	require_once(TOOLKIT . '/class.jsonpage.php');
	require_once(EXTENSIONS . '/multilingual_image_upload/fields/field.multilingual_image_upload.php');

	class contentExtensionMultilingual_Image_UploadConvert extends JSONPage {

		/**
		 *
		 * Builds the content view
		 */
		public function view() {
			if ($_SERVER['REQUEST_METHOD'] != 'POST') {
				$this->_Result['status'] = Page::HTTP_STATUS_BAD_REQUEST;
				$this->_Result['error'] = 'This page accepts posts only';
				$this->setHttpStatus($this->_Result['status']);
				return;
			}

			if (!is_array($this->_context) || empty($this->_context)) {
				$this->_Result['error'] = 'Parameters not found';
				return;
			}

			$id = MySQL::cleanValue($this->_context[0]);
			$this->_Result['ok'] = true;

			$field = FieldManager::fetch($id);

			if ($field == null || !($field instanceof fieldImage_upload)) {
				$this->_Result['error'] = "Field $id not found.";
				$this->_Result['ok'] = false;
				return;
			}

			try {
				// Check for languages
				$langs = FLang::getLangs();
				if (empty($langs)) {
					throw new Exception('No language found. Please check that you have at least one.');
				}

				$destination = MySQL::cleanValue($field->get('destination'));
				$validator = MySQL::cleanValue($field->get('validator'));
				$unique = MySQL::cleanValue($field->get('unique'));
				$min_width = MySQL::cleanValue($field->get('min_width'));
				$min_height = MySQL::cleanValue($field->get('min_height'));
				$max_width = MySQL::cleanValue($field->get('max_width'));
				$max_height = MySQL::cleanValue($field->get('max_height'));
				$resize = MySQL::cleanValue($field->get('resize'));
				$requiredLang = $field->get('required') == 'yes' ? "'main'" : 'null';

				// ALTER data table SQL: add new cols
				$entries_table = "tbl_entries_data_$id";
				$query = "ALTER TABLE `$entries_table` ";
				$cols = fieldMultilingual_image_upload::generateTableColumns();
				foreach ($cols as $col) {
					$query .= ' ADD COLUMN ' . $col;
				}
				$keys = fieldMultilingual_image_upload::generateTableKeys();
				foreach ($keys as $key) {
					$query .= ' ADD ' . $key . ',';
				}
				$query = trim($query, ',') . ';';
				Symphony::Database()->query($query);

				// Copy values to default lang
				$defLang = FLang::getMainLang();
				$query = "UPDATE `$entries_table` SET ";
				$query .= " `file-$defLang` = `file`,
					`size-$defLang` = `size`,
					`mimetype-$defLang` = `mimetype`,
					`meta-$defLang` = `meta`;";

				Symphony::Database()->query($query);

				// Insert into multilingual
				Symphony::Database()->query("
					INSERT INTO `tbl_fields_multilingual_image_upload`
						(`field_id`, `destination`, `validator`, `unique`, `default_main_lang`,
						 `min_width`, `min_height`,
						 `max_width`, `max_height`,
						 `resize`, `required_languages`)
					VALUES
						($id, '$destination', '$validator', '$unique', 'yes',
						 '$min_width', '$min_height',
						 '$max_width', '$max_height',
						 '$resize', $requiredLang)
				");

				// remove from textbox
				Symphony::Database()->query("
					DELETE FROM `tbl_fields_textbox`
						WHERE `field_id` = $id
				");

				// update type
				Symphony::Database()->query("
					UPDATE `tbl_fields` SET `type` = 'multilingual_image_upload'
						WHERE `id` = $id
				");

			} catch (Exception $ex) {
				$this->_Result['ok'] = false;
				$this->_Result['error'] = $ex->getMessage();
			}
		}
	}
