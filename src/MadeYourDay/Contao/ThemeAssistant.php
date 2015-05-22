<?php
/*
 * Copyright MADE/YOUR/DAY <mail@madeyourday.co>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\Contao;

/**
 * RockSolid Theme Assistant DCA
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Martin Auswöger <martin@madeyourday.co>
 */
class ThemeAssistant extends \Backend
{
	public function executePostActionsHook($action, $dc)
	{
		if (!$dc instanceof ThemeAssistantDataContainer) {
			return;
		}

		if ($action === 'loadFiletree') {

			// Backwards compatibility for Contao 3.2
			if (version_compare(VERSION, '3.3', '<')) {
				$arrData['strTable'] = $dc->table;
				$arrData['id'] = $dc->id;
				$arrData['name'] = \Input::post('name');
				$objWidget = new $GLOBALS['BE_FFL']['fileSelector']($arrData, $dc);
			}
			else {
				$strField = \Input::post('name');
				$strClass = $GLOBALS['BE_FFL']['fileSelector'];
				$objWidget = new $strClass($strClass::getAttributesFromDca($GLOBALS['TL_DCA'][$dc->table]['fields'][$strField], $strField, null, $strField, $dc->table, $dc));
			}

			// Load a particular node
			if (\Input::post('folder', true) != '')
			{
				echo $objWidget->generateAjax(\Input::post('folder', true), \Input::post('field'), intval(\Input::post('level')));
			}
			else
			{
				echo $objWidget->generate();
			}
			exit;

		}

		if ($action === 'reloadPagetree' || $action === 'reloadFiletree') {

			$intId = \Input::get('id');
			$strField = $dc->field = \Input::post('name');

			// Handle the keys in "edit multiple" mode
			if (\Input::get('act') == 'editAll')
			{
				$intId = preg_replace('/.*_([0-9a-zA-Z]+)$/', '$1', $strField);
				$strField = preg_replace('/(.*)_[0-9a-zA-Z]+$/', '$1', $strField);
			}

			// The field does not exist
			if (!isset($GLOBALS['TL_DCA'][$dc->table]['fields'][$strField]))
			{
				$this->log('Field "' . $strField . '" does not exist in DCA "' . $dc->table . '"', 'Ajax executePostActions()', TL_ERROR);
				header('HTTP/1.1 400 Bad Request');
				die('Bad Request');
			}

			$varValue = \Input::post('value', true);
			$strKey = ($action == 'reloadPagetree') ? 'pageTree' : 'fileTree';

			// Convert the selected values
			if ($varValue != '')
			{
				$varValue = trimsplit("\t", $varValue);

				// Automatically add resources to the DBAFS
				if ($strKey == 'fileTree')
				{
					foreach ($varValue as $k=>$v)
					{
						if (version_compare(VERSION, '3.2', '<')) {
							$varValue[$k] = \Dbafs::addResource($v)->id;
						}
						else {
							$varValue[$k] = \Dbafs::addResource($v)->uuid;
						}
					}
				}

				$varValue = serialize($varValue);
			}

			$dc->activeRecord = new \stdClass;
			$dc->activeRecord->id = $intId;
			$dc->activeRecord->$strField = $varValue;

			// Backwards compatibility for Contao 3.2
			if (version_compare(VERSION, '3.3', '<')) {

				// Build the attributes based on the "eval" array
				$arrAttribs = $GLOBALS['TL_DCA'][$dc->table]['fields'][$strField]['eval'];

				$arrAttribs['id'] = $dc->field;
				$arrAttribs['name'] = $dc->field;
				$arrAttribs['value'] = $varValue;
				$arrAttribs['strTable'] = $dc->table;
				$arrAttribs['strField'] = $strField;

				$objWidget = new $GLOBALS['BE_FFL'][$strKey]($arrAttribs);

			}
			else {
				$strClass = $GLOBALS['BE_FFL'][$strKey];
				$objWidget = new $strClass($strClass::getAttributesFromDca($GLOBALS['TL_DCA'][$dc->table]['fields'][$strField], $strField, $varValue, $strField, $dc->table, $dc));
			}

			echo $objWidget->generate();
			exit;

		}
	}

