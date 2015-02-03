<?php
/**
 * MediaWiki MathSearch extension
 *
 * (c) 2012 various MediaWiki contributors
 * GPLv2 license; info in main package.
 */

class MathSearchHooks {

	/**
	 * LoadExtensionSchemaUpdates handler; set up math table on install/upgrade.
	 *
	 * @param $updater DatabaseUpdater
	 * @throws MWException
	 * @return bool
	 */
	static function onLoadExtensionSchemaUpdates( $updater = null ) {
		global $wgMathWmcServer;
		if ( is_null( $updater ) ) {
			throw new MWException( "Mathsearch extension requires Mediawiki 1.18 or above" );
		}
 		$type = $updater->getDB()->getType();
 		if ( $type == "mysql"  ) {
			$dir = __DIR__ . '/db/' ;
			$updater->addExtensionTable( 'mathindex', $dir . 'mathindex.sql' );
			$updater->addExtensionTable( 'mathobservation',  $dir . 'mathobservation.sql' );
			$updater->addExtensionTable( 'mathvarstat', $dir . 'mathvarstat.sql' );
			$updater->addExtensionTable( 'mathpagestat', $dir . 'mathpagestat.sql' );
		    $updater->addExtensionTable( 'mathsemantics', $dir . 'mathsemantics.sql' );
		    $updater->addExtensionTable( 'mathperformance', $dir . 'mathperformance.sql' );
			if ( $wgMathWmcServer ){
				$updater->addExtensionTable( 'math_wmc_ref', $dir . "math_wmc_ref.sql");
				$updater->addExtensionTable( 'math_wmc_runs', $dir . "math_wmc_runs.sql");
				$updater->addExtensionTable( 'math_wmc_results', $dir . "math_wmc_results.sql");
				$updater->addExtensionTable( 'math_wmc_assessed_formula', $dir . "math_wmc_assessed_formula.sql");
				$updater->addExtensionTable( 'math_wmc_assessed_revision', $dir . "math_wmc_assessed_revision.sql");

			}
			$updater->addExtensionTable( 'mathidentifier', $dir . 'mathidentifier.sql' );
 		} elseif ( $type == 'sqlite' ){
			// Don't scare Jenkins with an exception.
		} else {
 			throw new Exception( "Math extension does not currently support $type database." );
 		}
		return true;
	}

	/**
	 * Checks if the db2 php client is installed
	 * @return boolean
	 */
	public static function isDB2Supported(){
		if ( function_exists('db2_connect') ){
			return true;
		} else {
			return false;
		}
	}

