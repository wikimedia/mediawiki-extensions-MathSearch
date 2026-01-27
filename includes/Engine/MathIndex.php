<?php
namespace MediaWiki\Extension\MathSearch\Engine;

use Wikimedia\Rdbms\IDatabase;

class MathIndex {
	public function __construct(
		private readonly IDatabase $dbw,
	) {
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