	public function onloadCallback(\DataContainer $dc)
	{
		if ($dc->id) {

			if (substr($dc->id, -5) === '.base') {

				$type = 'html';
				if (substr($dc->id, -9, 4) === '.css') {
					$type = 'css';
				}

				list($data, $template) = static::parseBaseFile(file_get_contents(TL_ROOT . '/' . $dc->id), $type);

				// Check if parsing the file was successful
				if (empty($data) || empty($data['fileHash']) || empty($data['templateVars']) || !trim($template)) {
					$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['fields']['file_invalid_error'] = array(
						'label' => array('', ''),
						'input_field_callback' => array('MadeYourDay\\Contao\\ThemeAssistant', 'fieldFileInvalidCallback'),
					);
					$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['palettes']['default'] .= 'file_invalid_error;';
					return;
				}

				if ($data['fileHash'] !== md5_file(TL_ROOT . '/' . substr($dc->id, 0, -5))) {
					$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['fields']['file_hash_warning'] = array(
						'label' => array('', ''),
						'input_field_callback' => array('MadeYourDay\\Contao\\ThemeAssistant', 'fieldFileHashCallback'),
					);
					$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['palettes']['default'] .= 'file_hash_warning;';
				}

				if (!static::isWriteable(TL_ROOT . '/' . substr($dc->id, 0, -5)) || !static::isWriteable(TL_ROOT . '/' . $dc->id)) {
					$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['fields']['file_writable_warning'] = array(
						'label' => array('', ''),
						'input_field_callback' => array('MadeYourDay\\Contao\\ThemeAssistant', 'fieldFileWritableCallback'),
					);
					$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['palettes']['default'] .= 'file_writable_warning;';
				}

				if (!empty($data['variations']) && (
					!empty($data['variations'][substr($GLOBALS['TL_LANGUAGE'], 0, 2)]) ||
					!empty($data['variations']['en'])
				)) {
					$variations = !empty($data['variations'][substr($GLOBALS['TL_LANGUAGE'], 0, 2)]) ? $data['variations'][substr($GLOBALS['TL_LANGUAGE'], 0, 2)] : $data['variations']['en'];
					$options = array('variation0' => $GLOBALS['TL_LANG']['rocksolid_theme_assistant']['variation_default']);
					foreach ($variations as $key => $variation) {
						$options['variation' . ($key + 1)] = $variation;
					}
					$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['fields']['variations'] = array(
						'label' => &$GLOBALS['TL_LANG']['rocksolid_theme_assistant']['variations'],
						'inputType' => 'select',
						'options' => $options,
						'eval' => array('includeBlankOption' => true),
						'wizard' => array(
							array('MadeYourDay\\Contao\\ThemeAssistant', 'fieldVariationsWizard'),
						),
					);
					$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['palettes']['default'] .= '{legend_variations:hide},variations;';
				}

				foreach ($data['templateVars'] as $key => $var) {

					if (isset($var[substr($GLOBALS['TL_LANGUAGE'], 0, 2)])) {
						$label = array(
							$var[substr($GLOBALS['TL_LANGUAGE'], 0, 2)]['label'],
							$var[substr($GLOBALS['TL_LANGUAGE'], 0, 2)]['description'],
						);
					}
					else {
						// Fallback to english language
						$label = array(
							$var['en']['label'],
							$var['en']['description'],
						);
					}

					// add default values to field descriptions
					$defaultValue = $var['defaultValues'][0];
					if ($var['type'] === 'boolean') {
						$defaultValue = '<input type="checkbox"' . ($defaultValue ? ' checked="checked"' : '') . ' disabled="disabled" />';
					}
					elseif ($var['type'] === 'set') {
						$defaultValue = '<br>' . implode('<br>', array_map(function($values){
							return implode(' &nbsp;|&nbsp; ', array_map('htmlspecialchars', $values));
						}, $defaultValue));
					}
					if ($label[1]) {
						$label[1] .= '. ';
					}
					if ($defaultValue === '') {
						$defaultValue = $GLOBALS['TL_LANG']['rocksolid_theme_assistant']['default_value_empty'];
					}
					$label[1] .= '<i>' . $GLOBALS['TL_LANG']['rocksolid_theme_assistant']['default_value'] . ': ' . $defaultValue . '</i>';

					$field = array(
						'label'         => $label,
						'inputType'     => 'text',
						'load_callback' => array(array('MadeYourDay\\Contao\\ThemeAssistant', 'fieldLoadCallback')),
						'value'         => $var['value'],
					);

					if ($var['type'] === 'color') {
						$field['value'] = trim($field['value'], '#');
						$field['eval'] = array(
							'maxlength'      => 6,
							'isHexColor'     => true,
							'decodeEntities' => true,
							'tl_class'       => 'wizard',
						);
						$field['wizard'] = array(array('MadeYourDay\\Contao\\ThemeAssistant', 'colorWizardCallback'));
					}
					elseif ($var['type'] === 'boolean') {
						$field['inputType'] = 'checkbox';
					}
					elseif ($var['type'] === 'select') {
						$field['inputType'] = 'select';
						$field['options'] = array();
						foreach ($var['choices'] as $choiceKey => $choiceValue) {
							$field['options'][$choiceKey] = isset($choiceValue[substr($GLOBALS['TL_LANGUAGE'], 0, 2)]) ? $choiceValue[substr($GLOBALS['TL_LANGUAGE'], 0, 2)] : $choiceValue['en'];
						}
					}
					elseif ($var['type'] === 'image') {
						$field['value'] = \FilesModel::findByPath($GLOBALS['TL_CONFIG']['uploadPath'] . '/' . $field['value']);
						if ($field['value']) {
							if (version_compare(VERSION, '3.2', '<')) {
								$field['value'] = $field['value']->id;
							}
							else {
								$field['value'] = $field['value']->uuid;
							}
						}
						else {
							$field['value'] = '';
						}
						$field['inputType'] = 'fileTree';
						$field['eval'] = array(
							'fieldType' => 'radio',
							'filesOnly' => true,
							'extensions' => 'jpg,jpeg,png,gif,svg',
						);
					}
					elseif ($var['type'] === 'background-image') {
						if (substr($field['value'], 0, 5) === 'url("' && substr($field['value'], -2) === '")') {
							$field['value'] = \FilesModel::findByPath(static::resolveRelativePath(dirname($dc->id) . '/' . substr($field['value'], 5, -2)));
							if ($field['value']) {
								if (version_compare(VERSION, '3.2', '<')) {
									$field['value'] = $field['value']->id;
								}
								else {
									$field['value'] = $field['value']->uuid;
								}
							}
							else {
								$field['value'] = '';
							}
						}
						else {
							$field['value'] = '';
						}
						$field['inputType'] = 'fileTree';
						$field['eval'] = array(
							'fieldType' => 'radio',
							'filesOnly' => true,
							'extensions' => 'jpg,jpeg,png,gif,svg',
						);
					}
					elseif ($var['type'] === 'length') {
						$field['inputType'] = 'inputUnit';
						$field['options'] = array(
							'' => '-',
							'px' => 'px',
							'%' => '%',
							'em' => 'em',
							'rem' => 'rem',
							'ex' => 'ex',
							'pt' => 'pt',
							'pc' => 'pc',
							'in' => 'in',
							'cm' => 'cm',
							'mm' => 'mm',
							'vw' => 'vw',
							'vh' => 'vh',
							'vmin' => 'vmin',
							'vmax' => 'vmax',
						);
						if ($field['value']) {
							if (preg_match('(^(-?[.0-9]+)([^.0-9]*))i', $field['value'], $matches) && isset($field['options'][$matches[2]])) {
								$field['value'] = array(
									'value' => $matches[1],
									'unit' => $matches[2],
								);
							}
							else {
								$field['value'] = array(
									'value' => $field['value'],
									'unit' => '',
								);
							}
						}
						else {
							$field['value'] = array(
								'value' => '0',
								'unit' => '',
							);
						}
					}
					elseif ($var['type'] === 'background-repeat') {
						$field['inputType'] = 'select';
						$field['options'] = array(
							'no-repeat' => 'no-repeat',
							'repeat' => 'repeat',
							'repeat-x' => 'repeat-x',
							'repeat-y' => 'repeat-y',
						);
					}
					elseif ($var['type'] === 'background-attachment') {
						$field['inputType'] = 'select';
						$field['options'] = array(
							'scroll' => 'scroll',
							'fixed' => 'fixed',
							'local' => 'local',
						);
					}
					elseif ($var['type'] === 'background-size') {
						$field['inputType'] = 'select';
						$field['options'] = array(
							'auto' => 'auto',
							'cover' => 'cover',
							'contain' => 'contain',
						);
					}
					elseif ($var['type'] === 'set') {
						$field['inputType'] = 'mydMultiListWizard';
						$field['eval'] = array(
							'fields' => array(),
						);
						foreach ($var['fields'] as $fieldKey => $fieldSettings) {
							$field['eval']['fields'][] = array(
								'name' => $fieldKey,
								'label' => $fieldSettings[
									isset($fieldSettings[substr($GLOBALS['TL_LANGUAGE'], 0, 2)])
									? substr($GLOBALS['TL_LANGUAGE'], 0, 2)
									: 'en'
								]['label'],
							);
						}
					}

					$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['fields'][$key] = $field;

				}

				foreach ($data['groups'] as $key => $group) {

					if ($key) {
						$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['palettes']['default'] .= ';';
					}
					$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['palettes']['default'] .= '{group_legend_' . $key . ':hide}';
					$GLOBALS['TL_LANG']['rocksolid_theme_assistant']['group_legend_'.$key] = $group[
						isset($group[substr($GLOBALS['TL_LANGUAGE'], 0, 2)])
						? substr($GLOBALS['TL_LANGUAGE'], 0, 2)
						: 'en'
					]['label'];
					foreach ($data['templateVars'] as $varKey => $var) {
						if ($var['group'] === $key) {
							$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['palettes']['default'] .= ',' . $varKey;
						}
					}

				}

			}
			else {
				$this->redirect('contao/main.php?act=error');
			}

		}
	}

