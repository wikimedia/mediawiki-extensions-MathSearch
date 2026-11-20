<?php

namespace MediaWiki\Extension\MathSearch\Keys;

use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Which public keys belong to which wiki user, and whether a key is trusted.
 */
class KeyStore {

	private const string TABLE = 'math_key';

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly PermissionManager $permissionManager
	) {
	}

	/** Returns false if the key belongs to another user. */
	public function add( UserIdentity $user, Ed25519Key $key ): bool {
		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$dbw->newInsertQueryBuilder()
			->insertInto( self::TABLE )
			->ignore()
			->row( [ 'mk_key' => $key->getBytes(), 'mk_user' => $user->getId(), 'mk_timestamp' => $dbw->timestamp() ] )
			->caller( __METHOD__ )
			->execute();
		$owner = $dbw->newSelectQueryBuilder()
			->select( 'mk_user' )
			->from( self::TABLE )
			->where( [ 'mk_key' => $key->getBytes() ] )
			->caller( __METHOD__ )
			->fetchField();
		return (int)$owner === $user->getId();
	}

	/** A key is trusted while its owner has the right mathtrustedkeys. */
	public function isTrusted( Ed25519Key $key ): bool {
		$userId = $this->connectionProvider->getReplicaDatabase()->newSelectQueryBuilder()
			->select( 'mk_user' )
			->from( self::TABLE )
			->where( [ 'mk_key' => $key->getBytes() ] )
			->caller( __METHOD__ )
			->fetchField();
		if ( !$userId ) {
			return false;
		}
		$user = $this->userIdentityLookup->getUserIdentityByUserId( (int)$userId );
		return $user !== null && $this->permissionManager->userHasRight( $user, 'mathtrustedkeys' );
	}
}