	private static function curId2OldId( $curId ){
		$title = Title::newFromID( $curId );
		if ( $title ){
			return Title::newFromID( $curId )->getLatestRevID();
		} else {
			return 0;
		}
	}
	/**
	 * Updates the formula index in the database
	 *
	 * @param int $pid Page-ID
	 * @param int $eid Equation-ID (get updated incrementally for every math element on the page)
	 * @param string $inputHash hash of tex string (used as database entry)
	 * @param string $tex the user input hash
	 */
	private static function updateIndex($pid, $eid, $inputHash, $tex){
		try {
			$oldID = self::curId2OldId( $pid );
			$dbr = wfGetDB( DB_SLAVE );
			$exists = $dbr->selectRow( 'mathindex',
				array( 'mathindex_revision_id', 'mathindex_anchor', 'mathindex_inputhash' ),
				array(
					'mathindex_revision_id' => $oldID,
					'mathindex_anchor' => $eid,
					'mathindex_inputhash' => $inputHash)
			) ;
			if ( $exists ) {
				wfDebugLog( "MathSearch", 'Index $' . $tex . '$ already in database.' );
			} else {
				self::writeMathIndex( $oldID, $eid, $inputHash, $tex );
			}
		} catch ( Exception $e ) {
			wfDebugLog( "MathSearch", 'Problem writing to math index!'
				. ' You might want the rebuild the index by running:'
				. '"php extensions/MathSearch/ReRenderMath.php". The error is'
				. $e->getMessage() );
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
	 * @return bool true if an ID has been assigned manually,
	 * false if the automatic fallback math{$id} was used.
	 */
	private static function setMathId( &$id, MathRenderer $renderer) {
		if ( $renderer->getID() ){
			$id = $renderer->getID();
			return true;
		} else {
			$id = "math{$id}";
			return false;
		}
	}

	/**
	 * Callback function that is called after a formula was rendered
	 * @param MathRenderer $Renderer
	 * @param string|null $Result reference to the rendering result
	 * @param int $pid
	 * @param int $eid
	 * @return bool
	 */
	static function updateMathIndex( MathRenderer $Renderer, &$Result = null, $pid = 0, $eid = 0 ) {
		if ( $pid > 0 ) { // Only store something if a pageid was set.
			// Use manually assigned IDs whenever possible
			// and fallback to automatic IDs otherwise.
			if ( ! self::setMathId( $eid , $Renderer ) ){
				$Result = preg_replace( '/(class="mwe-math-mathml-(inline|display))/', "id=\"$eid\" \\1", $Result );
			}
			self::updateIndex( $pid , $eid , $Renderer->getInputHash() , $Renderer->getTex() );
		}
		return true;
	}

	/**
	 * Callback function that is called after a formula was rendered
	 * @param MathRenderer $Renderer
	 * @param string|null $Result reference to the rendering result
	 * @param int $pid
	 * @param int $eid
	 * @return bool
	 */
	static function addIdentifierDescription( MathRenderer $Renderer, &$Result = null, $pid = 0, $eid = 0 ) {
		self::setMathId( $eid , $Renderer );
		$mo = MathObject::cloneFromRenderer($Renderer);
		$mo->setRevisionID($pid);
		$mo->setID($eid);
		$Result = preg_replace_callback("#<(mi|mo)( ([^>].*?))?>(.*?)</\\1>#u", array( $mo , 'addIdentifierTitle' ), $Result);
		return true;
	}

	/**
	 * Callback function that is called after a formula was rendered
	 * @param MathRenderer $Renderer
	 * @param string|null $Result reference to the rendering result
	 * @param int $pid
	 * @param int $eid
	 * @return bool
	 */
	static function addLinkToFormulaInfoPage( MathRenderer $Renderer, &$Result = null, $pid = 0, $eid = 0 ) {
		self::setMathId( $eid , $Renderer );
		$url = SpecialPage::getTitleFor( 'FormulaInfo' )->getLocalUrl( array( 'pid' => $pid, 'eid' => $eid ) );
		$Result = "<span><a href=\"$url\" id=\"$eid\" style=\"color:inherit;\">$Result</a></span>";
		return true;
	}

	/**
	 * Alternative Callback function that is called after a formula was rendered
	 * used for test corpus generation for NTCIR11 Math-2
	 * You can enable this alternative hook via setting
	 * <code>$wgHooks['MathFormulaRendered'] = array('MathSearchHooks::onMathFormulaRenderedNoLink');</code>
	 * in your local settings
	 *
	 * @param MathRenderer $Renderer
	 * @param null $Result
	 * @param int $pid
	 * @param int $eid
	 * @return boolean (true)
	 */
	static function onMathFormulaRenderedNoLink( $Renderer, &$Result = null, $pid = 0, $eid = 0 ) {
		if ( $pid > 0 ) { // Only store something if a pageid was set.
			self::updateIndex( $pid, $eid, $Renderer->getInputHash(), $Renderer->getTex() );
		}
		if ( preg_match( '#<math(.*)?\sid="(?P<id>[\w\.]+)"#', $Result, $matches ) ) {
			$rendererId = $matches['id'];
			$oldId = self::curId2OldId( $pid );
			$newID = self::generateMathAnchorString( $oldId, $eid, '' );
			$Result = str_replace( $rendererId, $newID, $Result );
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

	static function generateMathAnchorString($pageID, $anchorID, $prefix = "#"){
		return "{$prefix}math.$pageID.$anchorID";
	}

	/**
	 * @param int    $oldID
	 * @param int    $eid
	 * @param string $inputHash
	 * @param string $tex
	 */
	public static function writeMathIndex( $oldID, $eid, $inputHash, $tex ) {
		wfDebugLog( "MathSearch", 'Store index for $' . $tex . '$ in database' );
		$dbw = wfGetDB( DB_MASTER );
		$dbw->onTransactionIdle( function () use ( $oldID, $eid, $inputHash, $dbw ) {
			$dbw->replace( 'mathindex', array( 'mathindex_revision_id', 'mathindex_anchor' ), array(
					'mathindex_revision_id' => $oldID,
					'mathindex_anchor' => $eid,
					'mathindex_inputhash' => $inputHash
				) );
		} );
	}
}
