<?php
/**
 * MediaWiki MathSearch extension
 *
 * (c) 2026 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @file
 */

namespace MediaWiki\Extension\MathSearch;

use MathIdGenerator;
use MathObject;
use MediaWiki\Extension\Math\MathSource;
use MediaWiki\Revision\RevisionRecord;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Page views write nothing, so the index does not depend on what a page renders.
 */
class RevisionIndexer {

	/** @var array<string,true> inputs stored by this instance */
	private array $stored = [];

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	/**
	 * @param MathIdGenerator $idGenerator
	 * @param bool $storeInputs without rendering them
	 */
	public function index( MathIdGenerator $idGenerator, bool $storeInputs = false ): void {
		$index = [];
		$inputs = [];
		foreach ( $idGenerator->getMathTags() as $key => $tag ) {
			[ $tex, $attributes ] = $idGenerator->getRendererInput( $tag );
			$hash = MathObject::hashInput( $tex, $attributes );
			$index[] = [
				'mathindex_revision_id' => $idGenerator->getRevisionId(),
				'mathindex_anchor' => $idGenerator->getAnchor( $key ),
				'mathindex_inputhash' => $hash,
			];
			if ( $storeInputs && !isset( $this->stored[$hash] ) ) {
				$this->stored[$hash] = true;
				$inputs[$hash] = MathObject::cloneFromRenderer( new MathSource( $tex, $attributes ), $hash );
			}
		}
		if ( !$index ) {
			return;
		}
		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$dbw->newReplaceQueryBuilder()
			->replaceInto( 'mathindex' )
			->uniqueIndexFields( [ 'mathindex_revision_id', 'mathindex_anchor' ] )
			->rows( $index )
			->caller( __METHOD__ )
			->execute();
		if ( !$inputs ) {
			return;
		}
		$known = $dbw->newSelectQueryBuilder()
			->select( 'math_inputhash' )
			->distinct()
			->from( 'mathlog' )
			->where( [ 'math_inputhash' => array_keys( $inputs ) ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
		$rows = array_map( static fn ( MathObject $mo ) => $mo->dbOutArray(),
			array_values( array_diff_key( $inputs, array_flip( $known ) ) ) );
		if ( $rows ) {
			$dbw->newInsertQueryBuilder()
				->insertInto( 'mathlog' )
				->rows( $rows )
				->caller( __METHOD__ )
				->execute();
		}
	}

	public function indexRevision( RevisionRecord $revisionRecord ): void {
		$this->index( MathIdGenerator::newFromRevisionRecord( $revisionRecord ), true );
	}
}
