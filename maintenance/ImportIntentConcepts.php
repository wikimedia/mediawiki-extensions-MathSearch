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

class ImportIntentConcepts extends Maintenance {
	private const URL = 'https://raw.githubusercontent.com/davidcarlisle/mathml-docs/main/_data/core.yml';
	/** @var bool */
	private $overwrite;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Batch imports definitions from a folder.' .
			" \n Processes mathosphere files that follow the naming convention: \n *.json" );
		$this->addArg( 'url', 'The URL to to be read. Defaults to ', false );
		$this->addOption(
			'overwrite', 'Overwrite existing definitions with the same name.', false, false, "o"
		);
		$this->setBatchSize( 100 );
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		$url = $this->getArg( 0, self::URL );
		$this->overwrite = $this->getOption( 'overwrite' );
		if ( $this->overwrite ) {
			$this->output( "Loaded with option overwrite enabled .\n" );
		}
		$res = yaml_parse_url( $url );

		$concepts = $this->findConcept( [], $res );
		$partitions = array_chunk( $concepts, $this->getBatchSize(), true );
		$jobname = 'concepts' . date( 'ymdhms' );
		$graphMap = new Map();
		$segment = 0;
		foreach ( $partitions as $chunk ) {
			$this->output( "Push jobs to segment $segment.\n" );
			$graphMap->pushJob(
				$chunk,
				$segment++,
				'MediaWiki\Extension\MathSearch\Graph\Job\MathMLIntents',
			[ 'jobname' => $jobname ] );
		}
		$this->output( "Pushed $segment segments.\n" );
	}

	public function findConcept( $context, $content ) {
		$concepts = [];
		foreach ( $content as $key => $value ) {
			if ( $key === 'concept' ) {
				$concepts[$value] = [
					'context' => $context,
					'content' => $content
				];
				return $concepts;
			}
			if ( is_array( $value ) ) {
				$concepts += $this->findConcept( [ ...$context, $key ], $value );
			}
		}
		return $concepts;
	}
}

$maintClass = ImportIntentConcepts::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
