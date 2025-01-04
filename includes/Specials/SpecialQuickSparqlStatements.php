<?php

namespace MediaWiki\Extension\MathSearch\specials;

use MediaWiki\SpecialPage\SpecialPage;

class SpecialQuickSparqlStatements extends SpecialPage {
	public function __construct() {
		parent::__construct( 'QuickSparqlStatements', 'import' );
	}

	protected function getGroupName(): string {
		return 'mathsearch';
	}
}
