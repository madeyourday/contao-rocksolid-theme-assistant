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
	public function onloadCallback(\DataContainer $dc)
	{
		if ($dc->id) {

			if (substr($dc->id, -5) === '.base') {

				$type = 'html';
				if (substr($dc->id, -9, 4) === '.css') {
					$type = 'css';
				}

				$template = explode("\n", file_get_contents(TL_ROOT . '/' . $dc->id), 2);
				if ($type === 'css') {
					$data = json_decode(substr($template[0], 2, -2), true);
				}
				else {
					$data = json_decode(substr($template[0], 4, -3), true);
				}
				$template = $template[1];

				if($data['fileHash'] !== md5_file(TL_ROOT . '/' . substr($dc->id, 0, -5))){
					$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['fields']['file_hash_warning'] = array(
						'label' => array('', ''),
						'input_field_callback' => array('MadeYourDay\\Contao\\ThemeAssistant', 'fieldFileHashCallback'),
					);
					$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['palettes']['default'] .= 'file_hash_warning;';
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
					elseif ($var['type'] === 'image') {
						$field['value'] = \FilesModel::findByPath($GLOBALS['TL_CONFIG']['uploadPath'] . '/' . $field['value'])->id;
						$field['inputType'] = 'fileTree';
						$field['eval'] = array(
							'multiple' => false,
							'filesOnly' => true,
							'extensions' => 'jpg,jpeg,png,gif,svg',
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

				$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['palettes']['default'] .= ';{legend_source:hide},source';
				$GLOBALS['TL_DCA']['rocksolid_theme_assistant']['fields']['source'] = array(
					'label'                => $GLOBALS['TL_LANG']['rocksolid_theme_assistant']['source'],
					'input_field_callback' => array('MadeYourDay\\Contao\\ThemeAssistant', 'fieldCallbackSource'),
					'load_callback'        => array(array('MadeYourDay\\Contao\\ThemeAssistant', 'fieldLoadCallback')),
					'value'                => $template,
				);

			}
			else {
				$this->redirect('contao/main.php?act=error');
			}

		}
	}

	public function fieldLoadCallback($value, \DataContainer $dc)
	{
		return $GLOBALS['TL_DCA']['rocksolid_theme_assistant']['fields'][$dc->field]['value'];
	}

	public function fieldFileHashCallback($a, $b)
	{
		return '<p class="tl_gerror"><strong>'
		     . $GLOBALS['TL_LANG']['rocksolid_theme_assistant']['hash_warning'][0] . ':</strong> '
		     . sprintf($GLOBALS['TL_LANG']['rocksolid_theme_assistant']['hash_warning'][1], substr($a->id, 0, -5)) . '</p>';
	}

	public function fieldCallbackSource(\DataContainer $dc)
	{
		$codeEditor = '';

		// Prepare the code editor
		if ($GLOBALS['TL_CONFIG']['useCE']) {

			$type = 'htmlmixed';

			if ($dc->id && substr($dc->id, -9, 4) === '.css') {
				$type = 'css';
			}

			$this->ceFields = array(array(
				'id'   => 'ctrl_source',
				'type' => $type,
			));

			$this->language = $GLOBALS['TL_LANGUAGE'];

			// Load the code editor configuration
			ob_start();
			include TL_ROOT . '/system/config/codeMirror.php';
			$codeEditor = ob_get_contents();
			ob_end_clean();

		}

		return '
			<div class="tl_tbox">
				<p class="tl_info">' . $GLOBALS['TL_LANG']['rocksolid_theme_assistant']['editor_info'] . '</p>
				<h3><label for="ctrl_source">' . $GLOBALS['TL_LANG']['rocksolid_theme_assistant']['source'][0] . '</label></h3>
				<textarea name="source" id="ctrl_source" class="tl_textarea monospace" rows="12" cols="80" style="height:500px" onfocus="Backend.getScrollOffset()">' . "\n" . htmlspecialchars($dc->value) . '</textarea>
				'. (($GLOBALS['TL_CONFIG']['showHelp']) ? '<p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['rocksolid_theme_assistant']['source'][1] . '</p>' : '') . '
			</div>' . "\n" . $codeEditor;
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

			$template = explode("\n", file_get_contents(TL_ROOT . '/' . $dc->id), 2);
			if ($type === 'css') {
				$data = json_decode(substr($template[0], 2, -2), true);
			}
			else {
				$data = json_decode(substr($template[0], 4, -3), true);
			}
			$template = $template[1];

			foreach ($data['templateVars'] as $key => $var) {

				if(!isset($_POST[$key])){
					continue;
				}

				$value = $_POST[$key];

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
					$value = substr(\FilesModel::findByPk($value)->path, strlen($GLOBALS['TL_CONFIG']['uploadPath'])+1);
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

			// Accessing raw post data
			if(!empty($_POST['source'])){
				$template = $_POST['source'];
			}
			file_put_contents(TL_ROOT . '/' . substr($dc->id, 0, -5), $this->renderTemplate($template, $data, $type));
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
			'([ \\t]*<!--\\:(.*?)-->)i' => '<?php $1 ?>',
			'(\\{\\:\\=(.*?)\\})i' => '<?php echo $1 ?>',
			'(\\{\\:(.*?)\\})i' => '<?php $1 ?>',
		);

		$template = preg_replace(array_keys($replace), array_values($replace), $template);
		$template = $this->parsePhpCode($template, $values);
		$template = str_replace('<rst?php', '<?php', $template);

		return $template;
	}

	protected function parsePhpCode($_phpCode, $data)
	{
		extract($data);
		ob_start();
		eval('?>' . $_phpCode . '<?php ');

		return ob_get_clean();
	}

	protected function renderCssTemplate($template, $data)
	{
		$replaceFrom = array();
		$replaceTo = array();

		foreach ($data['templateVars'] as $var) {
			$replaceFrom[] = '/***rst' . $var['placeholder'] . '***/';
			$replaceTo[] = $var['value'];
		}

		foreach ($data['colorFunctions'] as $colorFunction) {
			$replaceFrom[] = '/***rst' . $colorFunction['placeholder'] . '***/';
			$replaceTo[] = $this->executeColorFunction($colorFunction, $data);
		}

		return str_replace($replaceFrom, $replaceTo, $template);
	}

	protected function executeColorFunction($function, $data)
	{
		if ($function['function'] === 'rgba') {

			$color = $data['templateVars'][substr($function['params'][0], 1)]['value'];

			return 'rgba('.hexdec(substr($color, 1, 2)).', '.hexdec(substr($color, 3, 2)).', '.hexdec(substr($color, 5, 2)).', '.$function['params'][1].')';

		}
		elseif ($function['function'] === 'mix') {

			if (substr($function['params'][0], 0, 1) === '$') {
				$function['params'][0] = $data['templateVars'][substr($function['params'][0], 1)]['value'];
			}
			if (substr($function['params'][1], 0, 1) === '$') {
				$function['params'][1] = $data['templateVars'][substr($function['params'][1], 1)]['value'];
			}
			$color1 = substr(trim($function['params'][0]), 1);
			$color2 = substr(trim($function['params'][1]), 1);
			$weight = substr(trim($function['params'][2]), 0, -1)/100;

			return strtolower('#'
				.sprintf("%02X",(int)((hexdec(substr($color1, 0, 2))*$weight) + (hexdec(substr($color2, 0, 2))*(1-$weight)))) // red
				.sprintf("%02X",(int)((hexdec(substr($color1, 2, 2))*$weight) + (hexdec(substr($color2, 2, 2))*(1-$weight)))) // green
				.sprintf("%02X",(int)((hexdec(substr($color1, 4, 2))*$weight) + (hexdec(substr($color2, 4, 2))*(1-$weight)))) // blue
			);

		}
		elseif ($function['function'] === 'lighten' || $function['function'] === 'darken') {

			$color = substr($data['templateVars'][substr($function['params'][0], 1)]['value'], 1);
			$weight = substr($function['params'][1], 0, -1)/100;
			$color = static::colorRgbToHsl(array(hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2))));
			$color[2] += $weight * ($function['function'] === 'lighten' ? 1 : -1);
			if ($color[2] < 0) {
				$color[2] = 0;
			}
			elseif ($color[2] > 1) {
				$color[2] = 1;
			}
			$color = static::colorHslToRgb($color);

			return '#'.strtolower(sprintf("%02X", round($color[0])) . sprintf("%02X", round($color[1])) . sprintf("%02X", round($color[2])));

		}
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
}
