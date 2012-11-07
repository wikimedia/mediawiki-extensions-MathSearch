<?php
	# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/MathSearch/MathSearch.php" );
EOT;
        exit( 1 );
}

$wgExtensionCredits['specialpage'][] = array(
        'path' => __FILE__,
        'name' => 'MathSearch',
        'author' => 'Moritz Schubotz',
        'url' => 'https://www.mediawiki.org/wiki/Extension:MathSearch',
        'descriptionmsg' => 'mathsearch-desc',
        'version' => '0.0.0',
);

$dir = dirname( __FILE__ ) . '/';
$wgAutoloadClasses['MathSearchHooks'] = $dir . 'MathSearch.hooks.php';
$wgAutoloadClasses['SpecialMathSearch'] = $dir . 'SpecialMathSearch.php'; # Location of the SpecialMathSearch class (Tell MediaWiki to load this file)
$wgExtensionMessagesFiles['MathSearch'] = $dir . 'MathSearch.i18n.php'; # Location of a messages file (Tell MediaWiki to load this file)
$wgExtensionMessagesFiles['MathSearchAlias'] = $dir . 'MathSearch.alias.php'; # Location of an aliases file (Tell MediaWiki to load this file)
$wgSpecialPages['MathSearch'] = 'SpecialMathSearch'; # Tell MediaWiki about the new special page and its class name

$wgHooks['LoadExtensionSchemaUpdates'][] = 'MathSearchHooks::onLoadExtensionSchemaUpdates';
$wgHooks['MathFormulaRendered'][] = 'MathSearchHooks::onMathFormulaRendered';