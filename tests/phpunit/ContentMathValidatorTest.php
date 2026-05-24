<?php

use DataValues\NumberValue;
use DataValues\StringValue;
use MediaWiki\Extension\MathSearch\Wikidata\Content\ContentMathValidator;
use MediaWiki\Registration\ExtensionRegistry;
use ValueValidators\Result;

/**
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class ContentMathValidatorTest extends MediaWikiIntegrationTestCase {

	private const VADLID_TEX = "a^2+b^2=c^2";

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseClient' ) &&
			!ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' )
		) {
			$this->markTestSkipped( "Extension WikibaseClient or WikibaseRepository are required for this test" );
		}

		$this->overrideConfigValue( 'MathDisableTexFilter', 'always' );
	}

	/**
	 * @covers MediaWiki\Extension\MathSearch\Wikidata\Content\ContentMathValidator::validate
	 */
	public function testNotStringValue() {
		$validator = new ContentMathValidator();
		$this->expectException( InvalidArgumentException::class );
		$validator->validate( new NumberValue( 0 ) );
	}

	/**
	 * @covers MediaWiki\Extension\MathSearch\Wikidata\Content\ContentMathValidator::validate
	 */
	public function testNullValue() {
		$validator = new ContentMathValidator();
		$this->expectException( InvalidArgumentException::class );
		$validator->validate( null );
	}

	/**
	 * @covers MediaWiki\Extension\MathSearch\Wikidata\Content\ContentMathValidator::validate
	 */
	public function testValidInput() {
		$validator = new ContentMathValidator();
		$result = $validator->validate( new StringValue( self::VADLID_TEX ) );
		$this->assertInstanceOf( Result::class, $result );
		$this->assertTrue( $result->isValid() );
	}
}