	public function fieldLoadCallback($value, \DataContainer $dc)
	{
		if (version_compare(VERSION, '4.0', '>=')) {
			$route = \System::getContainer()->get('request')->get('_route');
		}
		else {
			$route = str_replace(
				array('contao/', '.php'),
				array('contao_backend_', ''),
				\Environment::get('script')
			);
		}

		if (
			(
				$route === 'contao_backend_file'
				|| $route === 'contao_backend_page'
			)
			&& \Input::get('field') === $dc->field
		) {
			return $value;
		}

		return $GLOBALS['TL_DCA']['rocksolid_theme_assistant']['fields'][$dc->field]['value'];
	}

	public function fieldFileInvalidCallback(\DataContainer $dc)
	{
		return '<p class="tl_gerror"><strong>'
		     . $GLOBALS['TL_LANG']['rocksolid_theme_assistant']['invalid_error'][0] . ':</strong> '
		     . sprintf($GLOBALS['TL_LANG']['rocksolid_theme_assistant']['invalid_error'][1], $dc->id) . '</p>';
	}

	public function fieldFileHashCallback(\DataContainer $dc)
	{
		return '<p class="tl_gerror"><strong>'
		     . $GLOBALS['TL_LANG']['rocksolid_theme_assistant']['hash_warning'][0] . ':</strong> '
		     . sprintf($GLOBALS['TL_LANG']['rocksolid_theme_assistant']['hash_warning'][1], substr($dc->id, 0, -5)) . '</p>';
	}

