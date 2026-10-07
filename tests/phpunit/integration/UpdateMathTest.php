<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration;

require_once dirname( __DIR__, 3 ) . '/maintenance/UpdateMath.php';

use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MockHttpTrait;
use UpdateMath;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

/**
 * @covers \UpdateMath
 * @group Database
 */
class UpdateMathTest extends MaintenanceBaseTestCase {

	use MockHttpTrait;

	protected function getMaintenanceClass() {
		return UpdateMath::class;
	}

	public function testNativeIsTheDefaultMode() {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		$this->editPage( 'Mass energy', '<math>E=mc^2</math>' );

		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();

		// editPage() already stored the formula in the default user mode
		$this->newSelectQueryBuilder()
			->select( 'math_mode' )
			->from( 'mathlog' )
			->where( [ 'math_mode' => 8 ] )
			->assertFieldValue( 8 );
		$this->expectOutputRegex( '/processing 1 math fields for Mass_energy page(?!.*F:)/s' );
	}

	public function testFormulaNotOnThePageIsStored() {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		// The template does not exist, so its parameter and the formula in it are not shown
		$this->editPage( 'Hidden', '{{Missing template|<math>z^3</math>}}' );

		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( 'math_mode' )
			->from( 'mathlog' )
			->where( [ 'math_input' => 'z^3' ] )
			->assertFieldValue( 8 );
		$this->expectOutputRegex( '/0 failed/' );
	}

	public function testFailedTexCheckIsStored() {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		$this->editPage( 'Broken', '<math>\\frac{</math>' );

		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'math_input', 'math_statuscode' ] )
			->from( 'mathlog' )
			->where( $this->getDb()->expr( 'math_statuscode', '>', 0 ) )
			->assertResultSet( [ [ '\\frac{', 1 ] ] );
		$this->expectOutputRegex( '/1 failed \\(see mathlog\\)/' );
	}

	public function testFailedRenderingIsReported() {
		$this->overrideConfigValues( [
			'MathValidModes' => [ 'source', 'native', 'latexml' ],
			// Keeps BaseX out of the test
			'MathSearchMode' => 'mathml',
			MainConfigNames::HTTPTimeout => 1,
		] );
		$this->editPage( 'Mass energy', '<math>E=mc^2</math>' );
		$this->installMockHttp( $this->makeFakeHttpRequest( '404 page not found', 404 ) );

		$this->maintenance->loadWithArgv( [ '--mode', 'latexml' ] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'math_input', 'math_statuscode' ] )
			->from( 'mathlog' )
			->where( $this->getDb()->expr( 'math_log', IExpression::LIKE,
				new LikeValue( $this->getDb()->anyString(), '404', $this->getDb()->anyString() ) ) )
			->assertResultSet( [ [ 'E=mc^2', 2 ] ] );
		$this->expectOutputRegex( '/1 failed \(see mathlog\)/' );
	}

	public function testUnclosedTagIsStored() {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		$this->editPage( 'Unclosed', 'Text <math>x^2 and the rest of the page' );

		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'math_input', 'math_statuscode', 'math_log' ] )
			->from( 'mathlog' )
			->where( $this->getDb()->expr( 'math_statuscode', '>', 0 ) )
			->assertResultSet( [ [ 'x^2 and the rest of the page', 4, 'unclosed tag' ] ] );
		$this->expectOutputRegex( '/1 failed \\(see mathlog\\)/' );
	}

	public function testTooLongInputIsNotStored() {
		$this->overrideConfigValues( [
			'MathValidModes' => [ 'source', 'native' ],
			'MathSearchContentTexMaxLength' => 5,
		] );
		$this->editPage( 'Long', '<math>a+b+c+d</math>' );

		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'math_input', 'math_statuscode', 'math_log' ] )
			->from( 'mathlog' )
			->where( [ 'math_statuscode' => 8 ] )
			->assertResultSet( [ [ null, 8, '7 characters' ] ] );
		$this->expectOutputRegex( '/1 failed \\(see mathlog\\)/' );
	}

	public function testUnclosedAndTooLongAddUp() {
		$this->overrideConfigValues( [
			'MathValidModes' => [ 'source', 'native' ],
			'MathSearchContentTexMaxLength' => 5,
		] );
		$this->editPage( 'Both', '<math>a+b+c+d' );

		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'math_input', 'math_statuscode', 'math_log' ] )
			->from( 'mathlog' )
			->where( $this->getDb()->expr( 'math_statuscode', '>', 0 ) )
			->assertResultSet( [ [ null, 12, 'unclosed tag, 7 characters' ] ] );
		$this->expectOutputRegex( '/1 failed \\(see mathlog\\)/' );
	}
}
