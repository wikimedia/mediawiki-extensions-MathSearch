<?php
namespace MediaWiki\Extension\MathSearch\Engine;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

class MathIndex {
	private IDatabase $dbw;

	public function __construct( ?IDatabase $dbw = null ) {
		$this->dbw = $dbw ?? MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getPrimaryDatabase();
	}

	public function delete( int $revid ): bool {
		$this->dbw->delete( 'mathindex', [ 'mathindex_revision_id' => $revid ] );
		return true;
	}

	public function update( array $new = [], array $old = [] ): bool {
		$this->dbw->delete( 'mathindex', [ 'mathindex_inputhash' => $old ] );
		$this->dbw->insert( 'mathindex', $new );
		return true;
	}

}
