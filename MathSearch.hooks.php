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
	static function onMathFormulaRendered( $Renderer, &$Result=null) {
		$dbw = wfGetDB( DB_MASTER );
		wfDebugLog( "MathSearch", 'Store index for $' . $Renderer->getTex() . '$ in database' );
		$inputhash = $dbw->encodeBlob( $Renderer->getInputHash() );
		$dbw->replace( 'mathindex',
		array( 'mathindex_pageid', 'anchor', ),
		array(
				'mathindex_page_id' => $Renderer->getPageID(),
				'mathindex_anchor' =>  $Renderer->getAnchorID() ,
				'mathindex_inputhash' => $inputhash
				) );
		$Result='<a href="/index.php/Special:FormulaInfo?pid=' .$Renderer->getPageID().'&eid='.$Renderer->getAnchorID().'">'.$Result.'</a>';
		return true;
	}


}