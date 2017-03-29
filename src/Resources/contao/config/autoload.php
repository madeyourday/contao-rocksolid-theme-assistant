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

ClassLoader::addClasses(array(
	'DC_RockSolidThemeAssistant'                       => 'system/modules/rocksolid-theme-assistant/src/DC_RockSolidThemeAssistant.php',
	'MadeYourDay\\RockSolidThemeAssistant\\ThemeAssistant'              => 'system/modules/rocksolid-theme-assistant/src/MadeYourDay/Contao/ThemeAssistant.php',
	'MadeYourDay\\RockSolidThemeAssistant\\ThemeAssistantDataContainer' => 'system/modules/rocksolid-theme-assistant/src/MadeYourDay/Contao/ThemeAssistantDataContainer.php',
	'MadeYourDay\\RockSolidThemeAssistant\\Widget\\MultiListWizard'     => 'system/modules/rocksolid-theme-assistant/src/MadeYourDay/Contao/Widget/MultiListWizard.php',
));
