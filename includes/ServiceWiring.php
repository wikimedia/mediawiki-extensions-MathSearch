<?php

use MediaWiki\Extension\MathSearch\Rest\FormulaStore;
use MediaWiki\Extension\MathSearch\Rest\SvgRenderer;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// @codeCoverageIgnoreStart

return [
	'MathSearch.FormulaStore' => static function ( MediaWikiServices $services ): FormulaStore {
		return new FormulaStore( $services->getConnectionProvider() );
	},
	'MathSearch.SvgRenderer' => static function ( MediaWikiServices $services ): SvgRenderer {
		$config = $services->getMainConfig();
		return new SvgRenderer(
			$config->get( MainConfigNames::Server ),
			$config->get( MainConfigNames::ScriptPath ),
			MW_INSTALL_PATH
		);
	},
];

// @codeCoverageIgnoreEnd