	public function fieldFileWritableCallback(\DataContainer $dc)
	{
		return '<p class="tl_gerror"><strong>'
		     . $GLOBALS['TL_LANG']['rocksolid_theme_assistant']['writable_warning'][0] . ':</strong> '
		     . sprintf($GLOBALS['TL_LANG']['rocksolid_theme_assistant']['writable_warning'][1], substr($dc->id, 0, -5), $dc->id) . '</p>';
	}

	public function fieldVariationsWizard(\DataContainer $dc)
	{
		return '<script>'
		     . '	$("ctrl_' . $dc->field . '").addEvent("change", function(){'
		     . '		if (window.confirm("' . $GLOBALS['TL_LANG']['rocksolid_theme_assistant']['variations_confirm'] . '")) {'
		     . '			Backend.autoSubmit("' . $dc->table . '");'
		     . '		}'
		     . '		else {'
		     . '			this.set("value", "");'
		     . '		}'
		     . '	});'
		     . '</script>';
	}

	public function colorWizardCallback(\DataContainer $dc)
	{
		return ' '.$this->generateImage('pickcolor.gif', $GLOBALS['TL_LANG']['MSC']['colorpicker'], 'style="vertical-align:top;cursor:pointer" title="' . specialchars($GLOBALS['TL_LANG']['MSC']['colorpicker']) . '" id="moo_' . $dc->field . '"') . '
			<script>
				window.addEvent("domready", function() {
					new MooRainbow("moo_' . $dc->field . '", {
						id:"ctrl_' . $dc->field . '",
						startColor:((cl = $("ctrl_' . $dc->field . '").value.hexToRgb(true)) ? cl : [255, 0, 0]),
						imgPath:"assets/mootools/colorpicker/'.COLORPICKER.'/images/",
						onComplete: function(color){
							$("ctrl_' . $dc->field . '").value = color.hex.replace("#", "");
						}
					});
				});
			</script>';
	}

