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

$GLOBALS['BE_MOD']['design']['rocksolid_theme_assistant'] = array(
	'tables' => array('rocksolid_theme_assistant'),
	'icon'   => 'system/modules/rocksolid-theme-assistant/assets/images/icon.png',
);

$GLOBALS['BE_FFL']['mydMultiListWizard'] = 'MadeYourDay\\Contao\\Widget\\MultiListWizard';
