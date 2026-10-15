<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration;

require_once dirname( __DIR__, 3 ) . '/maintenance/UpdateMath.php';

use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MockHttpTrait;
use UpdateMath;
use Wikimedia\Rdbms\IDatabase;
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

	public function testCheckedTexIsStored() {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		$this->editPage( 'Square', '<math>a^2</math>' );

		$this->maintenance->loadWithArgv( [ '--mode', 'native' ] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'math_input', 'math_tex' ] )
			->from( 'mathlog' )
			->where( [ 'math_mode' => 8 ] )
			->assertResultSet( [ [ 'a^2', '{\\displaystyle a^{2}}' ] ] );
		$this->expectOutputRegex( '/0 failed/' );
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

		// Saving stored the input, UpdateMath logged the rendering next to it
		$this->newSelectQueryBuilder()
			->select( 'math_mode' )
			->from( 'mathlog' )
			->where( [ 'math_input' => 'z^3' ] )
			->orderBy( 'math_mode' )
			->assertResultSet( [ [ 3 ], [ 8 ] ] );
		$this->expectOutputRegex( '/0 failed/' );
	}

	public function testLatestRevisionThatReusesTheContentIsIndexed() {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		$page = $this->editPage( 'Dummy', '<math>q</math>' )->getNewRevision()->getPage();
		$dummy = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $page )
			->newPageUpdater( $this->getTestSysop()->getUser() )
			->saveDummyRevision( 'dummy' )
			->getId();
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'mathindex' )
			->where( IDatabase::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();

		$this->maintenance->loadWithArgv( [ '--mode', 'source' ] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( 'mathindex_anchor' )
			->from( 'mathindex' )
			->assertFieldValue( "math.$dummy.0" );
		$this->expectOutputRegex( '/Updated 1 formulae/' );
	}

	public function testFormulaNotOnThePageIsIndexed() {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		$revId = $this->editPage( 'Hidden', '{{Missing template|<math>z^3</math>}}' )
			->getNewRevision()->getId();

		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'mathindex_revision_id', 'mathindex_anchor' ] )
			->from( 'mathindex' )
			->join( 'mathlog', null, 'math_inputhash = mathindex_inputhash' )
			->where( [ 'math_input' => 'z^3' ] )
			->distinct()
			->assertResultSet( [ [ $revId, "math.$revId.0" ] ] );
		$this->expectOutputRegex( '/0 failed/' );
	}

	public function testFormulaWithAttributesJoinsMathlog() {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		$this->editPage( 'Display', '<math display="block" id="eq1">x^2</math>' );

		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'mathindex_anchor', 'math_input' ] )
			->from( 'mathindex' )
			->join( 'mathlog', null, 'math_inputhash = mathindex_inputhash' )
			->distinct()
			->assertResultSet( [ [ 'eq1', '{\\displaystyle x^2}' ] ] );
		$this->expectOutputRegex( '/0 failed/' );
	}

	public static function provideAnchors() {
		yield 'id attribute and the same content without' => [
			'<math id="a">x</math> <math>x</math>', [ [ 'a', 'x' ], [ 'math.%d.1', 'x' ] ] ];
		yield 'the same id attribute twice' => [
			'<math id="a">x</math> <math id="a">y</math>', [ [ 'a', 'x' ], [ 'math.%d.1', 'y' ] ] ];
		yield 'a repeated formula in a reference' => [
			'<ref><math>y</math></ref> <math>y</math> <math>z</math> <references/>',
			[ [ 'math.%d.0', 'y' ], [ 'math.%d.1', 'y' ], [ 'math.%d.2', 'z' ] ] ];
	}

	/**
	 * @dataProvider provideAnchors
	 */
	public function testEveryFormulaIsIndexedOnce( string $wikitext, array $expected ) {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		$revId = $this->editPage( 'Anchors', $wikitext )->getNewRevision()->getId();

		$this->maintenance->loadWithArgv( [] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'mathindex_anchor', 'math_input' ] )
			->from( 'mathindex' )
			->join( 'mathlog', null, 'math_inputhash = mathindex_inputhash' )
			->where( [ 'mathindex_revision_id' => $revId ] )
			->distinct()
			->orderBy( 'mathindex_anchor' )
			->assertResultSet( array_map(
				static fn ( $row ) => [ sprintf( $row[0], $revId ), $row[1] ], $expected ) );
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
				[ '{\\displaystyle x^2}', '{"display":"block"}' ],
			] );
		$this->newSelectQueryBuilder()
			->select( [ 'math_input', 'math_inputhash' ] )
			->from( 'mathlog' )
			->distinct()
			->orderBy( 'math_input' )
			->assertResultSet( [
				[ 'y', md5( '{"q":"{\\\\displaystyle y}","type":"tex"}', true ) ],
				[ '{\\displaystyle x^2}', md5( '{"attributes":{"display":"block"},' .
					'"q":"{\\\\displaystyle x^2}","type":"tex"}', true ) ],
			] );
		$this->expectOutputRegex( '/0 failed/' );
	}

	public function testSourceModeOnlyIndexesAndStoresTheInputs() {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		$revId = $this->editPage( 'Source', 'Text' )->getNewRevision()->getId();
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'text' )
			->set( [ 'old_text' => '<math>a+b</math> <chem>H2O</chem>' ] )
			->where( [ 'old_text' => 'Text' ] )
			->caller( __METHOD__ )
			->execute();

		$this->maintenance->loadWithArgv( [ '--mode', 'source' ] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'mathindex_anchor', 'mathindex_inputhash', 'math_mode', 'math_mathml' ] )
			->from( 'mathindex' )
			->join( 'mathlog', null, 'math_inputhash = mathindex_inputhash' )
			->where( [ 'mathindex_revision_id' => $revId ] )
			->orderBy( 'mathindex_anchor' )
			->assertResultSet( [
				[ "math.$revId.0", md5( '{"q":"{\\\\displaystyle a+b}","type":"tex"}', true ), 3, '' ],
				[ "math.$revId.1", md5( '{"attributes":{"chem":true},' .
					'"q":"{\\\\displaystyle \\\\ce{H2O}}","type":"chem"}', true ), 3, '' ],
			] );
		$this->expectOutputRegex( '/Updated 2 formulae, 0 failed/' );
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

	public function testNoSvgIsRequestedForAFailedRendering() {
		$this->overrideConfigValues( [
			'MathValidModes' => [ 'source', 'native', 'mathml' ],
			'MathSearchMode' => 'latexml',
			MainConfigNames::HTTPTimeout => 1,
		] );
		$this->editPage( 'Mass energy', '<math>E=mc^2</math>' );
		$this->installMockHttp( $this->makeFakeHttpMultiClient( [
			[ 'code' => 400, 'body' => '{"success":false,"detail":"invalid"}' ],
		] ) );

		$this->maintenance->loadWithArgv( [ '--mode', 'mathml', '--SVG' ] );
		$this->maintenance->execute();

		$this->expectOutputRegex( '/Updated 1 formulae, 1 failed \(see mathlog\)/' );
	}

	public function testFormulaWithoutSvgIsStored() {
		$this->overrideConfigValues( [
			'MathValidModes' => [ 'source', 'native', 'mathml' ],
			'MathSearchMode' => 'latexml',
			MainConfigNames::HTTPTimeout => 1,
		] );
		$this->editPage( 'Mass energy', '<math>E=mc^2</math>' );
		$mathml = '<math xmlns="http://www.w3.org/1998/Math/MathML"><mi>E</mi></math>';
		$svgError = [ 'code' => 500, 'body' => '' ];
		$this->installMockHttp( [
			$this->makeFakeHttpMultiClient( [ [
				'code' => 200,
				'headers' => [ 'x-resource-location' => 'RESOURCE_LOCATION' ],
				'body' => '{"success":true,"checked":"E=mc^2","identifiers":[]}',
			] ] ),
			$this->makeFakeHttpMultiClient( [ $mathml ] ),
			$this->makeFakeHttpMultiClient( [ $svgError ] ),
			$this->makeFakeHttpMultiClient( [ $svgError ] ),
		] );

		$this->maintenance->loadWithArgv( [ '--mode', 'mathml', '--SVG' ] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( [ 'math_mathml', 'math_svg', 'math_log' ] )
			->from( 'mathlog' )
			->where( [ 'math_mode' => 5 ] )
			->assertResultSet( [ [ $mathml, '', 'Cannot get svg. Server problem.' ] ] );
		$this->expectOutputRegex( '/Updated 1 formulae, 0 failed/' );
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