	public function onsubmitCallback(\DataContainer $dc)
	{

		if ($dc->id && substr($dc->id, -5) === '.base') {

			$type = 'html';
			if (substr($dc->id, -9, 4) === '.css') {
				$type = 'css';
			}

			list($data, $template) = static::parseBaseFile(file_get_contents(TL_ROOT . '/' . $dc->id), $type);

			// Check if parsing the file was successful
			if (empty($data) || empty($data['fileHash']) || empty($data['templateVars']) || !trim($template)) {
				$this->redirect('contao/main.php?act=error');
				return;
			}

			if (\Input::post('variations') && substr(\Input::post('variations'), 0, 9) === 'variation') {

				$variation = (int) substr(\Input::post('variations'), 9);
				foreach ($data['templateVars'] as $key => $var) {
					if (isset($data['templateVars'][$key]['defaultValues'][$variation])) {
						$data['templateVars'][$key]['value'] = $data['templateVars'][$key]['defaultValues'][$variation];
					}
					elseif (isset($data['templateVars'][$key]['defaultValues'][0])) {
						$data['templateVars'][$key]['value'] = $data['templateVars'][$key]['defaultValues'][0];
					}
				}

			}
			else {

				foreach ($data['templateVars'] as $key => $var) {

					if(\Input::post($key) === null){
						continue;
					}

					$value = \Input::post($key);

					if ($data['templateVars'][$key]['type'] === 'color') {
						if (strlen($value) === 6) {
							$value = '#' . strtolower($value);
						}
						else {
							$value = $data['templateVars'][$key]['value'];
						}
					}
					elseif ($data['templateVars'][$key]['type'] === 'boolean') {
						$value = (bool)$value;
					}
					elseif ($data['templateVars'][$key]['type'] === 'image') {
						$file = null;
						if (trim($value)) {
							if (version_compare(VERSION, '3.2', '<')) {
								$file = \FilesModel::findByPk($value);
							}
							else {
								$file = \FilesModel::findByUuid(\String::uuidToBin($value));
							}
						}
						if ($file) {
							$value = substr($file->path, strlen($GLOBALS['TL_CONFIG']['uploadPath'])+1);
						}
						else {
							$value = '';
						}
					}
					elseif ($data['templateVars'][$key]['type'] === 'background-image') {
						$file = null;
						if (trim($value)) {
							if (version_compare(VERSION, '3.2', '<')) {
								$file = \FilesModel::findByPk($value);
							}
							else {
								$file = \FilesModel::findByUuid(\String::uuidToBin($value));
							}
						}
						if ($file) {
							$value = 'url("' . static::getRelativePath(dirname($dc->id), $file->path) . '")';
						}
						else {
							$value = 'none';
						}
					}
					elseif ($data['templateVars'][$key]['type'] === 'length') {
						if ($value && isset($value['value']) && isset($value['unit'])) {
							$value = (trim($value['value']) ? trim($value['value']) : '0') . trim($value['unit']);
						}
						if (! $value) {
							$value = '0';
						}
					}
					elseif ($data['templateVars'][$key]['type'] === 'set') {
						if(count($value) === 1){
							$emptyValues = true;
							foreach ($value[0] as $setValue) {
								if($setValue){
									$emptyValues = false;
									break;
								}
							}
							if($emptyValues){
								$value = array();
							}
						}
					}

					$data['templateVars'][$key]['value'] = $value;

				}

			}

			$rendered = $this->renderTemplate($template, $data, $type);
			if (!$rendered) {
				$this->log('Parse error in Theme Assistant template "' . $dc->id . '"', 'MadeYourDay\Contao\ThemeAssistant::onsubmitCallback', TL_ERROR);
				return $this->redirect('contao/main.php?act=error');
			}
			file_put_contents(TL_ROOT . '/' . substr($dc->id, 0, -5), $rendered);
			$data['fileHash'] = md5_file(TL_ROOT . '/' . substr($dc->id, 0, -5));
			if($type === 'css'){
				$template = "/*".json_encode($data) . "*/\n" . $template;
			}
			else{
				$template = "<!--".json_encode($data) . "-->\n" . $template;
			}
			file_put_contents(TL_ROOT . '/' . $dc->id, $template);

			if(substr($dc->id, 0, strlen($GLOBALS['TL_CONFIG']['uploadPath'])) === $GLOBALS['TL_CONFIG']['uploadPath']){
				$file = new \File($dc->id);
				$fileRecord = \FilesModel::findByPath($dc->id);
				$fileRecord->hash = $file->hash;
				$fileRecord->save();
			}

		}
		else {
			$this->redirect('contao/main.php?act=error');
		}

	}

	public function editButtonCallback($arrRow, $href, $label, $title, $icon, $attributes, $strTable, $arrRootIds, $arrChildRecordIds, $blnCircularReference, $strPrevious, $strNext)
	{
		if(substr($arrRow['id'], -5) === '.base'){
			return '<a href="' . $this->addToUrl($href . '&amp;id=' . $arrRow['id']) . '" title="' . specialchars($title) . '"' . $attributes . '>'
			     . $this->generateImage($icon, $label)
			     . '</a> ';
		}

		return '';
	}

