<?php
/*
 * Copyright MADE/YOUR/DAY <mail@madeyourday.co>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MadeYourDay\RockSolidThemeAssistant\Widget;

/**
 * Multi list wizard
 *
 * Provide methods to handle list items with multiple fields.
 *
 * @author Martin Auswöger <martin@madeyourday.co>
 */
class MultiListWizard extends \Widget
{
	protected $blnSubmitInput = true;

	protected $strTemplate = 'be_widget';

	public function generate()
	{
		$arrButtons = array('copy', 'up', 'down', 'delete');
		$strCommand = 'cmd_' . $this->strField;

		if (!is_array($this->varValue) || empty($this->varValue)) {
			$varValue = array(array());
			foreach ($this->fields as $field) {
				$varValue[0][$field['name']] = '';
			}
			$this->varValue = $varValue;
		}

		$return = '<table id="ctrl_'.$this->strId.'" class="tl_multilistwizard" width="100%">'
		        . '<thead>'
		        . '<tr>';

		foreach ($this->fields as $field) {
			$return .= '<th>' . $field['label'] . '</th>';
		}

		$return .= '<td>&nbsp;</td>'
		         . '</tr>'
		         . '</thead>'
		         . '</tbody>';

		foreach ($this->varValue as $key => $values) {

			$return .= '<tr>';

			foreach ($this->fields as $field) {
				$return .= '<td><input type="text" name="'.$this->strId.'['.$key.']['.$field['name'].']" class="tl_text" style="width:95%" value="'.htmlspecialchars($this->varValue[$key][$field['name']]).'"' . $this->getAttributes() . '></td>';
			}

			$return .= '<td style="white-space:nowrap">';
			foreach ($arrButtons as $button) {
				$return .= '<a href="'.$this->addToUrl('&amp;'.$strCommand.'='.$button.'&amp;cid='.$i.'&amp;id='.$this->currentRecord).'" title="'.htmlspecialchars($GLOBALS['TL_LANG']['MSC']['lw_'.$button]).'" onclick="Backend.optionsWizard(this,\''.$button.'\',\'ctrl_'.$this->strId.'\');return false">'.$this->generateImage($button.'.gif', $GLOBALS['TL_LANG']['MSC']['lw_'.$button], 'class="tl_listwizard_img"').'</a> ';
			}
			$return .= '</td>';

			$return .= '</tr>';

		}

		$return .= '</tbody>'
		         . '</table>';

		return $return;
	}
}
