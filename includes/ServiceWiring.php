<?php

use MediaWiki\Extension\MathSearch\Ipfs\IpfsClient;
use MediaWiki\Extension\MathSearch\Keys\KeyStore;
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
	'MathSearch.IpfsClient' => static function ( MediaWikiServices $services ): IpfsClient {
		return new IpfsClient(
			$services->getHttpRequestFactory()->createGuzzleClient(),
			$services->getMainConfig()->get( 'MathSearchIpfsApi' )
		);
	},
	'MathSearch.KeyStore' => static function ( MediaWikiServices $services ): KeyStore {
		return new KeyStore(
			$services->getConnectionProvider(),
			$services->getUserIdentityLookup(),
			$services->getPermissionManager()
		);
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
