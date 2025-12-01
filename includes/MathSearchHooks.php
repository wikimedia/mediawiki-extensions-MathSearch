<?php

use MediaWiki\Extension\Math\Hooks\MathFormulaPostRenderRevisionHook;
use MediaWiki\Extension\Math\MathLaTeXML;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Extension\MathSearch\Engine\BaseX;
use MediaWiki\Extension\MathSearch\Engine\MathIndex;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ArticleDeleteCompleteHook;
use MediaWiki\Page\Hook\ArticleUndeleteHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\DBConnRef;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * MediaWiki MathSearch extension
 *
 * (c) 2012 various MediaWiki contributors
 * GPLv2 license; info in main package.
 */
class MathSearchHooks implements
	ArticleDeleteCompleteHook,
	ArticleUndeleteHook,
	MathFormulaPostRenderRevisionHook,
	PageSaveCompleteHook,
	ParserFirstCallInitHook
{
	/** @var MathIdGenerator[] */
	private array $idGenerators = [];

	public function __construct(
		private IConnectionProvider $connectionProvider,
		private RevisionLookup $revisionLookup
	) {
	}

	/**
	 * LoadExtensionSchemaUpdates handler; set up math table on install/upgrade.
	 *
	 * @param DatabaseUpdater|null $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( ?DatabaseUpdater $updater = null ) {
		global $wgMathWmcServer;
		$type = $updater->getDB()->getType();
		if ( $type == "mysql" ) {
			$dir = __DIR__ . '/../db/';
			$updater->addExtensionTable( 'mathindex', $dir . 'mathindex.sql' );
			$updater->addExtensionTable( 'mathobservation', $dir . 'mathobservation.sql' );
			$updater->addExtensionTable( 'mathvarstat', $dir . 'mathvarstat.sql' );
			$updater->addExtensionTable( 'mathrevisionstat', $dir . 'mathrevisionstat.sql' );
			$updater->addExtensionTable( 'mathsemantics', $dir . 'mathsemantics.sql' );
			$updater->addExtensionTable( 'mathperformance', $dir . 'mathperformance.sql' );
			$updater->addExtensionTable( 'mathidentifier', $dir . 'mathidentifier.sql' );
			$updater->addExtensionTable( 'mathlog', $dir . 'mathlog.sql' );
			$updater->addExtensionTable( 'math_mlp', $dir . 'math_mlp.sql' );
			$updater->addExtensionTable( 'math_review_list', "{$dir}math_review_list.sql" );
			$updater->addExtensionTable( 'math_wbs_entity_map', "{$dir}math_wbs_entity_map.sql" );
			$updater->addExtensionTable( 'math_wbs_text_store', "{$dir}math_wbs_text_store.sql" );
			$updater->addExtensionTable( 'mathpagesimilarity', "{$dir}mathpagesimilarity.sql" );
			if ( $wgMathWmcServer ) {
				$wmcDir = $dir . 'wmc/persistent/';
				$updater->addExtensionTable( 'math_wmc_ref', $wmcDir . "math_wmc_ref.sql" );
				$updater->addExtensionTable( 'math_wmc_runs', $wmcDir . "math_wmc_runs.sql" );
				$updater->addExtensionTable( 'math_wmc_results', $wmcDir . "math_wmc_results.sql" );
				$updater->addExtensionTable( 'math_wmc_assessed_formula',
					$wmcDir . "math_wmc_assessed_formula.sql" );
				$updater->addExtensionTable( 'math_wmc_assessed_revision',
					$wmcDir . "math_wmc_assessed_revision.sql" );

			}
		} else {
			throw new Exception( "MathSearch extension does not currently support $type database." );
		}
		return true;
	}

	/**
	 * Updates the formula index in the database
	 *
	 * @param int $revId Page-ID
	 * @param string $eid Equation-ID (get updated incrementally for every math element on the page)
	 * @param MathRenderer $renderer
	 * @param ?DBConnRef $dbr
	 */
	private function updateIndex(
		int $revId, string $eid, MathRenderer $renderer, ?DBConnRef $dbr = null
	) {
		if ( $revId > 0 && $eid ) {
			try {
				$inputHash = $renderer->getInputHash();
				$tex = $renderer->getTex();
				$mo = MathObject::cloneFromRenderer( $renderer );
				if ( !$mo->isInDatabase() ) {
					$mo->writeToCache();
				}
				( new BaseX() )->storeMathObject( $mo );
				$exists = ( $dbr ?? $this->connectionProvider
					->getReplicaDatabase() )->selectRow( 'mathindex',
					[ 'mathindex_revision_id', 'mathindex_anchor', 'mathindex_inputhash' ],
					[
						'mathindex_revision_id' => $revId,
						'mathindex_anchor' => $eid,
						'mathindex_inputhash' => $inputHash
					]
				);
				if ( $exists ) {
					LoggerFactory::getInstance(
						'MathSearch'
					)->warning( 'Index $' . $tex . '$ already in database.' );
					LoggerFactory::getInstance(
						'MathSearch'
					)->warning( "$revId-$eid with hash " . bin2hex( $inputHash ) );
				} else {
						$this->writeMathIndex( $revId, $eid, $inputHash, $tex );
				}
			} catch ( Exception $e ) {
				LoggerFactory::getInstance( "MathSearch" )->error( 'Problem writing to math index!'
					. ' You might want the rebuild the index by running:'
					. '"php extensions/MathSearch/ReRenderMath.php". The error is'
					. $e->getMessage() );
			}
		}
	}

	/**
	 * Changes the specified defaultID given as argument ID to
	 * either the manually assignedID from the MathTag or
	 * prefixes it with "math" to increase the probability of
	 * having a unique id that can be referenced via the anchor
	 * #math{$id}.
	 * @param string &$id
	 * @param MathRenderer $renderer
	 * @param int $revId
	 * @return bool|null true if an ID has been assigned manually,
	 * false if the automatic fallback math{$id} was used.
	 */
	public function setMathId( &$id, MathRenderer $renderer, $revId ) {
		if ( $revId > 0 ) {
			if ( $renderer->getID() ) {
				$id = $renderer->getID();
				return true;
			} else {
				if ( $id === null ) {
					try {
						$id = $this->getRevIdGenerator( $revId )->guessIdFromContent( $renderer->getUserInputTex() );
					} catch ( Exception $e ) {
						LoggerFactory::getInstance( "MathSearch" )->warning( "Error generating Math ID", [ $e ] );
						return false;
					}
					$renderer->setID( $id );
					return true;
				}
				return false;
			}
		}
	}

	/**
	 * Callback function that is called after a formula was rendered
	 *
	 * @param RevisionRecord|null $revisionRecord
	 * @param MathRenderer $renderer
	 * @param string|null &$Result
	 * @return true
	 */
	public function onMathFormulaPostRenderRevision(
		?RevisionRecord $revisionRecord,
		MathRenderer $renderer,
		&$Result = null
	): bool {
		$revId = $revisionRecord?->getId() ?? 0;
		$this->addLinkToFormulaInfoPage( $revId, $renderer, $Result );
		$this->updateMathIndex( $revId, $renderer, $Result );
		return true;
	}

	/**
	 * Callback function that is called after a formula was rendered
	 *
	 * @param int $revId
	 * @param MathRenderer $renderer
	 * @param string|null &$Result reference to the rendering result
	 */
	private function updateMathIndex(
		int $revId,
		MathRenderer $renderer,
		?string &$Result = null
	): void {
		// Only store something if a pageid was set.
		if ( $revId === 0 ) {
			return;
		}
		// Use manually assigned IDs whenever possible
		// and fallback to automatic IDs otherwise.
		$hasEid = $this->setMathId( $eid, $renderer, $revId );
		if ( $eid === null ) {
			return;
		}
		if ( $hasEid === false ) {
			$Result =
				preg_replace( '/(class="mwe-math-mathml-(inline|display))/', "id=\"$eid\" \\1",
					$Result );
		}
		$this->updateIndex( $revId, $eid, $renderer );
	}

	/**
	 * Callback function that is called after a formula was rendered
	 * @param Parser $parser
	 * @param MathRenderer $renderer
	 * @param string|null &$Result reference to the rendering result
	 * @return bool
	 */
	public function addIdentifierDescription(
		Parser $parser, MathRenderer $renderer, &$Result = null
	) {
		$revId = $parser->getRevisionId();
		$this->setMathId( $eid, $renderer, $revId );
		$mo = MathObject::cloneFromRenderer( $renderer );
		$mo->setRevisionID( $revId );
		$mo->setID( $eid );
		$Result = preg_replace_callback( "#<(mi|mo)( ([^>].*?))?>(.*?)</\\1>#u",
			[ $mo, 'addIdentifierTitle' ], $Result );
		return true;
	}

	/**
	 * Callback function that is called after a formula was rendered
	 *
	 * @param int $revId
	 * @param MathRenderer $renderer
	 * @param string|null &$Result reference to the rendering result
	 */
	private function addLinkToFormulaInfoPage(
		int $revId,
		MathRenderer $renderer,
		?string &$Result = null
	): void {
		global $wgMathSearchInfoPage;
		if ( $revId === 0 || $this->setMathId( $eid, $renderer, $revId ) === false ) {
			return;
		}
		$url = SpecialPage::getTitleFor( $wgMathSearchInfoPage )->getLocalURL( [
			'pid' => $revId,
			'eid' => $eid
		] );
		$Result = "<span><a href=\"$url\" id=\"$eid\" style=\"color:inherit;\">$Result</a></span>";
	}

	/**
	 * Alternative Callback function that is called after a formula was rendered
	 * used for test corpus generation for NTCIR11 Math-2
	 * You can enable this alternative hook via setting
	 * <code>$wgHooks['MathFormulaRendered'] = array(
	 * 	 'MathSearchHooks::onMathFormulaRenderedNoLink'
	 * );</code>
	 * in your local settings
	 *
	 * @param Parser $parser
	 * @param MathRenderer $renderer
	 * @param string|null &$Result
	 * @return bool
	 */
	public function onMathFormulaRenderedNoLink(
		Parser $parser, MathRenderer $renderer, &$Result = null
	) {
		$revId = $parser->getRevisionId();
		if ( !$this->setMathId( $eid, $renderer, $revId ) ) {
			return true;
		}
		if ( $revId > 0 ) { // Only store something if a pageid was set.
			$this->updateIndex( $revId, $eid, $renderer );
		}
		if ( preg_match( '#<math(.*)?\sid="(?P<id>[\w\.]+)"#', $Result, $matches ) ) {
			$rendererId = $matches['id'];
			$Result = str_replace( $rendererId, $eid, $Result );
		}
		return true;
	}

	public static function generateMathAnchorString( $revId, $anchorID, $prefix = "#" ) {
		$result = "{$prefix}math.$revId.$anchorID";
		MediaWikiServices::getInstance()->getHookContainer()->run( "MathSearchGenerateAnchorString",
			[ $revId, $anchorID, $prefix, &$result ] );
		return $result;
	}

	/**
	 * @param int $oldID
	 * @param string $eid
	 * @param string $inputHash
	 * @param string $tex
	 */
	public function writeMathIndex( $oldID, $eid, $inputHash, $tex ) {
		LoggerFactory::getInstance( "MathSearch" )->warning(
			"Store index for \$$tex\$ in database with id $eid for revision $oldID." );
		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$fname = __METHOD__;
		$dbw->onTransactionCommitOrIdle( static function () use ( $oldID, $eid, $inputHash, $dbw, $fname ) {
			$dbw->replace( 'mathindex', [ [ 'mathindex_revision_id', 'mathindex_anchor' ] ], [
				'mathindex_revision_id' => $oldID,
				'mathindex_anchor' => $eid,
				'mathindex_inputhash' => $inputHash
			], $fname );
		}, $fname );
	}

	/**
	 * Register the <mquery> tag with the Parser.
	 *
	 * @param Parser $parser instance of Parser
	 * @return bool true
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'mquery', [ 'MathSearchHooks', 'mQueryTagHook' ] );
		LoggerFactory::getInstance( 'MathSearch' )->debug( 'mquery tag registered' );
		return true;
	}

	/**
	 * Callback function for the <mquery> parser hook.
	 *
	 * @param string $content the LaTeX+MWS query input
	 * @param array $attributes
	 * @param Parser $parser
	 * @return string|string[]
	 */
	public static function mQueryTagHook( $content, $attributes, $parser ) {
		global $wgMathDefaultLaTeXMLSetting;
		if ( trim( $content ) === '' ) { // bug 8372
			return '';
		}
		LoggerFactory::getInstance( 'MathSearch' )->debug( 'Render mquery tag.' );
		// TODO: Report %\n problem to LaTeXML upstream
		$content = preg_replace( '/%\n/', '', $content );
		$renderer = new MathLaTeXML( $content );
		$mQuerySettings = $wgMathDefaultLaTeXMLSetting;
		$mQuerySettings['preload'][] = 'mws.sty';
		$renderer->setLaTeXMLSettings( $mQuerySettings );
		$renderer->render();
		$renderedMath = $renderer->getHtmlOutput();
		$renderer->writeCache();

		return [ $renderedMath, "markerType" => 'nowiki' ];
	}

	public function onArticleDeleteComplete(
		$wikiPage, $user, $reason, $id, $content, $logEntry, $archivedRevisionCount
	) {
		$revId = $wikiPage->getTitle()->getLatestRevID();
		$engine = new MathIndex();
		$updated = false;
		$detail = '';
		try {
			$updated = $engine->delete( $revId );
		} catch ( Exception $e ) {
			$detail = $e->getMessage();
		}
		if ( $updated ) {
			LoggerFactory::getInstance( 'MathSearch' )->info( "Deletion of $revId was successful." );
		} else {
			LoggerFactory::getInstance( 'MathSearch' )->warning( "Deletion of $revId failed: $detail" );
		}
	}

	/**
	 * This occurs when an article is undeleted (restored).
	 * The formulae of the undeleted article are restored then in the index.
	 * @param Title $title Title corresponding to the article restored
	 * @param bool $create Whether the restoration caused the page to be created.
	 * @param string $comment Comment explaining the undeletion.
	 * @param int $oldPageId ID of page previously deleted. ID will be used for restored page.
	 * @param array $restoredPages Set of page IDs that have revisions restored for undelete.
	 * @return true
	 */
	public function onArticleUndelete(
		$title, $create, $comment, $oldPageId, $restoredPages
	) {
		if ( $this->revisionLookup
				->getRevisionByPageId( $oldPageId )
				->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )
				->getModel() !== CONTENT_MODEL_WIKITEXT
		) {
			// Skip pages that do not contain wikitext
			return true;
		}
		$revId = $title->getLatestRevID();
		$harvest = $this->getIndexUpdates( $revId );

		$mathEngineBaseX = new MathIndex();
		$updated = false;
		$detail = '';
		try {
			$updated = $mathEngineBaseX->update( $harvest );
		} catch ( Exception $e ) {
			$detail = $e->getMessage();
		}
		if ( $updated ) {
			LoggerFactory::getInstance( 'MathSearch' )->warning( "Restoring of $revId was successful." );

		} else {
			LoggerFactory::getInstance( 'MathSearch' )->warning( "Restoring of $revId failed: $detail" );
		}
		return true;
	}

	/**
	 * Occurs after the save page request has been processed.
	 * @inheritDoc
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 */
	public function onPageSaveComplete(
		$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
	) {
		if ( $revisionRecord
			->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )
			->getModel() !== CONTENT_MODEL_WIKITEXT
		) {
			// Skip pages that do not contain wikitext
			return true;
		}
		$prevRevId = -1;
		$revId = $revisionRecord->getId();
		$harvest = $this->getIndexUpdates( $revId );
		if ( count( $harvest ) === 0 ) {
			// No math to index
			return true;
		}
		$previousRevisionRecord = $this->revisionLookup
			->getPreviousRevision( $revisionRecord );
		$res = false;
		$index = new MathIndex();
		try {
			if ( $previousRevisionRecord != null ) {
				$prevRevId = $previousRevisionRecord->getId();
				# delete the entries previous revision.
				$res = $index->update( $harvest, [ $prevRevId ] );
			} else {
				# just create a new entry in index.
				$res = $index->update( $harvest, [] );
			}
		} catch ( Exception $e ) {
			LoggerFactory::getInstance( 'MathSearch' )
				->warning( 'Harvest update failed: {exception}',
					[ 'exception' => $e->getMessage() ] );
		}
		if ( $res ) {
			LoggerFactory::getInstance(
				'MathSearch'
			)->warning( "Update for $revId (was $prevRevId) successful." );
		} else {
			LoggerFactory::getInstance(
				'MathSearch'
			)->warning( "Update for $revId (was $prevRevId) failed." );
		}
	}

	/**
	 * Enable latexml rendering mode as option by default
	 */
	public static function registerExtension() {
		global $wgMathValidModes;
		if ( !in_array( 'latexml', $wgMathValidModes ) ) {
			$wgMathValidModes[] = 'latexml';
		}
	}

	/**
	 * @param int $revId
	 * @return MathIdGenerator
	 */
	private function getRevIdGenerator( $revId ) {
		$this->idGenerators[$revId] ??= MathIdGenerator::newFromRevisionId( $revId );
		return $this->idGenerators[$revId];
	}

	protected function getIndexUpdates( ?int $revId ): array {
		$idGenerator = MathIdGenerator::newFromRevisionId( $revId );
		$mathTags = $idGenerator->getMathTags();
		$harvest = [];
		try {
			if ( $mathTags ) {
				foreach ( $mathTags as $tag ) {
					$id = null;
					$tagContent = $tag[MathIdGenerator::CONTENT_POS];
					$attributes = $tag[MathIdGenerator::ATTRIB_POS];
					$renderer = MathRenderer::getRenderer( $tagContent, $attributes, 'latexml' );
					$renderer->render();
					$this->setMathId( $id, $renderer, $revId );
					$harvest[] = [
						'mathindex_revision_id' => $revId,
						'mathindex_anchor' => $id,
						'mathindex_inputhash' => $renderer->getInputHash()
					];
				}
			}
		} catch ( Exception $e ) {
			LoggerFactory::getInstance( 'MathSearch' )
				->warning( 'Harvest strinc can not be generated: {exception}',
					[ 'exception' => $e->getMessage() ] );
		}
		return $harvest;
	}
}
