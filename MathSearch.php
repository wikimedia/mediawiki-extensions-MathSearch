<?php
/**
 * MediaWiki math search extension
 *
 * @file
 * @ingroup Extensions
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
	'version' => '0.2.0',
);
/** @var String the IBM DB connection string*/
$wgMathSearchDB2ConnStr = false;
/** @var String URL of MathWebSearch instance */
$wgMathSearchMWSUrl = 'http://localhost:9090/';
/** @var boolean if true the observation is updated everytime the SpecialPage formulainfo is shown. */
$wgMathUpdateObservations = false;
/** @var string $wgMathAnalysisTableName mathoid or mathlatexml */
$wgMathAnalysisTableName = 'mathlatexml';

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
$wgAutoloadClasses['MathEngineMws'] = $dir . '/includes/engines/MathEngineMws.php';
$wgAutoloadClasses['MathEngineDB2'] = $dir . '/includes/engines/MathEngineDB2.php';
$wgAutoloadClasses['MathEngineBaseX'] = $dir . '/includes/engines/MathEngineBaseX.php';
$wgAutoloadClasses['MathEngineRest'] = $dir . '/includes/engines/MathEngineRest.php';
$wgAutoloadClasses['MathSearchApi'] = $dir . 'MathSearchApi.php';
$wgAutoloadClasses['ImportCsv'] = $dir . 'includes/ImportCsv.php';
$wgAutoloadClasses['MathSearchUtils'] = $dir . 'includes/MathSearchUtils.php';

$wgMessagesDirs['MathSeach'] = __DIR__ . '/i18n';
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
$wgAPIModules['mathquery'] = 'MathSearchApi';

$wgHooks['LoadExtensionSchemaUpdates'][] = 'MathSearchHooks::onLoadExtensionSchemaUpdates';
$wgHooks['MathFormulaRendered']['updateIndex'] = 'MathSearchHooks::updateMathIndex';
$wgHooks['MathFormulaRendered']['addLink'] = 'MathSearchHooks::addLinkToFormulaInfoPage';
$wgHooks['UnitTestsList'][] = 'MathSearchHooks::onRegisterUnitTests';

$wgGroupPermissions['user']['MathDebug'] = true;

$wgMathSearchDB2Table = 'math';

$wgMathSearchBaseXBackendUrl = 'http://localhost:10043/mwsquery';

/* Optional stuff for math search competetion server */
$wgMathWmcServer = false;
$wgGroupPermissions['sysop']['mathwmcsubmit'] = true;
$wgAvailableRights[] = 'mathwmcsubmit';
/** @var boolean $MathSearchWmcServer set true if you offer a math search competition server */
$wgMathWmcMaxResults = 10000;
$wgAutoloadClasses['SpecialUploadResult'] = $dir . 'SpecialUploadResult.php';
$wgSpecialPages['MathUpload'] = 'SpecialUploadResult';
$wgSpecialPageGroups['MathUpload'] = 'mathsearch';
$wgMathUploadEnabled = false;
$wgAutoloadClasses['SpecialMathDownloadResult'] = $dir . 'SpecialMathDownloadResult.php';
$wgSpecialPages['MathDownload'] = 'SpecialMathDownloadResult';
$wgSpecialPageGroups['MathDownload'] = 'mathsearch';