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

use MediaWiki\Extension\MathSearch\Graph\Map;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class ImportOpenAlex extends Maintenance {

	/** @var string */
	private string $filename;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Batch imports OpenAlex data from a CSV file." );
		$this->addArg( 'file', 'The file to be read', true );
		$this->setBatchSize( 100 );
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		$this->filename = $this->getArg( 0 );
		if ( !is_file( $this->filename ) ) {
			$this->output( "{$this->filename} is not a file.\n" );
			exit( 1 );
		}
		$handle = fopen( $this->filename, 'r' );
		$columns = fgetcsv( $handle );
		$table = [];
		if ( $columns === null ) {
			throw new Exception( "Problem processing the csv file." );
		}
		$line = fgetcsv( $handle, 0, ',', '"', '' );
		$graphMap = new Map();
		$segment = 0;
		$jobname = 'openalex' . date( 'ymdhms' );
		while ( $line !== false ) {
			try {
				$table += $this->readline( $line, $columns );
				if ( count( $table ) > $this->getBatchSize() ) {
					$this->output( "Push jobs to segment $segment.\n" );
					$graphMap->pushJob(
						$table,
						$segment++,
						'MediaWiki\Extension\MathSearch\Graph\Job\OpenAlex',
						[ 'jobname' => $jobname ] );
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
			$graphMap->pushJob(
				$table,
				$segment,
				'MediaWiki\Extension\MathSearch\Graph\Job\OpenAlex',
				[ 'jobname' => $jobname ] );
		}
		$this->output( "Pushed last $segment.\n" );

		fclose( $handle );
	}

	private function readline( array $line, array $columns ): array {
		global $wgMathOpenAlexQIdMap;
		$pDe = $wgMathOpenAlexQIdMap['document'];
		$pUrl = $wgMathOpenAlexQIdMap['prime_landing_page_url'];

		$fields = array_combine( $columns, $line );
		$data = [];
		foreach ( $wgMathOpenAlexQIdMap as $oa_name => $pid ) {
			if ( !array_key_exists( $pid, $data ) ) {
				$field = $fields[$oa_name];
				if ( $field ) {
					if ( str_starts_with( $field, 'https://' ) && $pid !== $pUrl ) {
						$data[$pid] = ltrim( parse_url( $field, PHP_URL_PATH ), '/' );
					} else {
						$data[$pid] = $field;
					}
				}
			}
		}
		if ( !array_key_exists( $pDe, $data ) ) {
			throw new Exception( "No document field found." );
		}
		$de = (int)$data[$pDe];
		// save some bytes
		unset( $data[$pDe] );
		return [ $de => $data ];
	}

}

$maintClass = ImportOpenAlex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