	public function listLabelCallback($row, $label, $dc, $args)
	{
		return $row['name'];
	}

	protected function renderTemplate($template, $data, $type)
	{
		if ($type === 'css') {
			return $this->renderCssTemplate($template, $data);
		}
		else {
			return $this->renderHtmlTemplate($template, $data);
		}
	}

	protected function renderHtmlTemplate($template, $data)
	{
		$values = array();
		foreach ($data['templateVars'] as $key => $var) {
			$values[$key] = $var['value'];
		}

		$replace = array(
			'(<\\?php)i' => '<rst?php',
			'([ \\t]*<!--\\:(.*?)-->)is' => '<?php $1 ?>',
			'(\\{\\:\\=(.*?)\\})is' => '<?php echo $1 ?>',
			'(\\{\\:(.*?)\\})is' => '<?php $1 ?>',
		);

		$template = preg_replace(array_keys($replace), array_values($replace), $template);
		$template = $this->parsePhpCode($template, $values);
		$template = str_replace('<rst?php', '<?php', $template);

		return $template;
	}

	protected function parsePhpCode($_phpCode, $data)
	{
		$syntaxOK = @eval('return true;?>' . $_phpCode . '<?php ');
		if (!$syntaxOK) {
			return '';
		}

		extract($data);
		ob_start();
		eval('?>' . $_phpCode . '<?php ');

		return ob_get_clean() ?: '';
	}

	protected function renderCssTemplate($template, $data)
	{
		$replaceFrom = array();
		$replaceTo = array();

		// Backwards compatibility
		foreach ($data['templateVars'] as $var) {
			$replaceFrom[] = '/***rst' . $var['placeholder'] . '***/';
			$replaceTo[] = $var['value'];
		}

		// Backwards compatibility
		$data['colorFunctions'] = isset($data['colorFunctions'])
			? $data['colorFunctions']
			: array();
		foreach ($data['colorFunctions'] as $colorFunction) {
			$replaceFrom[] = '/***rst' . $colorFunction['placeholder'] . '***/';
			$replaceTo[] = $this->renderCssFunction($colorFunction, $data);
		}

		$template = str_replace($replaceFrom, $replaceTo, $template);

		$replace = array(
			'(<\\?php)i' => '<rst?php',
			'(/\\*\\:=\\s(.*?)\\s\\*/)i' => '<?php echo $1 ?>',
			'([ \\t]*/\\*\\:(.*?)\\*/)is' => '<?php $1 ?>',
		);
		$template = preg_replace(array_keys($replace), array_values($replace), $template);
		$self = $this;
		$templatesData = array(
			'v' => array_map(function($var){
				return $var['value'];
			}, $data['templateVars']),
			'f' => function() use($self) {
				$arguments = func_get_args();
				return $self->executeCssFunction(array_shift($arguments), $arguments);
			},
		);

		// Backwards compatibility
		$templatesData['tv'] = $templatesData['v'];

		$template = $this->parsePhpCode($template, $templatesData);
		$template = str_replace('<rst?php', '<?php', $template);

		return $template;
	}

	protected function renderCssFunction($function, $data)
	{
		foreach ($function['params'] as $key => $param) {

			$function['params'][$key] = preg_replace_callback('(\\$([a-z0-9_-]+))i', function($matches) use ($data){
				if (isset($data['templateVars'][$matches[1]])) {
					return $data['templateVars'][$matches[1]]['value'];
				}
				return $matches[0];
			}, $function['params'][$key]);

			$function['params'][$key] = preg_replace_callback('(([0-9.]+)\\s*([*/+-])\\s*([0-9.]+))i', function($matches){
				if ($matches[2] === '*') {
					return $matches[1] * $matches[3];
				}
				elseif ($matches[2] === '/') {
					return $matches[1] / $matches[3];
				}
				elseif ($matches[2] === '+') {
					return $matches[1] + $matches[3];
				}
				elseif ($matches[2] === '-') {
					return $matches[1] - $matches[3];
				}
				return $matches[0];
			}, $function['params'][$key]);

			$function['params'][$key] = trim($function['params'][$key]);

		}

		return $this->executeCssFunction($function['function'], $function['params']);
	}

