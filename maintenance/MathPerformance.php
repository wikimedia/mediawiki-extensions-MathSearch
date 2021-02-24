#!/usr/bin/env php
<?php
/**
 *
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

use MediaWiki\Logger\LoggerFactory;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class MathPerformance extends Maintenance {

	/** @var bool */
	private $verbose;
	/** @var \Wikimedia\Rdbms\IDatabase */
	private $db;
	private $currentHash;
	/** @var float */
	private $time = 0.0; // microtime( true );
	/** @var float[] */
	private $performance = [];
	/** @var string */
	private $renderingMode = 'mathml';

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
		$this->db = wfGetDB( DB_MASTER );
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
			case 'png':
				$this->actionPng();
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
	 * @return bool|\Wikimedia\Rdbms\IResultWrapper
	 * @throws DBUnexpectedError
	 */
	private function getFormulae( $hash, $tex ) {
		$min = $this->getOption( 'min', 0 );
		$max = $this->getOption( 'max', 0 );
		$options = [];
		if ( $max ) {
			$options['LIMIT'] = $max - $min;
			$options['OFFSET'] = $min;
		}
		$table = $this->getOption( 'table', 'mathoid' );
		$shares = $this->getArg( 1, false ); // 'shares'
		$share = $this->getArg( 2, 0 ); // 'share'
		if ( $shares ) {
			$this->vPrint( "Processing share $share of $shares." );
			$counts = $this->db->selectField( $table, 'count(*)' );
			$bucket = ceil( $counts / $shares );
			$min = $share * $bucket;
			$max = $min + $bucket;
			$options['LIMIT'] = $max - $min;
			$options['OFFSET'] = $min;
		}
		$formulae = $this->db->select(
			$table,
			[ $hash, $tex ],
			'',
			__METHOD__,
			$options
		);
		return $formulae;
	}

	private function actionBenchmark() {
		$tex = $this->getOption( 'input', 'math_input' );
		$hash = $this->getOption( 'hash', 'math_inputhash' );
		$formulae = $this->getFormulae( $hash, $tex );
		foreach ( $formulae as $formula ) {
			$this->currentHash = $formula->$hash;
			$rbi = new MathRestbaseInterface( $formula->$tex, false );
			if ( $this->runTest( $rbi ) ) {
				if ( round( rand( 0, 1 ) ) ) {
					$this->runTest( $rbi, 'getSvg', '1-' ) &&
					$this->runTest( $rbi, 'getMathML', '2-' );
				} else {
					$this->runTest( $rbi, 'getMathML', '1-' ) &&
					$this->runTest( $rbi, 'getSvg', '2-' );
				}
			}
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
		$delta = ( microtime( true ) - $this->time ) * 1000;
		if ( isset( $this->performance[$category] ) ) {
			$this->performance[$category] += $delta;
		} else {
			$this->performance[$category] = $delta;
		}
		$logData = [
			'math_inputhash'       => $this->currentHash,
			'mathperformance_name' => substr( $category, 0, 10 ),
			'mathperformance_time' => $delta,
			'mathperformance_mode' => MathHooks::mathModeToHashKey( $this->renderingMode )
		];
		if ( $wgMathDebug ) {
			$this->db->insert( 'mathperformance', $logData );
		} else {
			$logData['math_inputhash'] = base64_encode( $logData['math_inputhash'] );
			echo json_encode( $logData ) . "\n";
		}
		$this->resetTimer();
		return (int)$delta;
	}

	private function resetTimer() {
		$this->time = microtime( true );
	}

	private function vPrint( $string ) {
		if ( $this->verbose ) {
			$this->output( $string . "\n" );
		}
	}

	private function runTest( MathRestbaseInterface $rbi, $method = 'checkTeX', $prefix = '' ) {
		try{
			$this->resetTimer();
			call_user_func( [ $rbi, $method ] );
			$this->time( $prefix . $method );
			return true;
		} catch ( Exception $e ) {
			$this->vPrint( "Tex:{$rbi->getTex()}" );
			$this->vPrint( $e->getMessage() );
			$this->vPrint( $e->getTraceAsString() );
			return false;
		}
	}

	private function actionPng() {
		$tex = $this->getOption( 'input', 'math_input' );
		$hash = $this->getOption( 'hash', 'math_inputhash' );
		$folder = $this->getOption( 'output', '/tmp/math' );
		$formulae = $this->getFormulae( $hash, $tex );
		foreach ( $formulae as $formula ) {
			$this->currentHash = $formula->$hash;
			self::processImage( $folder, $formula->$tex );
		}
	}

	/**
	 * @param string $folder
	 * @param string $input
	 */
	private static function processImage( $folder, $input ) {
		$log = LoggerFactory::getInstance( 'MathSearch-maint' );
		$mathML = new MathMathML( $input );
		$md5 = $mathML->getMd5();
		$path = self::makePath( $folder, $md5 );
		$log->debug( 'process image', [
			'tex' => $input,
			'path' => $path
		] );

		// Mathoid
		if ( !$mathML->render() ) {
			$log->error( 'MathML rendering returned false', [
				'mathml' => $mathML,
				'tex' => $input,
				'path' => $path
			] );
			return;
		}
		$o = MathObject::cloneFromRenderer( $mathML );
		file_put_contents( "$path/new.png", $o->getPng() );
		file_put_contents( "$path/new.mml", $o->getMathml() );
		file_put_contents( "$path/new.svg", $o->getSvg() );
		file_put_contents( "$path/tex.tex", $o->getUserInputTex() );

		// Mathoid eating it's MathML
		$mathML = new MathMathML( $o->getMathml(), [ 'type' => 'pmml' ] );
		if ( !$mathML->render() ) {
			$log->error( 'MathML rendering returned false', [
				'mathml' => $mathML,
				'tex' => $input,
				'path' => $path
				] );
			return;
		}

		$o = MathObject::cloneFromRenderer( $mathML );
		file_put_contents( "$path/new-mathml.png", $o->getPng() );
		file_put_contents( "$path/new-mathml.svg", $o->getSvg( 'force' ) );

		// LaTeXML */
		$mathML = new MathLaTeXML( $input );
		if ( !$mathML->readFromDatabase() ) {
			$log->error( 'LaTeXML rendering returned false', [
				'mathml' => $mathML,
				'tex' => $input,
				'path' => $path
			] );
			return;
		}

		$o = MathObject::cloneFromRenderer( $mathML );
		file_put_contents( "$path/new-latexml.png", $o->getPng() );
		file_put_contents( "$path/new-latexml.svg", $o->getSvg( 'force' ) );
		file_put_contents( "$path/new-latexml.mml", $mathML->getMathml() );
	}

	/**
	 * @param string $folder
	 * @param string $md5
	 * @return string
	 */
	private static function makePath( $folder, $md5 ) {
		$subPath = implode( '/', str_split( substr( $md5, 0, 3 ) ) );
		$path = $folder . '/' . $subPath . '/' . $md5;
		mkdir( $path, '0755', true );
		return $path;
	}

}

$maintClass = "MathPerformance";
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
