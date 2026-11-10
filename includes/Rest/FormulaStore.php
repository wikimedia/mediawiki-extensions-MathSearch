<?php

namespace MediaWiki\Extension\MathSearch\Rest;

use MathObject;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;

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

	/**
	 * @param array{q:string,type:string} $formula
	 * @return array<string,mixed> the mathlog key of its native rendering
	 */
	private static function logKey( array $formula ): array {
		return [
			// As MathObject::hashInput() for a formula without attributes
			'math_inputhash' => md5( FormulaHash::preimage( $formula['q'], $formula['type'] ), true ),
			'math_mode' => MathObject::MODE_2_USER_OPTION['native'],
		];
	}

	/**
	 * The SVG stored for exactly this MathML, so a rendering from older code is never served.
	 *
	 * @param array{q:string,type:string} $formula
	 * @param string $mathml
	 * @return string|null
	 */
	public function getSvg( array $formula, string $mathml ): ?string {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$svg = $dbr->newSelectQueryBuilder()
			->select( 'math_svg' )
			->from( 'mathlog' )
			->where( self::logKey( $formula ) )
			->andWhere( [ 'math_mathml' => $mathml, $dbr->expr( 'math_svg', '!=', '' ) ] )
			->orderBy( 'math_timestamp', SelectQueryBuilder::SORT_DESC )
			->caller( __METHOD__ )
			->fetchField();
		return is_string( $svg ) ? $svg : null;
	}

	/** @param array{q:string,type:string} $formula */
	public function saveSvg( array $formula, string $mathml, string $svg ): void {
		$this->connectionProvider->getPrimaryDatabase()->newInsertQueryBuilder()
			->insertInto( 'mathlog' )
			->row( self::logKey( $formula ) + [
				'math_input' => $formula['q'],
				'math_mathml' => $mathml,
				'math_svg' => $svg,
			] )
			->caller( __METHOD__ )
			->execute();
	}
}
