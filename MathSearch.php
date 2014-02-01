<?php
/**
 * MediaWiki math search extension
 *
 * @file
 * @ingroup Extensions
 * @version 0.1
 * @author Moritz Schubotz
 * @author Brion Vibber
 * @copyright © 2002-2012 various MediaWiki contributors
 * @license GPLv2 license; info in main package.
 * @link http://www.mediawiki.org/wiki/Extension:MathSearch Documentation
 */

# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is not a valid entry point to MediaWiki.\n'
		. 'To install my extension, put the following line in LocalSettings.php:\n'
		. 'require_once( \"\$IP/extensions/MathSearch/MathSearch.php\" );' );
}

$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'MathSearch',
	'author' => 'Moritz Schubotz',
	'url' => 'https://www.mediawiki.org/wiki/Extension:MathSearch',
	'descriptionmsg' => 'mathsearch-desc',
	'version' => '0.1.0',
);

$wgMWSUrl = 'http://localhost:9090/';
$dir = dirname( __FILE__ ) . '/';

$wgAutoloadClasses['MathSearchHooks'] = $dir . 'MathSearch.hooks.php';
$wgAutoloadClasses['SpecialMathSearch'] = $dir . 'SpecialMathSearch.php';
$wgAutoloadClasses['FormulaInfo'] = $dir . 'FormulaInfo.php';
$wgAutoloadClasses['MathObject'] = $dir . 'MathObject.php';
$wgAutoloadClasses['MathQueryObject'] = $dir . 'MathQueryObject.php';
$wgAutoloadClasses['XQueryGenerator'] = $dir . 'XQueryGenerator.php';
$wgAutoloadClasses['XQueryGeneratorDB2'] = $dir . 'XQueryGeneratorDB2.php';
$wgAutoloadClasses['XQueryGeneratorBaseX'] = $dir . 'XQueryGeneratorBaseX.php';
$wgAutoloadClasses['GetEquationsByQuery'] = $dir . 'GetEquationsByQuery.php';
$wgAutoloadClasses['SpecialMathDebug'] = $dir . 'SpecialMathDebug.php';
$wgAutoloadClasses['SpecialMathIndex'] = $dir . 'SpecialMathIndex.php';
$wgAutoloadClasses['MathEngineMws'] = $dir . 'MathEngineMws.php';

$wgExtensionMessagesFiles['MathSearch'] = $dir . 'MathSearch.i18n.php';
$wgExtensionMessagesFiles['MathSearchAlias'] = $dir . 'MathSearch.alias.php';

$wgSpecialPageGroups['MathSearch'] = 'mathsearch';
$wgSpecialPageGroups['FormulaInfo'] = 'mathsearch';
$wgSpecialPageGroups['GetEquationsByQuery'] = 'mathsearch';
$wgSpecialPageGroups['XQueryGenerator'] = 'mathsearch';
$wgSpecialPageGroups['MathDebug'] = 'mathsearch';
$wgSpecialPageGroups['MathIndex'] = 'mathsearch';
$wgSpecialPages['MathSearch'] = 'SpecialMathSearch';
$wgSpecialPages['FormulaInfo'] = 'FormulaInfo';
$wgSpecialPages['GetEquationsByQuery'] = 'GetEquationsByQuery';
$wgSpecialPages['MathDebug'] = 'SpecialMathDebug';
$wgSpecialPages['MathIndex'] = 'SpecialMathIndex';

$wgHooks['LoadExtensionSchemaUpdates'][] = 'MathSearchHooks::onLoadExtensionSchemaUpdates';
$wgHooks['MathFormulaRendered'][] = 'MathSearchHooks::onMathFormulaRendered';

$wgGroupPermissions['user']['MathDebug'] = true;

$wgMathSearchDB2Table = 'wiki.math';