<?php

use MediaWiki\Extension\MathSearch\Graph\Map;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

abstract class BaseImport extends Maintenance {

	protected bool $groupConsecutiveKeys = false;

	protected bool $rowsHaveKeys = true;

	public function __construct(
		protected array $jobOptions,
		private readonly string $jobType,
		string $description = 'The file to be read',
	) {
		parent::__construct();
		$this->addArg( 'file', $description );
		$this->setBatchSize( 100 );
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		$filename = $this->getArg( 0 );
		if ( !is_file( $filename ) ) {
			$this->output( "{$filename} is not a file.\n" );
			exit( 1 );
		}
		$graphMap = new Map();
		$segment = 0;
		$lastKey = null;
		$table = [];
		// for the JSON format rows are arbitrary key-value pairs and must not have the same columns for each line
		foreach ( $this->getFileRows( $filename ) as [ $line, $columns ] ) {
			try {
				$newRow = $this->readline( $line, $columns );
				// There are two forms of rows with a key such as "v1 => (v2, v3)" and no keys "(v1, v2, v3)".
				// A typical key can be the qID.
				$key = $this->rowsHaveKeys ? array_key_first( $newRow ) : null;
				$splitCondition = count( $table ) >= $this->getBatchSize();
				if ( $this->groupConsecutiveKeys ) {
					$splitCondition &= $key !== $lastKey;
				}
				$lastKey = $key;
				if ( $splitCondition ) {
					$this->output( "Push jobs to segment $segment.\n" );
					$this->pushJob( $graphMap, $table, $segment );
					$table = [];
				}
				if ( $this->rowsHaveKeys ) {
					if ( isset( $table[ $key ] ) ) {
						$table[$key] += $newRow[$key];
					} else {
						$table += $newRow;
					}
				} else {
					$table[] = $newRow;
				}
			} catch ( Throwable $e ) {
				$this->output( "Error processing line: \n" .
					var_export( implode( ',', $line ), true ) . "\nError:" .
					$e->getMessage() . "\n" );
			}
		}
		if ( count( $table ) ) {
			$this->pushJob( $graphMap, $table, $segment );
			$segment--;
		}
		$this->output( "Pushed last $segment.\n" );
		return true;
	}

	/**
	 * @return \Generator<array{array, array}> Yields [$line, $columns] pairs
	 */
	protected function getFileRows( string $filename ): \Generator {
		if ( str_ends_with( strtolower( $filename ), '.json' ) ) {
			yield from $this->getJsonRows( $filename );
		} else {
			yield from $this->getCsvRows( $filename );
		}
	}

	protected function getCsvRows( string $filename ): \Generator {
		$handle = fopen( $filename, 'r' );
		$columns = fgetcsv( $handle );
		$columns = array_map( 'trim', $columns );
		$line = fgetcsv( $handle, 0, ',', '"', '' );
		while ( $line !== false ) {
			yield [ $line, $columns ];
			$line = fgetcsv( $handle, 0, ',', '"', '' );
		}
		fclose( $handle );
	}

	protected function getJsonRows( string $filename ): \Generator {
		$content = file_get_contents( $filename );
		$data = json_decode( $content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->output( "Invalid JSON file: " . json_last_error_msg() . "\n" );
			exit( 1 );
		}
		if ( !is_array( $data ) ) {
			$this->output( "Invalid JSON file: expected an array of objects.\n" );
			exit( 1 );
		}
		foreach ( $data as $row ) {
			yield [ array_values( $row ), array_keys( $row ) ];
		}
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
