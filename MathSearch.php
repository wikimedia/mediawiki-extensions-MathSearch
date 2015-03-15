<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'MathSearch' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['MathSearch'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['MathSearchAlias'] = __DIR__ . '/MathSearch.alias.php';
	/* wfWarn(
		'Deprecated PHP entry point used for MathSearch extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
}