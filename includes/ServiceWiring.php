<?php

use MediaWiki\Extension\MathSearch\Rest\FormulaStore;
use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// @codeCoverageIgnoreStart

return [
	'MathSearch.FormulaStore' => static function ( MediaWikiServices $services ): FormulaStore {
		return new FormulaStore( $services->getConnectionProvider() );
	},
];

// @codeCoverageIgnoreEnd
