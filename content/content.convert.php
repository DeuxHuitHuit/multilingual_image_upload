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

			$id = $this->_context[0];
			$this->_Result['ok'] = true;

			$field = (new FieldManager)
				->select()
				->field($id)
				->execute()
				->next();

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

				$destination = $field->get('destination');
				$validator = $field->get('validator');
				$unique = $field->get('unique');
				$min_width = $field->get('min_width');
				$min_height = $field->get('min_height');
				$max_width = $field->get('max_width');
				$max_height = $field->get('max_height');
				$resize = $field->get('resize');
				$requiredLang = $field->get('required') == 'yes' ? "'main'" : 'null';

				// ALTER data table SQL: add new cols
				$entries_table = 'tbl_entries_data_' . $id;
				$cols = fieldMultilingual_image_upload::generateTableColumns();
				$keys = fieldMultilingual_image_upload::generateTableKeys();
				Symphony::Database()
					->alter($entries_table)
					->add($cols)
					->addKey($keys)
					->execute()
					->success();

				// Copy values to default lang
				$defLang = FLang::getMainLang();
				Symphony::Database()
					->update($entries_table)
					->set([
						'file-' . $defLang => '$file',
						'size-' . $defLang => '$size',
						'mimetype-' . $defLang => '$mimetype',
						'meta-' . $defLang => '$meta',
					])
					->execute()
					->success();

				// Insert into multilingual
				Symphony::Database()
					->insert('tbl_fields_multilingual_image_upload')
					->values([
						'field_id' => $id,
						'destination' => $destination,
						'validator' => $validator,
						'unique' => $unique,
						'default_main_lang' => 'yes',
						 'min_width' => $min_width,
						 'min_height' => $min_height,
						 'max_width' => $max_width,
						 'max_height' => $max_height,
						 'resize' => $resize,
						 'required_languages' => $requiredLang,
					])
					->execute()
					->success();

				// remove from textbox
				Symphony::Database()
					->delete('tbl_fields_textbox')
					->where(['field_id' => $id])
					->execute()
					->success();

				// update type
				Symphony::Database()
					->update('tbl_fields')
					->set([
						'type' => 'multilingual_image_upload',
					])
					->where(['id' => $id])
					->execute()
					->success();

			} catch (Exception $ex) {
				$this->_Result['ok'] = false;
				$this->_Result['error'] = $ex->getMessage();
			}
		}
	}
