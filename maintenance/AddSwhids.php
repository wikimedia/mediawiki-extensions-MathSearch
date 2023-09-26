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

use MediaWiki\Extension\MathSearch\Swh\Swhid;
use MediaWiki\MediaWikiServices;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class AddSwhids extends Maintenance {
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
		$this->addDescription( "Script to get the SoftWare Heritage IDentifier (SWHID) from a git url" );
		$this->addOption(
			'repo', 'The repoURL.', false, true, "r"
		);
		$this->addOption( 'force', 'force depositing', false, false, 'f' );
		$this->requireExtension( 'MathSearch' );
		$this->requireExtension( 'LinkedWiki' );
	}

	private function getQuery() {
		return <<<SPARQL
PREFIX wdt: <https://portal.mardi4nfdi.de/prop/direct/>

SELECT ?item ?title
WHERE { ?item wdt:P229 ?title}
SPARQL;
	}

	public function execute() {
		$configFactory = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'wgLinkedWiki' );
		$rf = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$configDefault = $configFactory->get( "SPARQLServiceByDefault" );
		$arrEndpoint = ToolsParser::newEndpoint( $configDefault, null );
		$sp = $arrEndpoint["endpoint"];
		$rs = $sp->query( $this->getQuery() );
		foreach ( $rs['result']['rows'] as $row ) {
			$url = 'https://github.com/cran/' . $row['title'];
			$instance = new Swhid( $rf, $url );
			$instance->saveCodeNow();
		}
	}

}

$maintClass = AddSwhids::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
