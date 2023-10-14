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

use DataValues\TimeValue;
use MediaWiki\Extension\MathSearch\Swh\Swhid;
use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\WikibaseRepo;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class AddSwhids extends Maintenance {

	private $entityLookup;
	private $snakFactory;
	private $entityStore;
	private $guidGenerator;
	private $mwUser;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Script to get the SoftWare Heritage IDentifier (SWHID) from a git url" );
		$this->addOption(
			'repo', 'The repoURL.', false, true, "r"
		);
		$this->addOption( 'force', 'force depositing', false, false, 'f' );
		$this->requireExtension( 'MathSearch' );
		$this->requireExtension( 'LinkedWiki' );
		$this->entityLookup = WikibaseRepo::getEntityLookup();
		$this->snakFactory = WikibaseRepo::getSnakFactory();
		$this->entityStore = WikibaseRepo::getEntityStore();
		$this->guidGenerator = new GuidGenerator();
		$this->mwUser =
			MediaWikiServices::getInstance()->getUserFactory()->newFromName( 'swh import' );
		$exists = ( $this->mwUser->idForName() !== 0 );
		if ( !$exists ) {
			MediaWikiServices::getInstance()->getAuthManager()->autoCreateUser(
				$this->mwUser,
				MediaWiki\Auth\AuthManager::AUTOCREATE_SOURCE_MAINT,
				false
			);
		}
	}

	private function getQuery() {
		return <<<SPARQL
PREFIX wdt: <https://portal.mardi4nfdi.de/prop/direct/>

SELECT ?item ?title
WHERE {
  ?item wdt:P229 ?title.
  FILTER NOT EXISTS { ?item wdt:P1454 ?x }
}
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
			$instance->fetchOrSave();
			if ( $instance->getSnapshot() !== null ) {
				$qID = preg_replace( '/.*Q(\d+)$/', '$1', $row['item'] );
				$this->createWbItem( $qID, $instance->getSnapshot(), $url, $instance->getSnapshotDate() );
			}
			if ( $instance->getStatus() === 429 ) {
				sleep( $instance->getWait() );
			}
		}
	}

	public function createWbItem( $qID, $swhid, $url, $pit ) {
		global $wgMathSearchPropertySwhid,
			   $wgMathSearchPropertyScrUrl,
			   $wgMathSearchPropertyPointInTime;

		$mainSnak = new PropertyValueSnak(
			NumericPropertyId::newFromNumber( $wgMathSearchPropertySwhid ),
			$swhid );
		$snakUrl = new PropertyValueSnak(
			NumericPropertyId::newFromNumber( $wgMathSearchPropertyScrUrl ),
			 $url );
		$time = new DateTimeImmutable( $pit );
		$date = new TimeValue(
			$time->format( 'Y-m-d\TH:i:s\Z' ),
			0, 0, 0,
			TimeValue::PRECISION_SECOND,
			TimeValue::CALENDAR_GREGORIAN
		);
		$snakTime = new PropertyValueSnak(
			NumericPropertyId::newFromNumber( $wgMathSearchPropertyPointInTime ),
			$date );
		$item = $this->entityLookup->getEntity( ItemId::newFromNumber( $qID ) );
		$statements = $item->getStatements();
		$statements->addNewStatement(
			$mainSnak,
			[ $snakUrl, $snakTime ],
			null,
			$this->guidGenerator->newGuid( $item->getId() ) );
		$item->setStatements( $statements );
		$this->entityStore->saveEntity( $item, "SWHID from Software Heritage", $this->mwUser );
	}

}

$maintClass = AddSwhids::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
