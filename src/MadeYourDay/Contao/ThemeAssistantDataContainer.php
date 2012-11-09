<?php
/*
 * Copyright MADE/YOUR/DAY <mail@madeyourday.co>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Some parts of this file are adopted from the Contao Open Source CMS and are
 * therefore licensed under their own license <http://contao.org/>
 */

namespace MadeYourDay\Contao;

/**
 * RockSolid Theme Assistant DataContainer
 *
 * @author Martin Auswöger <martin@madeyourday.co>
 */
class ThemeAssistantDataContainer extends \DataContainer implements \listable, \editable
{
	public function __construct($strTable, $arrModule = array())
	{
		parent::__construct();

		// Check the request token (see #4007)
		if (isset($_GET['act'])) {
			if (!isset($_GET['rt']) || !\RequestToken::validate(\Input::get('rt'))) {
				$this->Session->set('INVALID_TOKEN_URL', \Environment::get('request'));
				$this->redirect('contao/confirm.php');
			}
		}

		$this->intId = \Input::get('id');

		// Check whether the table is defined
		if (!$strTable || !isset($GLOBALS['TL_DCA'][$strTable])) {
			$this->log('Could not load the data container configuration for "' . $strTable . '"', 'DC_Table __construct()', TL_ERROR);
			trigger_error('Could not load the data container configuration', E_USER_ERROR);
		}

		$this->strTable = $strTable;
		$this->arrModule = $arrModule;

		// Call onload_callback (e.g. to check permissions)
		if (is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onload_callback'])) {
			foreach ($GLOBALS['TL_DCA'][$this->strTable]['config']['onload_callback'] as $callback) {
				if (is_array($callback)) {
					$this->import($callback[0]);
					$this->$callback[0]->$callback[1]($this);
				}
			}
		}
	}

	public function showAll()
	{
		$return = '';

		$query = 'SELECT * FROM tl_theme ORDER BY name ';
		$objRowStmt = $this->Database->prepare($query);
		$objRow = $objRowStmt->execute();

		$themeList = array();

		$result = $objRow->fetchAllAssoc();

		$this->import('FilesModel');

		foreach ($result as $row) {

			$files = array();
			$folders = \FilesModel::findMultipleByIds(unserialize($row['folders']));

			if($folders === null){
				continue;
			}

			foreach ($folders->fetchEach('path') as $folder) {
				$filesResult = \FilesModel::findBy(array($this->FilesModel->getTable().'.path LIKE ? AND extension = \'base\''), $folder.'/%');
				if($filesResult === null){
					continue;
				}
				foreach ($filesResult->fetchEach('path') as $file) {
					if(!file_exists(TL_ROOT.'/'.substr($file, 0, -5))){
						continue;
					}
					$extension = explode('.', $file);
					$extension = $extension[count($extension)-2];
					$files[] = array(
						'id'   => $file,
						'type' => $extension,
						'name' => substr($file, strlen($folder)+1, -5),
					);
				}
			}

			$screenshot = \FilesModel::findByPk($row['screenshot']);

			if ($screenshot !== null) {
				$screenshot = TL_FILES_URL . \Image::get($screenshot->path, 40, 30, 'center_top');
			}

			if (count($files)) {
				$themeList[] = array(
					'name' => $row['name'],
					'files' => $files,
					'screenshot' => $screenshot,
				);
			}

		}

		if (!count($themeList)) {
			return '<p class="tl_empty">' . $GLOBALS['TL_LANG']['MSC']['noResult'] . '</p>';
		}

		$eoCount = -1;

		$return .= '<div id="tl_buttons">' . $this->generateGlobalButtons() . '</div>' . \Message::generate(true);
		$return .= '<div class="tl_listing_container list_view">';
		$return .= '<table class="tl_listing' . ($GLOBALS['TL_DCA'][$this->strTable]['list']['label']['showColumns'] ? ' showColumns' : '') . '">';

		foreach ($themeList as $key => $theme) {

			if($key){
				$return .= '<tr style="height: 30px;"><td colspan="2">&nbsp;</td></tr>';
			}

			$return .= '<tr><td colspan="2" class="tl_folder_tlist">';
			if ($theme['screenshot']) {
				$return .= '<img src="'.$theme['screenshot'].'" alt="" class="theme_preview"> ';
			}
			$return .= $theme['name'].'</td></tr>';

			foreach ($theme['files'] as $file) {
				$return .= '<tr class="'.((++$eoCount % 2 == 0) ? 'even' : 'odd').'" onmouseover="Theme.hoverRow(this,1)" onmouseout="Theme.hoverRow(this,0)">';
				$return .= '<td class="tl_file_list">'.$GLOBALS['TL_LANG']['rocksolid_theme_assistant']['file_types'][$file['type']].' ('.$file['name'].')</td>';
				$return .= '<td class="tl_file_list tl_right_nowrap">'.$this->generateButtons($file, $this->strTable).'</td>';
				$return .= '</tr>';
			}

		}

		$return .= '</table>';
		$return .= '</div>';

		return $return;
	}

