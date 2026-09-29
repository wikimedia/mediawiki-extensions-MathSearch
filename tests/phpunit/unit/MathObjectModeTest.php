<?php

use MediaWiki\Extension\Math\MathConfig;

/**
 * @covers \MathObject
 */
class MathObjectModeTest extends MediaWikiUnitTestCase {

	public static function provideModes(): array {
		return array_map( static fn ( $mode ) => [ $mode ], MathConfig::SUPPORTED_MODES );
	}

	/**
	 * @dataProvider provideModes
	 */
	public function testModeMatchesMathUserOption( string $mode ) {
		$this->assertArrayHasKey( $mode, MathObject::MODE_2_USER_OPTION );
		$this->assertSame(
			$mode,
			MathConfig::normalizeRenderingMode( MathObject::MODE_2_USER_OPTION[$mode], '' )
		);
	}
}
