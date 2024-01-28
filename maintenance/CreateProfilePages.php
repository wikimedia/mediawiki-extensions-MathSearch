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

use MediaWiki\MediaWikiServices;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class CreateProfilePages extends Maintenance {
	private const BATCH_SIZE = 10000;
	private const PAGES_PER_JOB = 100;
	/** @var bool */
	private $overwrite;

	private $jobQueueGroup;
	private $jobname;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Mass creates pages from the SPARQL endpoint " );
		$this->addArg( 'type', 'Type of profile to be created.', true, false );
		$this->addOption(
			'overwrite', 'Overwrite existing pages with the same name.', false, false, "o"
		);

		$this->requireExtension( 'MathSearch' );
		$this->requireExtension( 'LinkedWiki' );
	}

	private function getQuery( int $offset, int $limit = self::BATCH_SIZE ) {
		global $wgMathProfileQueries;
		return <<<SPARQL
PREFIX wdt: <https://portal.mardi4nfdi.de/prop/direct/>
PREFIX wd: <https://portal.mardi4nfdi.de/entity/>
SELECT ?item WHERE {
${wgMathProfileQueries[$this->getArg( 'type' )]}
}
ORDER by ?item
LIMIT $limit
OFFSET $offset
SPARQL;
	}

	public function execute() {
		global $wgMathProfileQueries;
		if ( !isset( $wgMathProfileQueries[$this->getArg( 'type' )] ) ) {
			$this->error( "Unknown type of profile to be created.\n" );
			$this->error( "Available types are: " . implode( ', ', array_keys( $wgMathProfileQueries ) ) . "\n" );
			return;
		}

		$this->jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroup();
		$this->jobname = 'import' . date( 'ymdhms' );
		$this->overwrite = $this->getOption( 'overwrite' );

		if ( $this->overwrite ) {
			$this->output( "Loaded with option overwrite enabled .\n" );
		}
		$configFactory = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'wgLinkedWiki' );
		$configDefault = $configFactory->get( "SPARQLServiceByDefault" );
		$arrEndpoint = ToolsParser::newEndpoint( $configDefault, null );
		$sp = $arrEndpoint["endpoint"];
		$offset = 0;
		$table = [];
		$segment = 0;
		do {
			$this->output( 'Read from offset ' . $offset . ".\n" );
			$rs = $sp->query( $this->getQuery( $offset ) );
			if ( !$rs ) {
				$this->output( "No results retrieved!\n" );
				break;
			}
			foreach ( $rs['result']['rows'] as $row ) {
				$qID = preg_replace( '/.*Q(\d+)$/', '$1', $row['item'] );

				$table[] = $qID;
				if ( count( $table ) > self::PAGES_PER_JOB ) {
					$this->pushJob( $table, $segment );
					$segment++;
					$table = [];
				}
			}
			$offset += self::BATCH_SIZE;
		} while ( count( $rs['result']['rows'] ) == self::BATCH_SIZE );
		$this->pushJob( $table, $segment );
	}

	/**
	 * @param array $table
	 * @param int $segment
	 * @return void
	 */
	public function pushJob(
		array $table, int $segment
	): void {
		$this->jobQueueGroup->lazyPush( new PageCreationJob( [
				'jobname' => $this->jobname,
				'rows' => $table,
				'segment' => $segment, // just for the logs
				'prefix' => $this->getArg( 'type' ),
			] ) );
	}

}

$maintClass = CreateProfilePages::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
