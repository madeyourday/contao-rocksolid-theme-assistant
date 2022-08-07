<?php
/*
 * Copyright MADE/YOUR/DAY <mail@madeyourday.co>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * RockSolid Theme Assistant DCA
 *
 * @author Martin Auswöger <martin@madeyourday.co>
 */

use MadeYourDay\RockSolidThemeAssistant\ThemeAssistantDataContainer;

$GLOBALS['TL_DCA']['rocksolid_theme_assistant'] = array(

	'config' => array(
		'dataContainer' => ThemeAssistantDataContainer::class,
		'onload_callback' => array(
			array('MadeYourDay\\RockSolidThemeAssistant\\ThemeAssistant', 'onloadCallback'),
		),
		'onsubmit_callback' => array(
			array('MadeYourDay\\RockSolidThemeAssistant\\ThemeAssistant', 'onsubmitCallback'),
		),
	),

	'list' => array(
		'label' => array(
			'label_callback' => array('MadeYourDay\\RockSolidThemeAssistant\\ThemeAssistant', 'listLabelCallback'),
		),
		'operations' => array(
			'edit' => array(
				'label'           => &$GLOBALS['TL_LANG']['rocksolid_theme_assistant']['edit'],
				'href'            => 'act=edit',
				'icon'            => 'edit.gif',
				'button_callback' => array('MadeYourDay\\RockSolidThemeAssistant\\ThemeAssistant', 'editButtonCallback'),
			),
		),
	),

	'palettes' => array(
		'default' => '',
	),

	'fields' => array(),

);
