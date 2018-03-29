<?php

use DataValues\StringValue;
use DataValues\NumberValue;

/**
 * @covers MathValidator
 *
 * @group ContentContentMath
 *
 * @license GNU GPL v2+
 */
class ContentMathValidatorTest extends MediaWikiTestCase {
	const VADLID_TEX = "a^2+b^2=c^2";

	protected function setUp() {
		parent::setUp();
		$this->setMwGlobals( 'wgMathDisableTexFilter', 'always' );
	}

	/**
	 * @expectedException ValueFormatters\Exceptions\MismatchingDataValueTypeException
	 */
	public function testNotStringValue() {
		$validator = new ContentMathValidator();
		$validator->validate( new NumberValue( 0 ) );
	}

	/**
	 * @expectedException ValueFormatters\Exceptions\MismatchingDataValueTypeException
	 */
	public function testNullValue() {
		$validator = new ContentMathValidator();
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
