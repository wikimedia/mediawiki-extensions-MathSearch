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
	private const BATCH_SIZE = 100;
	/** @var bool */
	private $overwrite;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Mass creates pages from a CSV file " );
		$this->addOption(
			'overwrite', 'Overwrite existing pages with the same name.', false, false, "o"
		);
		$this->requireExtension( 'MathSearch' );
		$this->requireExtension( 'LinkedWiki' );
	}

	public function execute() {
		$jobname = 'import' . date( 'ymdhms' );
		$configFactory = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'wgLinkedWiki' );
		$configDefault = $configFactory->get( "SPARQLServiceByDefault" );
		$arrEndpoint = ToolsParser::newEndpoint( $configDefault, null );
		$sp = $arrEndpoint["endpoint"];
		$table = [];
		$rs = $sp->query( <<<SPARQL
PREFIX wdt: <https://portal.mardi4nfdi.de/prop/direct/>
PREFIX wd: <https://portal.mardi4nfdi.de/entity/>
SELECT ?item ?title
WHERE { ?item wdt:P2 ?title ;
              wdt:P31 wd:Q1025939}
SPARQL
);
		foreach ( $rs['result']['rows'] as $row ) {
			$table[] = [
				'qID' => preg_replace( '/.*Q(\d+)$/', '$1', $row['item'] ),
				'title' => $row['title'],
				'prefix' => 'Formula:'
			];
		}
		$this->overwrite = $this->getOption( 'overwrite' );
		if ( $this->overwrite ) {
			$this->output( "Loaded with option overwrite enabled .\n" );
		}
		$this->output( 'Read ' . count( $table ) . " rows.\n" );
		$parts = array_chunk( $table, self::BATCH_SIZE );
		$partId = 0;
		foreach ( $parts as $part ) {
			$title = Title::newFromText( "Page creator $jobname part $partId" );
			$job = new PageCreationJob( $title, [
				'rows' => $part,
				'jobname' => $jobname,
			] );
			MediaWikiServices::getInstance()->getJobQueueGroup()->push( $job );

			$partId++;
		}
	}

}

$maintClass = CreateProfilePages::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
