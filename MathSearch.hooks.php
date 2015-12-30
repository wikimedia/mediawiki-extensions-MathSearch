<?php
use MediaWiki\Logger\LoggerFactory;

/**
 * MediaWiki MathSearch extension
 *
 * (c) 2012 various MediaWiki contributors
 * GPLv2 license; info in main package.
 */
class MathSearchHooks {
	private static $idGenerators = array();
	/**
	 * LoadExtensionSchemaUpdates handler; set up math table on install/upgrade.
	 *
	 * @param $updater DatabaseUpdater
	 * @throws Exception
	 * @return bool
	 */
	static function onLoadExtensionSchemaUpdates( $updater = null ) {
		global $wgMathWmcServer;
		if ( is_null( $updater ) ) {
			throw new MWException( "Mathsearch extension requires Mediawiki 1.18 or above" );
		}
		$type = $updater->getDB()->getType();
		if ( $type == "mysql" ) {
			$dir = __DIR__ . '/db/';
			$updater->addExtensionTable( 'mathindex', $dir . 'mathindex.sql' );
			$updater->addExtensionTable( 'mathobservation', $dir . 'mathobservation.sql' );
			$updater->addExtensionTable( 'mathvarstat', $dir . 'mathvarstat.sql' );
			$updater->addExtensionTable( 'mathrevisionstat', $dir . 'mathrevisionstat.sql' );
			$updater->addExtensionTable( 'mathsemantics', $dir . 'mathsemantics.sql' );
			$updater->addExtensionTable( 'mathperformance', $dir . 'mathperformance.sql' );
			$updater->addExtensionTable( 'mathidentifier', $dir . 'mathidentifier.sql' );
			$updater->addExtensionTable( 'mathlog', $dir . 'mathlog.sql' );
			$updater->addExtensionTable( 'math_mlp', $dir . 'math_mlp.sql' );
			$updater->addExtensionTable( 'math_review_list', "${dir}math_review_list.sql" );
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
			if ( $updater->tableExists( 'mathlatexml' ) ) {
				// temporary workaround for T117659
				// $updater->addExtensionIndex( 'mathindex', 'fk_mathindex_hash',
				// "$dir/patches/mathindexHashConstraint.sql" );
			}
		} else {
			throw new Exception( "Math extension does not currently support $type database." );
		}
		return true;
	}

