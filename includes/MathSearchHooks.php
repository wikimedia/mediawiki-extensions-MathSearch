<?php

use MediaWiki\Extension\Math\Hooks\MathFormulaPostRenderRevisionHook;
use MediaWiki\Extension\Math\MathLaTeXML;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Extension\MathSearch\Engine\MathIndex;
use MediaWiki\Extension\MathSearch\RevisionIndexer;
use MediaWiki\Html\Html;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\Hook\PageUndeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\Hook\ParserBeforeInternalParseHook;
use MediaWiki\Parser\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * MediaWiki MathSearch extension
 *
 * (c) 2012 various MediaWiki contributors
 * GPLv2 license; info in main package.
 */
class MathSearchHooks implements
	MathFormulaPostRenderRevisionHook,
	PageDeleteCompleteHook,
	PageUndeleteCompleteHook,
	ParserBeforeInternalParseHook,
	PageSaveCompleteHook,
	ParserFirstCallInitHook
{
	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
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
			$updater->modifyExtensionField( 'mathlog', 'math_input', $dir . 'patch-mathlog-math_input-mediumtext.sql' );
			$updater->addExtensionField( 'mathlog', 'math_params', $dir . 'patch-mathlog-math_params.sql' );
			$updater->modifyExtensionField( 'mathlog', 'math_inputhash', $dir . 'patch-inputhash-binary.sql' );
			$updater->addExtensionTable( 'math_mlp', $dir . 'math_mlp.sql' );
			$updater->addExtensionTable( 'math_review_list', "{$dir}math_review_list.sql" );
			$updater->addExtensionTable( 'math_wbs_entity_map', "{$dir}math_wbs_entity_map.sql" );
			$updater->addExtensionTable( 'math_wbs_text_store', "{$dir}math_wbs_text_store.sql" );
			$updater->addExtensionTable( 'mathpagesimilarity', "{$dir}mathpagesimilarity.sql" );
			$updater->addExtensionTable( 'math_rest_input', "{$dir}math_rest_input.sql" );
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
	 * The hook also runs for text that extensions parse later, such as references from templates,
	 * which is not the wikitext of the revision; the first call of a parse is the one with it.
	 * @inheritDoc
	 */
	public function onParserBeforeInternalParse( $parser, &$text, $stripState ) {
		$revId = $parser->getRevisionId();
		$output = $parser->getOutput();
		// Parser tests pass a revision id without a stored revision
		if ( $revId && !$output->getExtensionData( 'mathsearch-anchors' ) && $parser->getRevisionRecordObject() ) {
			$output->setExtensionData( 'mathsearch-anchors', true );
			$text = ( new MathIdGenerator( $text, $revId ) )->addAnchorsTo();
		}
	}

	/** @inheritDoc */
	public function onMathFormulaPostRenderRevision(
		?RevisionRecord $revisionRecord, MathRenderer $renderer, string &$renderedMath
	) {
		global $wgMathSearchLinkToInfoPage, $wgMathSearchInfoPage;
		$revId = $revisionRecord?->getId();
		$eid = $renderer->getID();
		if ( $wgMathSearchLinkToInfoPage && $revId && $eid !== '' ) {
			$url = SpecialPage::getTitleFor( $wgMathSearchInfoPage )->getLocalURL( [ 'pid' => $revId, 'eid' => $eid ] );
			$renderedMath = Html::rawElement( 'span', [],
				Html::rawElement( 'a', [ 'href' => $url, 'style' => 'color:inherit;' ], $renderedMath ) );
		}
	}

	/**
	 * For a test corpus like the one of NTCIR-11 Math-2, the ids that LaTeXML gives the elements
	 * of a formula start with the anchor of the formula, so they are unique on the page.
	 * Enable it in LocalSettings.php with
	 * <code>$wgHooks['MathFormulaPostRenderRevision'][] = 'MathSearchHooks::onMathFormulaRenderedNoLink';</code>
	 *
	 * @param RevisionRecord|null $revisionRecord
	 * @param MathRenderer $renderer
	 * @param string|null &$Result
	 * @return bool
	 */
	public static function onMathFormulaRenderedNoLink(
		?RevisionRecord $revisionRecord, MathRenderer $renderer, &$Result = null
	) {
		$anchor = $renderer->getID();
		if ( $anchor && $Result !== null && preg_match( '#<math(.*)?\sid="(?P<id>[\w\.]+)"#', $Result, $matches ) ) {
			$Result = str_replace( $matches['id'], $anchor, $Result );
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

	public function onPageDeleteComplete(
		ProperPageIdentity $page,
		Authority $deleter,
		string $reason,
		int $pageID,
		RevisionRecord $deletedRev,
		ManualLogEntry $logEntry,
		int $archivedRevisionCount
	) {
		$revId = $deletedRev->getId();
		$engine = new MathIndex( $this->connectionProvider->getPrimaryDatabase() );
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
	 * @inheritDoc
	 *
	 * This occurs when an article is undeleted (restored).
	 * The formulae of the undeleted article are restored then in the index.
	 */
	public function onPageUndeleteComplete(
		ProperPageIdentity $page,
		Authority $restorer,
		string $reason,
		RevisionRecord $restoredRev,
		ManualLogEntry $logEntry,
		int $restoredRevisionCount,
		bool $created,
		array $restoredPageIds ): void {
		if ( !MathIdGenerator::indexesModel(
			$restoredRev->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )->getModel()
		) ) {
			return;
		}
		$revId = $restoredRev->getId();

		if ( !$revId ) {
			// No revision to restore
			return;
		}
		$this->getIndexer()->indexRevision( $restoredRev );
	}

	/**
	 * Occurs after the save page request has been processed.
	 * @inheritDoc
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 */
	public function onPageSaveComplete(
		$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
	) {
		if ( !MathIdGenerator::indexesModel(
			$revisionRecord->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )->getModel()
		) ) {
			return true;
		}
		if ( !$revisionRecord->getId() ) {
			return true;
		}
		$this->getIndexer()->indexRevision( $revisionRecord );
		return true;
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

	private function getIndexer(): RevisionIndexer {
		return new RevisionIndexer( $this->connectionProvider );
	}

}
