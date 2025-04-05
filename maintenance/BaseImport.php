<?php

use MediaWiki\Extension\MathSearch\Graph\Map;
use MediaWiki\Maintenance\Maintenance;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

abstract class BaseImport extends Maintenance {

	protected array $jobOptions;
	private string $jobType;

	public function __construct( array $jobOptions, string $jobType, string $description = 'The file to be read' ) {
		parent::__construct();
		$this->addArg( 'file', $description );
		$this->setBatchSize( 100 );
		$this->requireExtension( 'MathSearch' );
		$this->jobOptions = $jobOptions;
		$this->jobType = $jobType;
	}

	public function execute() {
		$filename = $this->getArg( 0 );
		if ( !is_file( $filename ) ) {
			$this->output( "{$filename} is not a file.\n" );
			exit( 1 );
		}
		$handle = fopen( $filename, 'r' );
		$columns = fgetcsv( $handle );
		$columns = array_map( 'trim', $columns );
		$table = [];
		$line = fgetcsv( $handle, 0, ',', '"', '' );
		$graphMap = new Map();
		$segment = 0;
		while ( $line !== false ) {
			try {
				$newRow = $this->readline( $line, $columns );
				// quasi recursive array + operator
				$key = array_key_first( $newRow );
				if ( isset( $table[ $key ] ) ) {
					$table[$key] += $newRow[$key];
				} else {
					$table += $newRow;
				}
				if ( count( $table ) > $this->getBatchSize() ) {
					$this->output( "Push jobs to segment $segment.\n" );
					$this->pushJob( $graphMap, $table, $segment );
					$table = [];
				}
			} catch ( Throwable $e ) {
				$this->output( "Error processing line: \n" .
					var_export( implode( ',', $line ), true ) . "\nError:" .
					$e->getMessage() . "\n" );
			}
			$line = fgetcsv( $handle, 0, ',', '"', '' );
		}
		if ( count( $table ) ) {
			$this->pushJob( $graphMap, $table, $segment );
			$segment--;
		}
		$this->output( "Pushed last $segment.\n" );

		fclose( $handle );
		return true;
	}

	/**
	 * @param Map $graphMap
	 * @param array $table
	 * @param int &$segment
	 */
	protected function pushJob( Map $graphMap, array $table, int &$segment ): void {
		$graphMap->pushJob(
			$table,
			$segment++,
			$this->jobType,
			$this->jobOptions );
	}

	abstract protected function readline( array $line, array $columns ): array;

}
