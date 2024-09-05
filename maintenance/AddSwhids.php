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

use DataValues\StringValue;
use DataValues\TimeValue;
use MediaWiki\Extension\MathSearch\Swh\Swhid;
use MediaWiki\User\User;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Repo\WikibaseRepo;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class AddSwhids extends Maintenance {

	/** @var EntityLookup */
	private $entityLookup;
	/** @var EntityStore */
	private $entityStore;
	/** @var GuidGenerator */
	private $guidGenerator;
	/** @var User */
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
	}

	private function getQuery() {
		return <<<SPARQL
PREFIX wdt: <https://portal.mardi4nfdi.de/prop/direct/>

SELECT ?item ?repo
WHERE {
  ?item wdt:P339 ?repo.
  FILTER NOT EXISTS { ?item wdt:P1454 ?x }
}
SPARQL;
	}

	public function execute() {
		$services = $this->getServiceContainer();
		$configFactory = $services->getConfigFactory()->makeConfig( 'wgLinkedWiki' );
		$rf = $services->getHttpRequestFactory();
		$configDefault = $configFactory->get( "SPARQLServiceByDefault" );
		$arrEndpoint = ToolsParser::newEndpoint( $configDefault, null );
		$sp = $arrEndpoint["endpoint"];
		$this->entityLookup = WikibaseRepo::getEntityLookup();
		$this->entityStore = WikibaseRepo::getEntityStore();
		$this->guidGenerator = new GuidGenerator();
		$this->mwUser = $services->getUserFactory()->newFromName( 'swh import' );
		$exists = ( $this->mwUser->idForName() !== 0 );
		if ( !$exists ) {
			$services->getAuthManager()->autoCreateUser(
				$this->mwUser,
				MediaWiki\Auth\AuthManager::AUTOCREATE_SOURCE_MAINT,
				false
			);
		}
		$rs = $sp->query( $this->getQuery() );
		foreach ( $rs['result']['rows'] as $row ) {
			$url = $row['repo'];
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
			new StringValue( $swhid ) );
		$snakUrl = new PropertyValueSnak(
			NumericPropertyId::newFromNumber( $wgMathSearchPropertyScrUrl ),
			 new StringValue( $url ) );
		$time = new DateTimeImmutable( $pit );
		// Currently DAY is the maximal precision and the time must be 0:00:00 T57755
		$date = new TimeValue(
			$time->format( '\+Y-m-d\T00:00:00\Z' ),
			0, 0, 0,
			TimeValue::PRECISION_DAY,
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
		$this->entityStore->saveEntity( $item,
			"SWHID from Software Heritage",
			$this->mwUser,
			EDIT_FORCE_BOT );
	}

}

$maintClass = AddSwhids::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;
