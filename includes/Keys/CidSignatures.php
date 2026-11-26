<?php

namespace MediaWiki\Extension\MathSearch\Keys;

use MediaWiki\Extension\MathSearch\Ipfs\Cid;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Which keys signed a CID, for D-LOCKSS to record in the manifest.
 */
class CidSignatures {

	private const string TABLE = 'math_cid_signature';

	public function __construct( private readonly IConnectionProvider $connectionProvider ) {
	}

	public static function message( Cid $cid ): string {
		return "dlockss-upload $cid";
	}

	public function add( Cid $cid, Ed25519Key $key, string $signature ): void {
		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$dbw->newInsertQueryBuilder()
			->insertInto( self::TABLE )
			->ignore()
			->row( [
				'mcs_cid' => $cid->getBytes(),
				'mcs_key' => $key->getBytes(),
				'mcs_signature' => $signature,
				'mcs_timestamp' => $dbw->timestamp(),
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/** @return array<array{key:string,signature:string}> */
	public function get( Cid $cid ): array {
		$rows = $this->connectionProvider->getReplicaDatabase()->newSelectQueryBuilder()
			->select( [ 'mcs_key', 'mcs_signature' ] )
			->from( self::TABLE )
			->where( [ 'mcs_cid' => $cid->getBytes() ] )
			->orderBy( 'mcs_timestamp' )
			->caller( __METHOD__ )
			->fetchResultSet();
		$signatures = [];
		foreach ( $rows as $row ) {
			$signatures[] = [
				'key' => (string)Ed25519Key::fromBytes( $row->mcs_key ),
				'signature' => Ed25519Key::encodeSignature( $row->mcs_signature ),
			];
		}
		return $signatures;
	}
}