	function executeCssFunction($function, $params)
	{
		if ($function === 'rgba') {

			$color = $params[0];

			return 'rgba('.hexdec(substr($color, 1, 2)).', '.hexdec(substr($color, 3, 2)).', '.hexdec(substr($color, 5, 2)).', '.$params[1].')';

		}
		if ($function === 'tint' || $function === 'shade') {

			array_unshift($params, $function === 'tint' ? '#ffffff' : '#000000');
			$function = 'mix';

		}
		if ($function === 'mix') {

			$color1 = substr(trim($params[0]), 1);
			$color2 = substr(trim($params[1]), 1);
			$weight = isset($params[2]) ? $params[2]/100 : 0.5;

			return strtolower('#'
				.sprintf("%02X",(int)((hexdec(substr($color1, 0, 2))*$weight) + (hexdec(substr($color2, 0, 2))*(1-$weight)))) // red
				.sprintf("%02X",(int)((hexdec(substr($color1, 2, 2))*$weight) + (hexdec(substr($color2, 2, 2))*(1-$weight)))) // green
				.sprintf("%02X",(int)((hexdec(substr($color1, 4, 2))*$weight) + (hexdec(substr($color2, 4, 2))*(1-$weight)))) // blue
			);

		}
		if ($function === 'lighten' || $function === 'darken') {

			$color = substr($params[0], 1);
			$weight = $params[1]/100;
			$color = static::colorRgbToHsl(array(hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2))));
			$color[2] += $weight * ($function === 'lighten' ? 1 : -1);
			$color[2] = max(0, min(1, $color[2]));
			$color = static::colorHslToRgb($color);

