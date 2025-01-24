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
 *
 * TODO: deduplicate code from OpenAlex
 */

use MediaWiki\Extension\MathSearch\Graph\Map;
use MediaWiki\Extension\MathSearch\Graph\Query;
use MediaWiki\Sparql\SparqlException;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class ImportRecommendations extends Maintenance {

	/** @var string */
	private string $filename;

	private array $qid_cache;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Batch imports Recommendation data from a CSV file." );
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
		$jobname = 'recommendation' . date( 'ymdhms' );
		while ( $line !== false ) {
			try {
				$table += $this->readline( $line, $columns );
				if ( count( $table ) > $this->getBatchSize() ) {
					$this->output( "Push jobs to segment $segment.\n" );
					$graphMap->pushJob(
						$table,
						$segment++,
						'MediaWiki\Extension\MathSearch\Graph\Job\QuickStatements',
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
				'MediaWiki\Extension\MathSearch\Graph\Job\QuickStatements',
				[ 'jobname' => $jobname ] );
		}
		$this->output( "Pushed last $segment.\n" );

		fclose( $handle );
	}

	/**
	 * @throws SparqlException
	 * @throws Exception
	 */
	private function de2q( string $de ): string {
		if ( !isset( $this->qid_cache[$de] ) ) {
			$rows = [ $de => true ];
			$map = Query::getDeQIdMap( $rows );
			if ( !array_key_exists( $de, $map ) ) {
				throw new Exception( "No document field found." );
			}
			$this->qid_cache[$de] = $map[$de];
		}
		return $this->qid_cache[$de];
	}

	/**
	 * @throws SparqlException
	 */
	private function readline( array $line, array $columns ): array {
		$fields = array_combine( $columns, $line );
		return [
			'qid' => $this->de2q( $fields['seed'] ),
			'P1643' => $this->de2q( $fields['recommendation'] ),
			'qal1659' => $fields['similarity_score'],
			'qal1660' => 'Q6534273'

		];
	}

}

$maintClass = ImportRecommendations::class;
require_once RUN_MAINTENANCE_IF_MAIN;
