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

	public function testChunkStartingAtTheLastRevision() {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		$revId = $this->editPage( 'Last', '<math>E=mc^2</math>' )->getNewRevision()->getId();

		$this->maintenance->loadWithArgv( [ '--chunk-size', '1', (string)$revId, (string)$revId ] );
		$this->maintenance->execute();

		$this->expectOutputRegex( '/processing 1 math fields for Last page/' );
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

	public function testAttributesAreStored() {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		$this->editPage( 'Attributes', '<math id="eq1" display="block">x^2</math> <math>y</math>' );

		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'math_input', 'math_params' ] )
			->from( 'mathlog' )
			->distinct()
			->orderBy( 'math_input' )
			->assertResultSet( [
				[ 'y', '{}' ],
				[ '{\\displaystyle x^2}', '{"id":"eq1","display":"block"}' ],
			] );
		$rows = $this->getDb()->newSelectQueryBuilder()
			->select( [ 'math_inputhash', 'math_input', 'math_params' ] )
			->from( 'mathlog' )
			->caller( __METHOD__ )
			->fetchResultSet();
		foreach ( $rows as $row ) {
			$this->assertSame( $row->math_inputhash,
				md5( 'native' . $row->math_input . implode( json_decode( $row->math_params, true ) ) ) );
		}
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

	public function testInputLongerThanTextIsStored() {
		$this->overrideConfigValues( [
			'MathValidModes' => [ 'source', 'native' ],
			'MaxArticleSize' => 100,
			'ParsoidSettings' => [ 'wt2htmlLimits' => [ 'wikitextSize' => 100 * 1024 ] ],
		] );
		// Longer than the 65,535 bytes of a TEXT column
		$tex = '\\frac{\\text{' . str_repeat( 'x', 70000 ) . '}';
		$this->editPage( 'Long', "<math>$tex</math>" );

		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'math_input', 'math_tex', 'math_statuscode', 'LENGTH(math_log)' ] )
			->from( 'mathlog' )
			->where( $this->getDb()->expr( 'math_statuscode', '>', 0 ) )
			->assertResultSet( [ [ $tex, '', ord( 'T' ), 65535 ] ] );
		$this->expectOutputRegex( '/1 failed \\(see mathlog\\)/' );
	}
}
