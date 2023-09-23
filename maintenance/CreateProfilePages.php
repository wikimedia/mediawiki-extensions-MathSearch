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

	/** @var bool */
	private bool $person;
	private $jobQueueGroup;
	private $jobname;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Mass creates pages from the SPARQL endpoint " );
		$this->addOption(
			'overwrite', 'Overwrite existing pages with the same name.', false, false, "o"
		);
		$this->addOption(
			'person', 'Create persons instead of FHP.', false, false, "p"
		);
		$this->requireExtension( 'MathSearch' );
		$this->requireExtension( 'LinkedWiki' );
	}

	private function getQuery( $offset, $limit = self::BATCH_SIZE ) {
		return $this->person ?
			<<<SPARQL
PREFIX wdt: <https://portal.mardi4nfdi.de/prop/direct/>
PREFIX wd: <https://portal.mardi4nfdi.de/entity/>

SELECT ?item
WHERE { ?item wdt:P31 wd:Q57162 .}
ORDER by ?item
LIMIT $limit OFFSET $offset
SPARQL
			: <<<SPARQL
PREFIX wdt: <https://portal.mardi4nfdi.de/prop/direct/>
PREFIX wd: <https://portal.mardi4nfdi.de/entity/>
SELECT ?item ?title
WHERE { ?item wdt:P2 ?title ;
              wdt:P31 wd:Q1025939}
LIMIT $limit OFFSET $offset
SPARQL;
	}

	public function execute() {
		$this->jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroup();
		$this->jobname = 'import' . date( 'ymdhms' );
		$this->overwrite = $this->getOption( 'overwrite' );
		$this->person = $this->getOption( 'person' );

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
			$rs = $sp->query( $this->getQuery( $offset ) );
			$this->output( 'Read from offset ' . $offset . ".\n" );
			foreach ( $rs['result']['rows'] as $row ) {
				$qID = preg_replace( '/.*Q(\d+)$/', '$1', $row['item'] );

				$table[] = [
				'qID' => $qID,
				'title' => $this->person ? $qID : $row['title'],
				];
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
				'prefix' => $this->person ? 'Person' : 'Formula'
			] ) );
	}

}

$maintClass = CreateProfilePages::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
