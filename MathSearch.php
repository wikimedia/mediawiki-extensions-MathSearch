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
/** @var String URL of MathWebSearch instance */
$wgMathSearchMWSUrl = 'http://localhost:9090/';
/** @var boolean if true the observation is updated everytime the SpecialPage formulainfo is shown. */
$wgMathUpdateObservations = false;
/** @var string $wgMathAnalysisTableName mathoid or mathlatexml */
$wgMathAnalysisTableName = 'mathlatexml';

$wgAutoloadClasses['MathSearchHooks'] = __DIR__ . '/MathSearch.hooks.php';
$wgAutoloadClasses['SpecialMathSearch'] = __DIR__ . '/includes/special/SpecialMathSearch.php';
$wgAutoloadClasses['FormulaInfo'] = __DIR__ . '/FormulaInfo.php';
$wgAutoloadClasses['MathObject'] = __DIR__ . '/MathObject.php';
$wgAutoloadClasses['MathQueryObject'] = __DIR__ . '/MathQueryObject.php';
$wgAutoloadClasses['GetEquationsByQuery'] = __DIR__ . '/GetEquationsByQuery.php';
$wgAutoloadClasses['SpecialMathDebug'] = __DIR__ . '/SpecialMathDebug.php';
$wgAutoloadClasses['SpecialMathIndex'] = __DIR__ . '/SpecialMathIndex.php';
$wgAutoloadClasses['SpecialDisplayTopics'] = __DIR__ . '/includes/special/SpecialDisplayTopics.php';
$wgAutoloadClasses['MathEngineMws'] = __DIR__ . '/includes/engines/MathEngineMws.php';
$wgAutoloadClasses['MathEngineBaseX'] = __DIR__ . '/includes/engines/MathEngineBaseX.php';
$wgAutoloadClasses['MathEngineRest'] = __DIR__ . '/includes/engines/MathEngineRest.php';
$wgAutoloadClasses['ImportCsv'] = __DIR__ . '/includes/ImportCsv.php';
$wgAutoloadClasses['MathSearchUtils'] = __DIR__ . '/includes/MathSearchUtils.php';
$wgAutoloadClasses['MathSearchTerm'] = __DIR__ . '/includes/MathSearchTerm.php';
$wgAutoloadClasses['MwsDumpWriter'] = __DIR__ . '/includes/MwsDumpWriter.php';
$wgAutoloadClasses['SpecialLaTeXTranslator'] = __DIR__ . '/includes/special/SpecialLaTeXTranslator.php';
$wgAutoloadClasses['LaTeXTranslator'] = __DIR__ . '/includes/LaTeXTranslator.php';

$wgMessagesDirs['MathSearch'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['MathSearchAlias'] = __DIR__ . '/MathSearch.alias.php';

$wgSpecialPages['MathSearch'] = 'SpecialMathSearch';
$wgSpecialPages['FormulaInfo'] = 'FormulaInfo';
$wgSpecialPages['GetEquationsByQuery'] = 'GetEquationsByQuery';
$wgSpecialPages['MathDebug'] = 'SpecialMathDebug';
$wgSpecialPages['MathIndex'] = 'SpecialMathIndex';
$wgSpecialPages['DisplayTopics'] = 'SpecialDisplayTopics';
$wgSpecialPages['LaTeXTranslator'] = 'SpecialLaTeXTranslator';

$wgHooks['LoadExtensionSchemaUpdates'][] = 'MathSearchHooks::onLoadExtensionSchemaUpdates';
$wgHooks['MathFormulaPostRender']['updateIndex'] = 'MathSearchHooks::updateMathIndex';
$wgHooks['MathFormulaPostRender']['addLink'] = 'MathSearchHooks::addLinkToFormulaInfoPage';
$wgHooks['UnitTestsList'][] = 'MathSearchHooks::onRegisterUnitTests';
$wgHooks['ParserFirstCallInit'][] = 'MathSearchHooks::onParserFirstCallInit';
$wgHooks['ArticleDeleteComplete'][] = 'MathSearchHooks::onArticleDeleteComplete';
$wgHooks['PageContentSaveComplete'][] = 'MathSearchHooks::onPageContentSaveComplete';

$wgMathSearchBaseXBackendUrl = 'http://localhost:10043/';

/* Optional stuff for math search competetion server */
$wgMathWmcServer = false;
$wgGroupPermissions['sysop']['mathwmcsubmit'] = true;
$wgAvailableRights[] = 'mathwmcsubmit';
/** @var boolean $MathSearchWmcServer set true if you offer a math search competition server */
$wgMathWmcMaxResults = 10000;
$wgAutoloadClasses['SpecialUploadResult'] = __DIR__ . '/SpecialUploadResult.php';
$wgSpecialPages['MathUpload'] = 'SpecialUploadResult';
$wgMathUploadEnabled = false;
$wgAutoloadClasses['SpecialMathDownloadResult'] = __DIR__ . '/SpecialMathDownloadResult.php';
$wgSpecialPages['MathDownload'] = 'SpecialMathDownloadResult';

$wgResourceModules['ext.mathsearch.styles'] = array(
	'localBasePath' => __DIR__ ,
	'remoteExtPath' => 'MathSearch/',
	'styles' => 'ext.mathsearch.css',
	'targets' => array( 'desktop', 'mobile' ),
);
