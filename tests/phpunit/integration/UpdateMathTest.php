<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration;

require_once dirname( __DIR__, 3 ) . '/maintenance/UpdateMath.php';

use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MockHttpTrait;
use Symfony\Component\Yaml\Yaml;
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
			->assertResultSet( [ [ '\\frac{', ord( 'T' ) ] ] );
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
			->assertResultSet( [ [ 'E=mc^2', ord( 'R' ) ] ] );
		$this->expectOutputRegex( '/1 failed \(see mathlog\)/' );
	}

	public function testTooLongInputIsNotStored() {
		$this->overrideConfigValues( [
			'MathValidModes' => [ 'source', 'native' ],
			'MathSearchContentTexMaxLength' => 5,
		] );
		$this->editPage( 'Long', "<math>a+b\n+c+d</math>" );
		$yaml = $this->getNewTempFile();

		$this->maintenance->loadWithArgv( [ '--long-inputs', $yaml ] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'math_input', 'math_statuscode' ] )
			->from( 'mathlog' )
			->where( $this->getDb()->expr( 'math_statuscode', '>', 0 ) )
			->assertResultSet( [ [ null, ord( 'L' ) ] ] );
		$this->assertSame( [ md5( "a+b\n+c+d" ) => "a+b\n+c+d" ], Yaml::parseFile( $yaml ) );
		$this->expectOutputRegex( '/1 failed \\(see mathlog\\)/' );
	}

	public function testLongInputIsRecordedOnce() {
		$this->overrideConfigValues( [
			'MathValidModes' => [ 'source', 'native' ],
			'MathSearchContentTexMaxLength' => 5,
		] );
		$this->editPage( 'First', '<math>a+b+c+d</math>' );
		$this->editPage( 'Second', '<math>a+b+c+d</math>' );
		$yaml = $this->getNewTempFile();

		$this->maintenance->loadWithArgv( [ '--long-inputs', $yaml ] );
		$this->maintenance->execute();

		$this->assertSame( [ md5( 'a+b+c+d' ) => 'a+b+c+d' ], Yaml::parseFile( $yaml ) );
		$this->expectOutputRegex( '/2 failed \\(see mathlog\\)/' );
	}
}
