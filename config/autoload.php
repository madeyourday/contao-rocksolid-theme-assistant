<?php
/*
 * Copyright MADE/YOUR/DAY <mail@madeyourday.co>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * RockSolid Theme Assistant autload configuration
 *
 * @author Martin Auswöger <martin@madeyourday.co>
 */

ClassLoader::addNamespaces(array(
	'MadeYourDay',
	'MadeYourDay\\Contao',
));

ClassLoader::addClasses(array(
	'DC_RockSolidThemeAssistant'                       => 'system/modules/rocksolid-theme-assistant/src/DC_RockSolidThemeAssistant.php',
	'MadeYourDay\\Contao\\ThemeAssistant'              => 'system/modules/rocksolid-theme-assistant/src/MadeYourDay/Contao/ThemeAssistant.php',
	'MadeYourDay\\Contao\\ThemeAssistantDataContainer' => 'system/modules/rocksolid-theme-assistant/src/MadeYourDay/Contao/ThemeAssistantDataContainer.php',
	'MadeYourDay\\Contao\\Widget\\MultiListWizard'     => 'system/modules/rocksolid-theme-assistant/src/MadeYourDay/Contao/Widget/MultiListWizard.php',
));
