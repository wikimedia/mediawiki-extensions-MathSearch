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

use MediaWiki\Extension\MathSearch\Graph\Job\PageCreation;
use MediaWiki\Extension\MathSearch\Graph\Job\SetProfileType;
use MediaWiki\Extension\MathSearch\Graph\Map;
use MediaWiki\Logger\ConsoleSpi;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LogLevel;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Rdbms\IResultWrapper;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class ProfilePages extends Maintenance {
	private readonly string $stateFile;
	private bool $shouldQuit = false;

	private string $jobDate;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Mass perform actions for profile pages." );
		$this->addArg( 'action', 'Action to be performed. ' . $this->printAvailableActions() );
		$this->addArg( 'type', 'Type of profile to be addressed. ', false );
		$this->setBatchSize( 100000 );
		$this->addOption(
			'overwrite', 'Overwrite existing pages with the same name.', false, false, "o"
		);
		$this->addOption( 'filter', 'Add a SPARQL command to filter the pages.
		 Set to an empty string to recreate all pages.
		 Defaults to `FILTER (?sitelinks < 1 ).`.', false, true );
		$this->addOption( 'loglevel', 'Overwrite log level.', false, true );
		$this->requireExtension( 'MathSearch' );
		$this->stateFile = __DIR__ . '/process_state.json';
		$this->jobDate = date( 'ymdhms' );
	}

	private function setupSignalHandler() {
		if ( function_exists( 'pcntl_signal' ) ) {
			pcntl_async_signals( true );
			pcntl_signal( SIGINT, function () {
				$this->output( "\n[Interrupt] Finishing current batch and saving state... Please wait.\n" );
				$this->shouldQuit = true;
			} );
		}
	}

	private function setupLogging(): void {
		if ( !$this->hasOption( 'loglevel' ) ) {
			return;
		}

		$level = $this->getOption( 'loglevel' );
		$validLevels = [
			LogLevel::EMERGENCY,
			LogLevel::ALERT,
			LogLevel::CRITICAL,
			LogLevel::ERROR,
			LogLevel::WARNING,
			LogLevel::NOTICE,
			LogLevel::INFO,
			LogLevel::DEBUG,
		];

		if ( !in_array( $level, $validLevels, true ) ) {
			$this->error( "Unknown log level: $level\n" );
			return;
		}

		LoggerFactory::registerProvider( new ConsoleSpi( [
			'channels' => [
				'MathSearch' => $level,
			],
			'forwardTo' => LoggerFactory::getProvider(),
		] ) );
	}

	private function saveState( int $id ): void {
		file_put_contents( $this->stateFile, json_encode( [
			'last_id' => $id, 'timestamp' => time(), 'jobdate' => $this->jobDate ] ) );
	}

	private function loadState(): int {
		if ( file_exists( $this->stateFile ) ) {
			$data = json_decode( file_get_contents( $this->stateFile ), true );
			if ( isset( $data['jobdate'] ) ) {
				$this->jobDate = (string)$data['jobdate'];
			}
			return $data['last_id'] ?? 0;
		}
		return 0;
	}

	public function execute() {
		$this->setupLogging();

		$profileTypeQIds = $this->getConfig()->get( 'MathString2QMap' )[
			$this->getConfig()->get( 'MathSearchPropertyProfileType' )];
		$action = $this->getArg( 'action' );
		if ( $action === 'fixall' ) {
			$this->setupSignalHandler();
			$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroup();

			$lastId = $this->loadState();
			$entityNamespaceLookup = WikibaseRepo::getEntityNamespaceLookup();
			$entityNamespaces = $entityNamespaceLookup->getEntityNamespaces();

			if ( !isset( $entityNamespaces['item'] ) ) {
				$this->error( "Could not determine the item namespace.\n" );
				return;
			}

			$itemNamespace = $entityNamespaces['item'];
			$dbr = $this->getDB( DB_REPLICA );
			$this->output( ">>> Resuming from Page ID: $lastId\n" );
			$this->output( ">>> Press Ctrl+C at any time to safely stop and save progress.\n" );

			while ( !$this->shouldQuit ) {
				$res = $dbr->newSelectQueryBuilder()
					->select( [ 'page_id', 'page_title' ] )
					->from( 'page' )
					->where( [ 'page_namespace' => $itemNamespace ] )
					->andWhere( "page_id > $lastId" )
					->orderBy( 'page_id', 'ASC' )
					->limit( Map::ROWS_PER_JOB )
					->fetchResultSet();

				if ( $res->numRows() === 0 ) {
					$this->output( "Done! No more items to process.\n" );
					break;
				}

				[ $lastId, $params ] = $this->res2rows( $res );
				$jobQueueGroup->lazyPush( new PageCreation( $params ) );
				// Save state immediately after the batch is finished
				$this->saveState( $lastId );
				$this->output( "Progress: Scheduled up to ID $lastId\n" );

				$this->commitTransactionRound( __METHOD__ );
			}

			if ( $this->shouldQuit ) {
				$this->output( ">>> Process halted. You can resume by running this script again.\n" );
			}
			return;
		}
		$type = $this->getArg( 'type' );
		if ( !isset( $profileTypeQIds[$type] ) ) {
			$this->error( "Unknown type of profile to be created.\n" );
			$this->error( $this->printProfileTypes() );
			return;
		}
		$jobOptions = [
			'overwrite' => $this->getOption( 'overwrite' )
		];
		if ( $action === 'create' ) {
			$jobType = PageCreation::class;
			if ( $this->hasOption( 'filter' ) ) {
				$jobOptions['filter'] = $this->getOption( 'filter', '' );
			}
		} elseif ( $action === 'load' ) {
			$jobType = SetProfileType::class;
			$jobOptions['qType'] = $profileTypeQIds[$type];
		} else {
			$this->error( "Unknown action to be performed.\n" );
			$this->error( $this->printAvailableActions() );
			return;
		}

		( new Map() )->scheduleJobs(
			\Closure::fromCallable( [ $this, 'output' ] ),
			$this->getOption( 'batchSize', $this->getBatchSize() ),
			$type,
			$jobType,
			$jobOptions
		);
	}

	public function printProfileTypes(): string {
		return "Available types are: " . implode( ', ', array_keys( $this->getConfig()->get( 'MathString2QMap' )[
			$this->getConfig()->get( 'MathSearchPropertyProfileType' )] ) ) . "\n";
	}

	public function printAvailableActions(): string {
		return "Available actions are: create, load, fixall.\n
		\tfixall: Iterates overall items, and fixes the corresponding profile pages. Might take very long.\n";
	}

	private function res2rows( IResultWrapper $res ): array {
		$rows = [];
		$lastId = 0;
		foreach ( $res as $row ) {
			$lastId = $row->page_id;
			$rows[$row->page_title] = $row->page_title;
		}
		return [ $lastId, [
			'rows' => $rows,
			'jobname' => 'AllProfilePages' . $this->jobDate,
			'variable_prefix' => true,
			'blank_pages' => true
		] ];
	}

}

$maintClass = ProfilePages::class;
require_once RUN_MAINTENANCE_IF_MAIN;
