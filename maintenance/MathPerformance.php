#!/usr/bin/env php
<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @ingroup Maintenance
 */

use MediaWiki\Extension\Math\WikiTexVC\TexVC;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class MathPerformance extends Maintenance {

	/** @var bool */
	private $verbose;
	/** @var \Wikimedia\Rdbms\IDatabase */
	private $db;
	/** @var string|null */
	private $currentHash;
	/** @var float */
	private $time = 0.0;
	/** @var float[] */
	private $performance = [];
	/** @var string */
	private $renderingMode = 'native';

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Run math performance tests.' );
		$this->addArg( 'action', 'Selects what should be done.', false );
		$this->addArg( 'shares', 'How many pieces should be used.', false );
		$this->addArg( 'share', 'Which piece should be used. Starting from 0.', false );
		$this->addOption( 'table', 'table to load the formulae from', false );
		$this->addOption( 'input', 'field that contains the input', false );
		$this->addOption( 'hash', 'field that contains the hash', false );
		$this->addOption( 'min', 'If set, processing is started at formula>min', false );
		$this->addOption( 'max', 'If set, processing is stopped at formula<=max', false );
		$this->addOption( 'output', 'The destination of the output defaults to stdout.', false );
		$this->addOption( 'verbose', 'If set, output for successful rendering will produced', false,
			false, 'v' );
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		global $wgMathValidModes;
		$this->db = $this->getServiceContainer()
			->getConnectionProvider()
			->getPrimaryDatabase();
		$wgMathValidModes[] = $this->renderingMode;
		$this->verbose = $this->getOption( 'verbose', false );
		$this->vPrint( "Loaded." );
		$action = trim( $this->getArg( 0, 'export' ) );
		switch ( $action ) {
			case 'export':
				$this->actionExport();
				break;
			case "benchmark":
				$this->actionBenchmark();
				break;
			case "fix-results":
				$this->actionBenchmark( 'fix' );
				break;
			case "compare-results":
				$this->actionBenchmark( 'compare' );
				break;
		}
		$shareString = $this->getArg( 2, '' );
		$this->vPrint( "{$shareString}Done." );
	}

	private function actionExport() {
		$tex = $this->getOption( 'input', 'math_input' );
		$hash = $this->getOption( 'hash', 'math_inputhash' );
		$formulae = $this->getFormulae( $hash, $tex );
		$out = [];
		foreach ( $formulae as $formula ) {
			$out[] = [ $hash => base64_encode( $formula->$hash ), $tex => $formula->$tex ];
		}
		$output = $this->getOption( 'output', 'php://stdout' );
		file_put_contents( $output, json_encode( $out, JSON_PRETTY_PRINT ) );
	}

	/**
	 * @param string $hash
	 * @param string $tex
	 * @param string $action
	 * @return bool|\Wikimedia\Rdbms\IResultWrapper
	 */
	private function getFormulae( $hash, $tex, $action = 'benchmark' ) {
		$min = $this->getOption( 'min', 0 );
		$max = $this->getOption( 'max', 0 );
		$options = [];
		if ( $max ) {
			$options['LIMIT'] = $max - $min;
			$options['OFFSET'] = $min;
		}
		$shares = $this->getArg( 1, false ); // 'shares'
		$share = $this->getArg( 2, 0 ); // 'share'
		if ( $shares ) {
			$this->vPrint( "Processing share $share of $shares." );
			$counts = $this->db->selectField( 'mathlog', 'count(*)', [], __METHOD__ );
			$bucket = ceil( $counts / $shares );
			$min = $share * $bucket;
			$max = $min + $bucket;
			$options['LIMIT'] = $max - $min;
			$options['OFFSET'] = $min;
		}
		$fields = [ $hash, $tex ];
		if ( $action !== 'benchmark' ) {
			array_push( $fields, 'math_tex', 'math_mathml', 'math_statuscode' );
		}
		$formulae = $this->db->select(
			'mathlog',
			$fields,
			'',
			__METHOD__,
			$options
		);
		return $formulae;
	}

	private function saveResult(
		string $tex,
		string $hash,
		object $formula,
		array $result,
		?string $mathml = null,
		bool $mhchem = false
	): void {
		$mo = new MathObject( $tex );
		$mo->setInputHash( $formula->$hash );
		$mo->setMode( $this->renderingMode );
		$mo->readFromCache();
		if ( isset( $result['output'] ) ) {
			$mo->setTex( $result['output'] );
		}
		if ( $mathml ) {
			$mo->setMathml( $mathml );
		}
		$mo->setStatusCode( ord( $result['status'] ) );
		if ( $result['status'] !== '+' ) {
			$mo->setLog( 'checking failed:' . $result['details'] );
		} elseif ( $mhchem ) {
			$mo->setLog( 'mhchem_required' );
		}
		$mo->writeToCache();
	}

	private function runNativeTest( object $formula, string $action, string $hash ): void {
		$tex = $formula->{$this->getOption( 'input', 'math_input' )};
		$mhchem = false;
		$texVC = new TexVC();
		try {
			$this->resetTimer();
			$result = $texVC->check( $tex, [
				'debug' => false,
				'usemathrm' => false,
				'oldtexvc' => false
			] );
			$this->time( 'check' );

			$status = $result['status'];
			if ( $status === 'C' ) {
				$this->resetTimer();
				$mhchem = true;
				$result = $texVC->check( $tex, [
					'debug' => false,
					'usemathrm' => false,
					'oldtexvc' => false,
					'usemhchem' => true,
				] );
				$this->time( 'check' );
			}
			$status = $result['status'];

			if ( $status !== '+' ) {
				$this->vPrint( bin2hex( $formula->$hash ) . ' checking failed:' . $result['details'] );
				if ( $action === 'fix' ) {
					$this->saveResult( $tex, $hash, $formula, $result );
				} elseif ( $action === 'compare' ) {
					$old_status = chr( $formula->math_statuscode );
					if ( $status !== $old_status ) {
						$this->vPrint( bin2hex( $formula->$hash ) .
							' changed status ' . $old_status . ' to ' . $status );
					}
				}
				return;
			}
			$mathml = $result["input"]->toMMLTree();
			$this->time( 'render' );
			$mathml = (string)$mathml;
			$this->time( 'print' );
			if ( $action === 'fix' ) {
				$this->saveResult( $tex, $hash, $formula, $result, $mathml, $mhchem );
			} elseif ( $action === 'compare' ) {
				if ( $formula->math_tex !== $result["output"] ) {
					$this->vPrint( bin2hex( $formula->$hash ) .
						' changed tex ' . $formula->math_tex . ' to ' . $result["output"] );
				}
				if ( $formula->math_mathml !== $mathml ) {
					$this->vPrint( bin2hex( $formula->$hash ) . ' changed mathml' );
				}
			}
		} catch ( PhpPegJs\SyntaxError $ex ) {
			$message = "Syntax error: " . $ex->getMessage() .
				' at line ' . $ex->grammarLine . ' column ' .
				$ex->grammarColumn . ' offset ' . $ex->grammarOffset;
			$this->vPrint( $formula->$hash . ':' . $message );
		} catch ( Exception $e ) {
			$message = "Unexpected error: " . $e->getMessage();
			$this->vPrint( $formula->$hash . ':' . $message );
		}
	}

	private function actionBenchmark( string $action = 'benchmark' ) {
		$tex = $this->getOption( 'input', 'math_input' );
		$hash = $this->getOption( 'hash', 'math_inputhash' );
		$formulae = $this->getFormulae( $hash, $tex, $action );
		foreach ( $formulae as $formula ) {
			$this->currentHash = $formula->$hash;
			$this->runNativeTest( $formula, $action, $hash );
		}
	}

	/**
	 * Measures time in ms.
	 * In order to have a formula centric evaluation, we can not just the build in profiler
	 * @param string $category
	 *
	 * @return int
	 */
	private function time( $category = 'default' ) {
		global $wgMathDebug;
		$delta = ( hrtime( true ) - $this->time ) / 1e6;
		$this->performance[$category] ??= 0;
		$this->performance[$category] += $delta;
		$logData = [
			'math_inputhash'       => $this->currentHash,
			'mathperformance_name' => substr( $category, 0, 10 ),
			'mathperformance_time' => $delta,
			'mathperformance_mode' => MathObject::MODE_2_USER_OPTION[ $this->renderingMode ]
		];
		if ( $wgMathDebug ) {
			$this->db->insert( 'mathperformance', $logData, __METHOD__ );
		} else {
			$logData['math_inputhash'] = base64_encode( $logData['math_inputhash'] );
			echo json_encode( $logData ) . "\n";
		}
		$this->resetTimer();
		return (int)$delta;
	}

	private function resetTimer() {
		$this->time = hrtime( true );
	}

	private function vPrint( string $string ) {
		if ( $this->verbose ) {
			$this->output( $string . "\n" );
		}
	}

}

$maintClass = MathPerformance::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