	/**
	 * Checks if the db2 php client is installed
	 * @return boolean
	 */
	public static function isDB2Supported() {
		if ( function_exists( 'db2_connect' ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Updates the formula index in the database
	 *
	 * @param int $revId Page-ID
	 * @param int $eid Equation-ID (get updated incrementally for every math element on the page)
	 * @param string $inputHash hash of tex string (used as database entry)
	 * @param string $tex the user input hash
	 */
	private static function updateIndex( $revId, $eid, $inputHash, $tex ) {
		if ( $revId>0 && $eid ) {
			try {
				$dbr = wfGetDB( DB_SLAVE );
				$exists = $dbr->selectRow( 'mathindex',
					array( 'mathindex_revision_id', 'mathindex_anchor', 'mathindex_inputhash' ),
					array(
						'mathindex_revision_id' => $revId,
						'mathindex_anchor' => $eid,
						'mathindex_inputhash' => $inputHash
					)
				);
				if ( $exists ) {
					LoggerFactory::getInstance(
						'MathSearch'
					)->warning( 'Index $' . $tex . '$ already in database.' );
					LoggerFactory::getInstance(
						'MathSearch'
					)->warning( "$revId-$eid with hash " . bin2hex( $inputHash ) );
				} else {
						self::writeMathIndex( $revId, $eid, $inputHash, $tex );
				}
			}
			catch ( Exception $e ) {
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
	 * @param int $id
	 * @param MathRenderer $renderer
	 * @param int $revId
	 * @return bool true if an ID has been assigned manually,
	 * false if the automatic fallback math{$id} was used.
	 */
	public static function setMathId( &$id, MathRenderer $renderer, $revId ) {
		if ( $revId > 0 ) {
			if ( $renderer->getID() ) {
				$id = $renderer->getID();
				return true;
			} else {
				if ( is_null( $id ) ) {
					$id = self::getRevIdGenerator( $revId )
							->guessIdFromContent( $renderer->getUserInputTex() );
					$renderer->setID( $id );
					return true;
				}
				return false;
			}
		}
	}

	/**
	 * Callback function that is called after a formula was rendered
	 * @param Parser $parser
	 * @param MathRenderer $renderer
	 * @param string|null $Result reference to the rendering result
	 * @return bool
	 */
	static function updateMathIndex( Parser $parser, MathRenderer $renderer, &$Result = null ) {
		$revId = $parser->getRevisionId();
		if ( $revId > 0 ) { // Only store something if a pageid was set.
			// Use manually assigned IDs whenever possible
			// and fallback to automatic IDs otherwise.
			if ( self::setMathId( $eid, $renderer, $revId ) === false ) {
				$Result =
					preg_replace( '/(class="mwe-math-mathml-(inline|display))/', "id=\"$eid\" \\1",
						$Result );
			}
			self::updateIndex( $revId, $eid, $renderer->getInputHash(), $renderer->getTex() );
		}
		return true;
	}

	/**
	 * Callback function that is called after a formula was rendered
	 * @param Parser $parser
	 * @param MathRenderer $renderer
	 * @param string|null $Result reference to the rendering result
	 * @return bool
	 */
	static function addIdentifierDescription( Parser $parser, MathRenderer $renderer,
		&$Result = null ) {
		$revId = $parser->getRevisionId();
		self::setMathId( $eid, $renderer, $revId );
		$mo = MathObject::cloneFromRenderer( $renderer );
		$mo->setRevisionID( $revId );
		$mo->setID( $eid );
		$Result = preg_replace_callback( "#<(mi|mo)( ([^>].*?))?>(.*?)</\\1>#u",
			array( $mo, 'addIdentifierTitle' ), $Result );
		return true;
	}

	/**
	 * Callback function that is called after a formula was rendered
	 * @param Parser $parser
	 * @param MathRenderer $renderer
	 * @param string|null $Result reference to the rendering result
	 * @return bool
	 */
	static function addLinkToFormulaInfoPage( Parser $parser, MathRenderer $renderer,
		&$Result = null ) {
		$revId = $parser->getRevisionId();
		if ( $revId == 0 || self::setMathId( $eid, $renderer, $revId ) === false ) {
			return true;
		}
		$url = SpecialPage::getTitleFor( 'FormulaInfo' )->getLocalUrl( array(
			'pid' => $revId,
			'eid' => $eid
		) );
		$Result = "<span><a href=\"$url\" id=\"$eid\" style=\"color:inherit;\">$Result</a></span>";
		return true;
	}

	/**
	 * Alternative Callback function that is called after a formula was rendered
	 * used for test corpus generation for NTCIR11 Math-2
	 * You can enable this alternative hook via setting
	 * <code>$wgHooks['MathFormulaRendered'] = array(
	 *	 'MathSearchHooks::onMathFormulaRenderedNoLink'
	 * );</code>
	 * in your local settings
	 *
	 * @param Parser $parser
	 * @param MathRenderer $renderer
	 * @param null $Result
	 * @return bool
	 */
	static function onMathFormulaRenderedNoLink( Parser $parser, MathRenderer $renderer,
		&$Result = null ) {
		$revId = $parser->getRevisionId();
		if ( ! self::setMathId( $eid, $renderer, $revId ) ) {
			return true;
		}
		if ( $revId > 0 ) { // Only store something if a pageid was set.
			self::updateIndex( $revId, $eid, $renderer->getInputHash(), $renderer->getTex() );
		}
		if ( preg_match( '#<math(.*)?\sid="(?P<id>[\w\.]+)"#', $Result, $matches ) ) {
			$rendererId = $matches['id'];
			$Result = str_replace( $rendererId, $eid, $Result );
		}
		return true;
	}

	/**
	 * Links to the unit test files for the test cases.
	 *
	 * @param string $files
	 * @return boolean (true)
	 */
	static function onRegisterUnitTests( &$files ) {
		$testDir = __DIR__ . '/tests/';
		$files = array_merge( $files, glob( "$testDir/*Test.php" ) );
		return true;
	}

	static function generateMathAnchorString( $revId, $anchorID, $prefix = "#" ) {
		$result = "{$prefix}math.$revId.$anchorID";
		Hooks::run( "MathSearchGenerateAnchorString",
			array( $revId, $anchorID, $prefix, &$result ) );
		return $result;
	}

	/**
	 * @param int $oldID
	 * @param int $eid
	 * @param string $inputHash
	 * @param string $tex
	 */
	public static function writeMathIndex( $oldID, $eid, $inputHash, $tex ) {
		LoggerFactory::getInstance( "MathSearch" )->warning(
			"Store index for \$$tex\$ in database with id $eid for revision $oldID." );
		$dbw = wfGetDB( DB_MASTER );
		$dbw->onTransactionIdle( function () use ( $oldID, $eid, $inputHash, $dbw ) {
			$dbw->replace( 'mathindex', array( 'mathindex_revision_id', 'mathindex_anchor' ), array(
				'mathindex_revision_id' => $oldID,
				'mathindex_anchor' => $eid,
				'mathindex_inputhash' => $inputHash
			) );
		} );
	}

	/**
	 * Register the <mquery> tag with the Parser.
	 *
	 * @param $parser Parser instance of Parser
	 * @return Boolean: true
	 */
	static function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'mquery', array( 'MathSearchHooks', 'mQueryTagHook' ) );
		LoggerFactory::getInstance( 'MathSearch' )->warning( 'mquery tag registered' );
		return true;
	}

	/**
	 * Callback function for the <mquery> parser hook.
	 *
	 * @param $content (the LaTeX+MWS query input)
	 * @param $attributes
	 * @param Parser $parser
	 * @return array
	 */
	static function mQueryTagHook( $content, $attributes, $parser ) {
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

		return array( $renderedMath, "markerType" => 'nowiki' );
	}

	static function onArticleDeleteComplete(
		&$article, User &$user, $reason, $id, $content, $logEntry
	) {
		$revId = $article->getTitle()->getLatestRevID();
		$mathEngineBaseX = new MathEngineBaseX();
		if ( $mathEngineBaseX->update( "", array( $revId ) ) ){
			LoggerFactory::getInstance( 'MathSearch' )->warning( "Deletion of $revId was successful." );
		} else {
			LoggerFactory::getInstance( 'MathSearch' )->warning( "Deletion of $revId failed." );
		}
	}



	/**
	 * Occurs after the save page request has been processed.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 *
	 * @param WikiPage $article
	 * @param User $user
	 * @param Content $content
	 * @param string $summary
	 * @param boolean $isMinor
	 * @param boolean $isWatch
	 * @param $section Deprecated
	 * @param integer $flags
	 * @param Revision|null $revision
	 * @param Status $status
	 * @param integer $baseRevId
	 *
	 * @return boolean
	 */
	public static function onPageContentSaveComplete( $article, $user, $content, $summary, $isMinor,
		$isWatch, $section, $flags, $revision, $status, $baseRevId ) {
		// TODO: Update to JOB
		if ( $revision == null ) {
			LoggerFactory::getInstance(
				'MathSearch'
			)->warning( "Empty update for {$article->getTitle()->getFullText()}." );
			return true;
		}
		$revId = $revision->getId();
		if ( $revision->getContentModel() !== CONTENT_MODEL_WIKITEXT ){
			// Skip pages that do not contain wikitext
			return true;
		}
		$idGenerator = MathIdGenerator::newFromRevisionId( $revId );
		$mathTags= $idGenerator->getMathTags();
		$harvest = "";
		if ( $mathTags ) {
			$dw = new MwsDumpWriter();
			foreach ( $mathTags as $tag ) {
				$id = null;
				$tagContent = $tag[1];
				$attributes = $tag[2];
				// $fullElement = $tag[3];
				$renderer = MathRenderer::getRenderer( $tagContent, $attributes, 'latexml' );
				$renderer->render();
				self::setMathId( $id, $renderer, $revId );
				$dw->addMwsExpression( $renderer->getMathml(), $revId, $id );
			}
			$harvest = $dw->getOutput();
		}
		/** @type Revision|null $previousRev */
		$previousRev = $revision->getPrevious();
		if ( $previousRev != null ) {
			$prevRevId = $previousRev->getId();
			$baseXUpdater = new MathEngineBaseX();
			$res = $baseXUpdater->update( $harvest, array( $prevRevId ) );
		} else {
			$prevRevId = -1;
			$res = false;
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

		return true;
	}

	/**
	 * Enable latexml rendering mode as option by default
	 */
	public static function registerExtension() {
		global $wgMathValidModes;
		if ( ! in_array( 'latexml', $wgMathValidModes ) ) {
			$wgMathValidModes[] = 'latexml';
		}
	}

	/**
	 * @param $revId int
	 * @return MathIdGenerator
	 * @throws MWException
	 */
	private static function getRevIdGenerator( $revId ) {
		if ( !array_key_exists( $revId, MathSearchHooks::$idGenerators ) ) {
			MathSearchHooks::$idGenerators[$revId] = MathIdGenerator::newFromRevisionId( $revId );
		}
		return MathSearchHooks::$idGenerators[$revId];

	}
}
