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
//		if( is_null( $updater ) ) {
//			throw new MWException( "Math extension is only necessary in 1.18 or above" );
//		}
		$map = array(
			'mysql' => 'mathsearch.sql',
			//'sqlite' => 'math.sql',
			//'postgres' => 'math.pg.sql',
			//'oracle' => 'math.oracle.sql',
			//'mssql' => 'math.mssql.sql',
			//'db2' => 'math.db2.sql',
		);
		$type = $updater->getDB()->getType();
		if ( isset( $map[$type] ) ) {
			$sql = dirname( __FILE__ ) . '/db/' . $map[$type];
			$updater->addExtensionTable( 'mathindex', $sql );
			//$sqlindex = dirname( __FILE__ ) . '/db/' . $map[$type].'.index';
		} else {
			throw new MWException( "Math extension does not currently support $type database." );
		}
		return true;
	}
	
		/**
	 * Callback function that is called after a formula was rendered
	 *
	 * @param $content
	 * @param $attributes
	 * @param $parser Parser
	 * @return
	 */
	static function onMathFormulaRendered( $Renderer ) {
		$dbw = wfGetDB( DB_MASTER );
		wfDebugLog("MathSearch",'Store index for $'.$Renderer->tex.'$ in database');
		$inputhash = $dbw->encodeBlob( $Renderer->getInputHash() );
		$dbw->replace('mathindex',
		array( 'pageid','anchor', ),
		array(
				'pageid' => $Renderer->pageID,
				'anchor' =>  $Renderer->anchor ,
				'inputhash' => $inputhash
				));
			wfDebugLog("Math","inputhash=$inputhash (".md5($Renderer->tex).")");
		return true;

}
}