<?php

use DataValues\StringValue;
use DataValues\NumberValue;
use ValueFormatters\Exceptions\MismatchingDataValueTypeException;

/**
 * @covers MathValidator
 *
 * @group ContentContentMath
 *
 * @license GPL-2.0-or-later
 */
class ContentMathValidatorTest extends MediaWikiTestCase {
	const VADLID_TEX = "a^2+b^2=c^2";

	protected function setUp() {
		parent::setUp();
		$this->setMwGlobals( 'wgMathDisableTexFilter', 'always' );
	}

	public function testNotStringValue() {
		$validator = new ContentMathValidator();
		$this->expectException( MismatchingDataValueTypeException::class );
		$validator->validate( new NumberValue( 0 ) );
	}

	public function testNullValue() {
		$validator = new ContentMathValidator();
		$this->expectException( MismatchingDataValueTypeException::class );
		$validator->validate( null );
	}

	public function testValidInput() {
		$validator = new ContentMathValidator();
		$result = $validator->validate( new StringValue( self::VADLID_TEX ) );
		// not supported by jenkins php version
		// $this->assertType( \ValueValidators\Result::class, $result );
		$this->assertTrue( $result->isValid() );
	}
}
