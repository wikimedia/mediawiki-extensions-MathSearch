<?php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';
$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/Math',
		'../../extensions/Wikibase/client',
		'../../extensions/Wikibase/repo',
		'../../extensions/Wikibase/lib',
	]
);
$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/Math',
		'../../extensions/Wikibase/client',
		'../../extensions/Wikibase/repo',
		'../../extensions/Wikibase/lib',
		'includes', // see T255945
		'maintenance' // see T255946
	]
);
return $cfg;