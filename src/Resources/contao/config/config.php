<?php
/*
 * Copyright MADE/YOUR/DAY <mail@madeyourday.co>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * RockSolid Theme Assistant back end modules configuration
 *
 * @author Martin Auswöger <martin@madeyourday.co>
 */

// Backwards compatibility for Contao < 3.5.1
if (!class_exists('StringUtil') && class_exists('String')) {
	class_alias('String', 'StringUtil');
}

$GLOBALS['TL_HOOKS']['executePostActions'][] = array('MadeYourDay\\RockSolidThemeAssistant\\ThemeAssistant', 'executePostActionsHook');

$GLOBALS['BE_MOD']['design']['rocksolid_theme_assistant'] = array(
	'tables' => array('rocksolid_theme_assistant'),
	'icon'   => (version_compare(VERSION, '4.0', '>=')
		? 'bundles/rocksolidthemeassistant'
		: 'system/modules/rocksolid-theme-assistant/assets'
	) . '/images/icon.png',
);

$GLOBALS['BE_FFL']['mydMultiListWizard'] = 'MadeYourDay\\RockSolidThemeAssistant\\Widget\\MultiListWizard';
