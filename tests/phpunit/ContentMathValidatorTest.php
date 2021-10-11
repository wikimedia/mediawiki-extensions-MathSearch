<?php

use DataValues\NumberValue;
use DataValues\StringValue;

/**
 * @covers ContentMathValidator
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class ContentMathValidatorTest extends MediaWikiIntegrationTestCase {

	private const VADLID_TEX = "a^2+b^2=c^2";

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( 'wgMathDisableTexFilter', 'always' );
	}

	public function testNotStringValue() {
		$validator = new ContentMathValidator();
		$this->expectException( InvalidArgumentException::class );
		$validator->validate( new NumberValue( 0 ) );
	}

	public function testNullValue() {
		$validator = new ContentMathValidator();
		$this->expectException( InvalidArgumentException::class );
		$validator->validate( null );
	}

	public function testValidInput() {
		$validator = new ContentMathValidator();
		$result = $validator->validate( new StringValue( self::VADLID_TEX ) );
		$this->assertInstanceOf( \ValueValidators\Result::class, $result );
		$this->assertTrue( $result->isValid() );
	}
}