	/**
	 * Edit action
	 *
	 * Some parts of this function are adopted from
	 * system/modules/core/drivers/DC_Table.php
	 *
	 * @return string back end HTML
	 */
	public function edit()
	{
		$this->isValid($this->intId);

		if (is_dir(TL_ROOT . '/' . $this->intId)) {
			$this->log('Folder "' . $this->intId . '" cannot be edited', 'DC_RockSolidThemeAssistant edit()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}
		elseif (!file_exists(TL_ROOT . '/' . $this->intId)) {
			$this->log('File "' . $this->intId . '" does not exist', 'DC_RockSolidThemeAssistant edit()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}
		elseif (substr($this->intId, -5) !== '.base') {
			$this->log('File "' . $this->intId . '" cannot be edited', 'DC_RockSolidThemeAssistant edit()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		$this->import('BackendUser', 'User');

		// Check user permission
		if (!$this->User->isAdmin && !$this->User->hasAccess('f2', 'fop')) {
			$this->log('Not enough permissions to edit the file "' . $this->intId . '"', 'DC_RockSolidThemeAssistant edit()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		$objFile = new \File($this->intId);

		$return = '';

		// Build an array from boxes and rows
		$this->strPalette = $this->getPalette();
		$boxes = trimsplit(';', $this->strPalette);
		$legends = array();

		if (!empty($boxes)) {

			foreach ($boxes as $k => $v) {

				$eCount = 1;
				$boxes[$k] = trimsplit(',', $v);

				foreach ($boxes[$k] as $kk=>$vv) {

					if (preg_match('/^\[.*\]$/', $vv)) {
						++$eCount;
						continue;
					}

					if (preg_match('/^\{.*\}$/', $vv)) {
						$legends[$k] = substr($vv, 1, -1);
						unset($boxes[$k][$kk]);
					}
					elseif ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$vv]['exclude'] || !is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$vv])) {
						unset($boxes[$k][$kk]);
					}

				}

				// Unset a box if it does not contain any fields
				if (count($boxes[$k]) < $eCount) {
					unset($boxes[$k]);
				}

			}

			$class = 'tl_tbox';
			$fs = $this->Session->get('fieldset_states');
			$blnIsFirst = true;

			// Render boxes
			foreach ($boxes as $k=>$v) {

				$strAjax = '';
				$blnAjax = false;
				$key = '';
				$cls = '';
				$legend = '';

				if (isset($legends[$k])) {
					list($key, $cls) = explode(':', $legends[$k]);
					$legend = "\n" . '<legend onclick="AjaxRequest.toggleFieldset(this,\'' . $key . '\',\'' . $this->strTable . '\')">' . (isset($GLOBALS['TL_LANG'][$this->strTable][$key]) ? $GLOBALS['TL_LANG'][$this->strTable][$key] : $key) . '</legend>';
				}

				if (isset($fs[$this->strTable][$key])) {
					$class .= ($fs[$this->strTable][$key] ? '' : ' collapsed');
				}
				else {
					$class .= (($cls && $legend) ? ' ' . $cls : '');
				}

				$return .= "\n\n" . '<fieldset' . ($key ? ' id="pal_'.$key.'"' : '') . ' class="' . $class . ($legend ? '' : ' nolegend') . '">' . $legend;

				// Build rows of the current box
				foreach ($v as $vv) {

					if ($vv == '[EOF]') {
						if ($blnAjax && \Environment::get('isAjaxRequest')) {
							return $strAjax . '<input type="hidden" name="FORM_FIELDS[]" value="'.specialchars($this->strPalette).'">';
						}
						$blnAjax = false;
						$return .= "\n" . '</div>';
						continue;
					}

					if (preg_match('/^\[.*\]$/', $vv)) {
						$thisId = 'sub_' . substr($vv, 1, -1);
						$blnAjax = ($ajaxId == $thisId && \Environment::get('isAjaxRequest')) ? true : false;
						$return .= "\n" . '<div id="'.$thisId.'">';
						continue;
					}

					$this->strField = $vv;
					$this->strInputName = $vv;
					$this->varValue = '';

					// Autofocus the first field
					if ($blnIsFirst && $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['inputType'] == 'text') {
						$GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['autofocus'] = 'autofocus';
						$blnIsFirst = false;
					}

					// Convert CSV fields (see #2890)
					if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['multiple'] && isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['csv'])) {
						$this->varValue = trimsplit($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['csv'], $this->varValue);
					}

					// Call load_callback
					if (is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['load_callback'])) {
						foreach ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['load_callback'] as $callback) {
							if (is_array($callback)) {
								$this->import($callback[0]);
								$this->varValue = $this->$callback[0]->$callback[1]($this->varValue, $this);
							}
						}
					}

					// Build the row and pass the current palette string (thanks to Tristan Lins)
					$blnAjax ? $strAjax .= $this->row($this->strPalette) : $return .= $this->row($this->strPalette);

				}

				$class = 'tl_box';
				$return .= "\n" . '</fieldset>';

			}

		}

		// Add some buttons and end the form
		$return .= '</div>'
		         . '<div class="tl_formbody_submit">'
		         . '<div class="tl_submit_container">'
		         . '<input type="submit" name="save" id="save" class="tl_submit" accesskey="s" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['save']).'">'
		         . '<input type="submit" name="saveNclose" id="saveNclose" class="tl_submit" accesskey="c" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['saveNclose']).'">'
		         . '</div>'
		         . '</div>'
		         . '</form>'
		         . '<script>'
		         . '	window.addEvent(\'domready\', function() {'
		         . '		(inp = $(\''.$this->strTable.'\').getElement(\'input[class^="tl_text"]\')) && inp.focus();'
		         . '	});'
		         . '</script>';

		// Begin the form (-> DO NOT CHANGE THIS ORDER -> this way the onsubmit attribute of the form can be changed by a field)
		$return = $version
		        . '<div id="tl_buttons">'
		        . '<a href="'.$this->getReferer(true).'" class="header_back" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']).'" accesskey="b" onclick="Backend.getScrollOffset()">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>'
		        . '</div>'
		        . '<h2 class="sub_headline">'.sprintf($GLOBALS['TL_LANG']['MSC']['editRecord'], ($this->intId ? $this->intId : '')).'</h2>'
		        . \Message::generate()
		        . '<form action="'.ampersand(\Environment::get('request'), true).'" id="'.$this->strTable.'" class="tl_form" method="post" enctype="' . ($this->blnUploadable ? 'multipart/form-data' : 'application/x-www-form-urlencoded') . '"'.(!empty($this->onsubmit) ? ' onsubmit="'.implode(' ', $this->onsubmit).'"' : '').'>'
		        . '<div class="tl_formbody_edit">'
		        . '<input type="hidden" name="FORM_SUBMIT" value="'.specialchars($this->strTable).'">'
		        . '<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">'
		        . '<input type="hidden" name="FORM_FIELDS[]" value="'.specialchars($this->strPalette).'">'
		        . ($this->noReload ? '<p class="tl_error">' . $GLOBALS['TL_LANG']['ERR']['general'] . '</p>' : '')
		        . $return;

		// Reload the page to prevent _POST variables from being sent twice
		if (\Input::post('FORM_SUBMIT') == $this->strTable && !$this->noReload) {

			// Trigger the onsubmit_callback
			if (is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'])) {
				foreach ($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'] as $callback) {
					$this->import($callback[0]);
					$this->$callback[0]->$callback[1]($this);
				}
			}

			// Redirect
			if (isset($_POST['saveNclose'])) {
				\Message::reset();
				setcookie('BE_PAGE_OFFSET', 0, 0, '/');
				$this->redirect($this->getReferer());
			}

			$this->reload();

		}

		// Set the focus if there is an error
		if ($this->noReload) {
			$return .= '<script>'
			         . 'window.addEvent(\'domready\', function() {'
			         . '	Backend.vScrollTo(($(\'' . $this->strTable . '\').getElement(\'label.error\').getPosition().y - 20));'
			         . '});'
			         . '</script>';
		}

		return $return;
	}

	protected function isValid($strFile)
	{
		if (strpos($strFile, '../') !== false) {
			$this->log('Invalid file name "' . $strFile . '" (hacking attempt)', 'DC_Folder isValid()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		// Check whether the file is within the files directory
		if (!preg_match('/^('.preg_quote($GLOBALS['TL_CONFIG']['uploadPath'], '/').'|templates)/i', $strFile)) {
			$this->log('File or folder "'.$strFile.'" is not within the files directory', 'DC_Folder isValid()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		return true;
	}

	public function getPalette()
	{
		return $GLOBALS['TL_DCA'][$this->strTable]['palettes']['default'];
	}

	public function delete()
	{
	}

	public function show()
	{
	}

	public function undo()
	{
	}

	public function create()
	{
	}

	public function cut()
	{
	}

	public function copy()
	{
	}

	public function move()
	{
	}

	public function save()
	{
	}
}