			return '#'.strtolower(sprintf("%02X", round($color[0])) . sprintf("%02X", round($color[1])) . sprintf("%02X", round($color[2])));

		}
		if ($function === 'saturate' || $function === 'desaturate') {

			$color = substr($params[0], 1);
			$weight = $params[1]/100;
			$color = static::colorRgbToHsl(array(hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2))));
			$color[1] += $weight * ($function === 'saturate' ? 1 : -1);
			$color[1] = max(0, min(1, $color[1]));
			$color = static::colorHslToRgb($color);

			return '#'.strtolower(sprintf("%02X", round($color[0])) . sprintf("%02X", round($color[1])) . sprintf("%02X", round($color[2])));

		}
		if ($function === 'adjust-hue') {

			$color = substr($params[0], 1);
			$degrees = $params[1] / 360;
			$color = static::colorRgbToHsl(array(hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2))));
			$color[0] += $degrees;
			while ($color[0] < 0) {
				$color[0] += 1;
			}
			while ($color[0] > 1) {
				$color[0] -= 1;
			}
			$color = static::colorHslToRgb($color);

			return '#'.strtolower(sprintf("%02X", round($color[0])) . sprintf("%02X", round($color[1])) . sprintf("%02X", round($color[2])));

		}
		if ($function === 'invert') {

			$color = substr($params[0], 1);

			return strtolower('#'
				.sprintf("%02X", 255 - (int)hexdec(substr($color, 0, 2))) // red
				.sprintf("%02X", 255 - (int)hexdec(substr($color, 2, 2))) // green
				.sprintf("%02X", 255 - (int)hexdec(substr($color, 4, 2))) // blue
			);

		}
		if ($function === 'col') {

			return rtrim(rtrim(number_format($params[0] / $params[1] * 100, 5, '.', ''), '0'), '.') . '%';

		}
		if ($function === 'lightness') {

			$color = substr($params[0], 1);
			$color = static::colorRgbToHsl(array(hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2))));

			return $color[2] * 100;

		}
		if ($function === 'saturation') {

			$color = substr($params[0], 1);
			$color = static::colorRgbToHsl(array(hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2))));

			return $color[1] * 100;

		}

		$this->log('Unknow CSS function "' . $function . '(' . implode(', ', $params) . ')" in Theme Assistant template', 'MadeYourDay\Contao\ThemeAssistant::executeCssFunction', TL_ERROR);
		return '';
	}

	protected function colorRgbToHsl($rgb)
	{
		$clrR = ($rgb[0]);
		$clrG = ($rgb[1]);
		$clrB = ($rgb[2]);

		$clrMin = min($clrR, $clrG, $clrB);
		$clrMax = max($clrR, $clrG, $clrB);
		$deltaMax = $clrMax - $clrMin;

		$L = ($clrMax + $clrMin) / 255 / 2;

		if (0 == $deltaMax){
			$H = 0;
			$S = 0;
		}
		else{
			if (0.5 > $L){
				$S = $deltaMax / ($clrMax + $clrMin);
			}
			else{
				$S = $deltaMax / (510 - $clrMax - $clrMin);
			}

			if ($clrMax == $clrR) {
				$H = ($clrG - $clrB) / (6.0 * $deltaMax);
			}
			else if ($clrMax == $clrG) {
				$H = 1/3 + ($clrB - $clrR) / (6.0 * $deltaMax);
			}
			else {
				$H = 2 / 3 + ($clrR - $clrG) / (6.0 * $deltaMax);
			}

			if (0 > $H) $H += 1;
			if (1 < $H) $H -= 1;
		}

		return array($H, $S,$L);
	}

	protected function colorHslToRgb($hsl)
	{
		$H = $hsl[0];
		$S = $hsl[1];
		$L = $hsl[2];

		if($S === 0){
			$R = $L * 255;
			$G = $L * 255;
			$B = $L * 255;
		}
		else{
			if($L < 0.5){
				$v2 = $L * (1 + $S);
			}
			else{
				$v2 = ($L + $S) - ($S * $L);
			}

			$v1 = 2 * $L - $v2;

			$R = 255 * static::colorHslToRgbHue($v1, $v2, $H + (1 / 3));
			$G = 255 * static::colorHslToRgbHue($v1, $v2, $H);
			$B = 255 * static::colorHslToRgbHue($v1, $v2, $H - (1 / 3));
		}

		return array($R, $G, $B);
	}

	protected function colorHslToRgbHue($v1, $v2, $H)
	{
		if($H < 0) $H += 1;
		if($H > 1) $H -= 1;
		if((6 * $H) < 1) return ($v1 + ($v2 - $v1) * 6 * $H);
		if((2 * $H) < 1) return ($v2);
		if((3 * $H) < 2) return ($v1 + ($v2 - $v1) * ((2 / 3) - $H) * 6);

		return $v1;
	}

	protected static function parseBaseFile($source, $type)
	{
		$template = explode("\n", $source, 2);

		if (substr($template[0], 0, 3) === chr(239) . chr(187) . chr(191)) {
			// UTF-8 BOM detected
			$template[0] = substr($template[0], 3);
		}

		$template[0] = trim($template[0]);

		if ($type === 'css') {
			$data = json_decode(substr($template[0], 2, -2), true);
		}
		else {
			$data = json_decode(substr($template[0], 4, -3), true);
		}

		$template = $template[1];

		return array($data, $template);
	}

	protected static function isWriteable($path)
	{
		if ($path[strlen($path)-1] === '/') {
			return static::isWriteable($path . uniqid(mt_rand()) . '.tmp');
		}
		else if (is_dir($path)) {
			return static::isWriteable($path . '/' . uniqid(mt_rand()) . '.tmp');
		}

		$rm = file_exists($path);
		$f = @fopen($path, 'a');
		if ($f === false) {
			return false;
		}
		fclose($f);

		if (!$rm) {
			unlink($path);
		}

		return true;
	}

	protected static function getRelativePath($path1, $path2)
	{
		//Remove starting, ending, and double / in paths
		$path1 = str_replace('\\', '/', trim($path1, '/'));
		$path2 = str_replace('\\', '/', trim($path2, '/'));
		while (substr_count($path1, '//')) {
			$path1 = str_replace('//', '/', $path1);
		}
		while (substr_count($path2, '//')) {
			$path2 = str_replace('//', '/', $path2);
		}

		//create arrays
		$arr1 = explode('/', $path1);
		if (!$path1) {
			$arr1 = array();
		}
		$arr2 = explode('/', $path2);
		if (!$path2) {
			$arr2 = array();
		}
		$size1 = count($arr1);
		$size2 = count($arr2);

		//now the hard part :-p
		$path = '';
		for ($i = 0; $i < min($size1, $size2); $i ++) {
			if ($arr1[$i] === $arr2[$i]) {
				continue;
			}
			else {
				$path = '../' . $path . $arr2[$i] . '/';
			}
		}
		if ($size1 > $size2) {
			for ($i = $size2; $i < $size1; $i ++) {
				$path = '../' . $path;
			}
		}
		elseif ($size2 > $size1) {
			for ($i = $size1; $i < $size2; $i ++) {
				$path .= $arr2[$i] . '/';
			}
		}

		return trim($path, '/');
	}

	protected static function resolveRelativePath($path)
	{
		$path = str_replace('\\', '/', $path);
		$parts = array_filter(explode('/', $path), 'strlen');
		$absolutes = array();
		foreach ($parts as $part) {
			if ('.' === $part) {
				continue;
			}
			if ('..' === $part) {
				array_pop($absolutes);
			}
			else {
				$absolutes[] = $part;
			}
		}

		return implode('/', $absolutes);
	}
}
