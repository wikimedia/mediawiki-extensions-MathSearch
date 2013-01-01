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
		if( is_null( $updater ) ) {
			throw new MWException( "Mathsearch extension requires Mediawiki 1.18 or above" );
		}
		$map = array(
			'mysql' => 'mathsearch.sql',
			// 'sqlite' => 'math.sql',
			// 'postgres' => 'math.pg.sql',
			// 'oracle' => 'math.oracle.sql',
			// 'mssql' => 'math.mssql.sql',
			// 'db2' => 'math.db2.sql',
		);
		$type = $updater->getDB()->getType();
		if ( isset( $map[$type] ) ) {
			$sql = dirname( __FILE__ ) . '/db/' . $map[$type];
			$updater->addExtensionTable( 'mathindex', $sql );
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
	 * @return boolean (true)
	 */
	static function onMathFormulaRendered( $Renderer, &$Result=null,$pid=0,$eid=0) {
		$dbw = wfGetDB( DB_MASTER );
		wfDebugLog( "MathSearch", 'Store index for $' . $Renderer->getTex() . '$ in database' );
		$inputhash = $dbw->encodeBlob( $Renderer->getInputHash() );
		try{
		$dbw->replace( 'mathindex',
		array( 'mathindex_pageid', 'anchor', ),
		array(
				'mathindex_page_id' => $pid,
				'mathindex_anchor' =>  $eid ,
				'mathindex_inputhash' => $inputhash
				) );
		} catch (Exception $e){
			wfDebugLog( "MathSearch", 'Problem writing to math index!' 
				.' You might want the rebuild the index by running:'
				.'"php extensions/MathSearch/ReRenderMath.php". The error is'
				.$e->getMessage());
		}
		$Result='<a href="/index.php/Special:FormulaInfo?pid=' .$pid.'&eid='.$eid.'" id="math'.$eid.'">'.$Result.'</a>';
		return true;
	}


}