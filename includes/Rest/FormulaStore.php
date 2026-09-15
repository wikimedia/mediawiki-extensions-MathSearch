<?php

namespace MediaWiki\Extension\MathSearch\Rest;

use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Resolve a content address back to the input it stands for.
 *
 * An address is a sha1 and cannot be inverted, so POST check records the
 * mapping. The row holds the hashed bytes themselves, which keeps the encoding
 * rule in FormulaHash and lets a row be checked against its own key.
 */
class FormulaStore {

	private const string TABLE = 'math_rest_input';

	private IConnectionProvider $connectionProvider;

	public function __construct( IConnectionProvider $connectionProvider ) {
		$this->connectionProvider = $connectionProvider;
	}

	/** Record $q so it resolves by its address, and return that address. */
	public function remember( string $q, string $type = 'tex' ): string {
		$preimage = FormulaHash::preimage( $q, $type );
		$hash = sha1( $preimage );
		$this->connectionProvider->getPrimaryDatabase()->newInsertQueryBuilder()
			->insertInto( self::TABLE )
			->ignore()
			->row( [
				'math_rest_hash' => $hash,
				'math_rest_input' => $preimage,
			] )
			->caller( __METHOD__ )
			->execute();
		return $hash;
	}

	/**
	 * @param string $hash
	 * @return array{q:string,type:string}|null
	 */
	public function resolve( string $hash ): ?array {
		if ( !FormulaHash::isRestbase( $hash ) ) {
			return null;
		}

		$row = $this->connectionProvider->getReplicaDatabase()->newSelectQueryBuilder()
			->select( 'math_rest_input' )
			->from( self::TABLE )
			->where( [ 'math_rest_hash' => $hash ] )
			->caller( __METHOD__ )
			->fetchField();
		if ( $row === false || $row === null ) {
			return null;
		}

		$formula = json_decode( (string)$row, true );
		if ( !is_array( $formula ) || !isset( $formula['q'] ) || !isset( $formula['type'] ) ) {
			return null;
		}
		return $formula;
	}
}
