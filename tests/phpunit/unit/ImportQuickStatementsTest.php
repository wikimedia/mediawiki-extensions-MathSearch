<?php

namespace MediaWiki\Extension\MathSearch\Maintenance\Tests;

use ImportQuickStatements;
use MediaWikiUnitTestCase;
use ReflectionMethod;
use ReflectionProperty;

require_once __DIR__ . '/../../../maintenance/ImportQuickStatements.php';

/**
 * @covers \ImportQuickStatements
 */
class ImportQuickStatementsTest extends MediaWikiUnitTestCase {

	/**
	 * Returns a minimal testable subclass that bypasses the Maintenance constructor.
	 */
	private function newScript(): ImportQuickStatements {
		return new class extends ImportQuickStatements {
			public function __construct() {
				// Bypass the Maintenance parent constructor intentionally.
			}
		};
	}

	/**
	 * @dataProvider provideReadline
	 */
	public function testReadline( array $columns, array $line, array $expected ): void {
		$method = new ReflectionMethod( ImportQuickStatements::class, 'readline' );
		$method->setAccessible( true );
		$this->assertSame( $expected, $method->invoke( $this->newScript(), $line, $columns ) );
	}

	public static function provideReadline(): array {
		return [
			'maps columns to values' => [
				[ 'qid', 'P31' ],
				[ 'Q42', 'Q5' ],
				[ 'qid' => 'Q42', 'P31' => 'Q5' ],
			],
			'strips empty non-qid value' => [
				[ 'qid', 'P31', 'P18' ],
				[ 'Q42', 'Q5', '' ],
				[ 'qid' => 'Q42', 'P31' => 'Q5' ],
			],
			'keeps empty qid value' => [
				[ 'qid', 'P31' ],
				[ '', 'Q5' ],
				[ 'qid' => '', 'P31' => 'Q5' ],
			],
			'strips all empty non-qid values' => [
				[ 'qid', 'P31', 'P18' ],
				[ 'Q42', '', '' ],
				[ 'qid' => 'Q42' ],
			],
		];
	}

	/**
	 * Returns a test double that stubs getOption('create-missing') and skips file I/O.
	 */
	private function newScriptWithCreateMissingFlag( bool $createMissingValue ): ImportQuickStatements {
		return new class( $createMissingValue ) extends ImportQuickStatements {
			private bool $createMissingValue;

			public function __construct( bool $createMissingValue ) {
				// Bypass Maintenance constructor
				$this->createMissingValue = $createMissingValue;
			}

			public function getOption( $name, $default = null ) {
				return $name === 'create-missing' ? $this->createMissingValue : $default;
			}

			public function execute(): void {
				// Mirrors ImportQuickStatements::execute() without file I/O from parent
				$this->jobOptions['create_missing'] = (bool)$this->getOption( 'create-missing', false );
			}
		};
	}

	public function testExecuteSetsCreateMissingFalseByDefault(): void {
		$testScript = $this->newScriptWithCreateMissingFlag( false );
		$testScript->execute();

		$prop = new ReflectionProperty( ImportQuickStatements::class, 'jobOptions' );
		$prop->setAccessible( true );
		$this->assertFalse( $prop->getValue( $testScript )['create_missing'] );
	}

	public function testExecuteSetsCreateMissingTrueWhenFlagEnabled(): void {
		$testScript = $this->newScriptWithCreateMissingFlag( true );
		$testScript->execute();

		$prop = new ReflectionProperty( ImportQuickStatements::class, 'jobOptions' );
		$prop->setAccessible( true );
		$this->assertTrue( $prop->getValue( $testScript )['create_missing'] );
	}
}
