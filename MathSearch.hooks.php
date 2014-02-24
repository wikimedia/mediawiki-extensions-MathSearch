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
	 * @return bool
	 */
	static function onLoadExtensionSchemaUpdates( $updater = null ) {
		if ( is_null( $updater ) ) {
			throw new MWException( "Mathsearch extension requires Mediawiki 1.18 or above" );
		}
// 		$map = array(
// 			'mysql' => 'mathsearch.sql',
// 			// 'sqlite' => 'math.sql',
// 			// 'postgres' => 'math.pg.sql',
// 			// 'oracle' => 'math.oracle.sql',
// 			// 'mssql' => 'math.mssql.sql',
// 			// 'db2' => 'math.db2.sql',
// 		);
 		$type = $updater->getDB()->getType();
 		if ( $type == "mysql"  ) {
			$dir = dirname( __FILE__ ) . '/db/' ;// . $map[$type];
			$updater->addExtensionTable( 'mathindex', $dir . 'mathsearch.sql' );
			$updater->addExtensionTable( 'mathobservation',  $dir . 'mathobservation.sql' );
			$updater->addExtensionTable( 'mathvarstat', $dir . 'mathvarstat.sql' );
			$updater->addExtensionTable( 'mathpagestat', $dir . 'mathpagestat.sql' );
		    $updater->addExtensionTable( 'mathsemantics', $dir . 'mathsemantics.sql' );
 		} else {
 			//throw new MWException( "Math extension does not currently support $type database." );
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

	/**
	 * Callback function that is called after a formula was rendered
	 *
	 * @param $content
	 * @param $attributes
	 * @param $parser Parser
	 * @return boolean (true)
	 */
	static function onMathFormulaRendered( $Renderer, &$Result = null, $pid = 0, $eid = 0 ) {
		if ( $pid > 0 ) { // Only store something if a pageid was set.
			try {
			$dbr = wfGetDB( DB_SLAVE );
			$exists = $dbr->selectRow( 'mathindex',
				array( 'mathindex_page_id', 'mathindex_anchor', 'mathindex_inputhash' ),
				array(
					'mathindex_page_id' => $pid,
					'mathindex_anchor' => $eid,
					'mathindex_inputhash' => $Renderer->getInputHash() )
					) ;
			if ( $exists ) {
				wfDebugLog( "MathSearch", 'Index $' . $Renderer->getTex() . '$ already in database.' );
			} else {
				wfDebugLog( "MathSearch", 'Store index for $' . $Renderer->getTex() . '$ in database' );
				$dbw = wfGetDB( DB_MASTER );
				$inputhash = $Renderer->getInputHash();
				$dbw->onTransactionIdle(
						function () use ( $pid, $eid, $inputhash, $dbw ) {
							$dbw->replace( 'mathindex',
							array( 'mathindex_page_id', 'mathindex_anchor' ),
							array(
								'mathindex_page_id' => $pid,
								'mathindex_anchor' =>  $eid ,
								'mathindex_inputhash' => $inputhash
							) );
						}
				);
				}
			} catch ( Exception $e ) {
				wfDebugLog( "MathSearch", 'Problem writing to math index!'
					. ' You might want the rebuild the index by running:'
					. '"php extensions/MathSearch/ReRenderMath.php". The error is'
					. $e->getMessage() );
			}
		}
		$url = SpecialPage::getTitleFor( 'FormulaInfo' )->getLocalUrl( array( 'pid' => $pid, 'eid' => $eid ) );
		$Result = '<a href="' . $url . '" id="math' . $eid . '" style="color:inherit;">' . $Result . '</a>';
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
}
