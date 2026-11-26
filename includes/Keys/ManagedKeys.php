<?php

namespace MediaWiki\Extension\MathSearch\Keys;

use MediaWiki\User\UserIdentity;
use stdClass;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Keys that the wiki creates and holds for users who do not manage keys themselves.
 * A user has at most one. A passphrase keeps it unusable without the user; an empty
 * passphrase protects it no better than the database.
 */
class ManagedKeys {

	private const string TABLE = 'math_managed_key';

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly KeyStore $keyStore
	) {
	}

	public function getKey( UserIdentity $user ): ?Ed25519Key {
		$row = $this->fetch( $user );
		return $row ? Ed25519Key::fromBytes( $row->mmk_key ) : null;
	}

	/**
	 * Creates a new key and drops the private key of the old one. The old public key
	 * stays registered, so that what it signed can still be checked.
	 */
	public function create( UserIdentity $user, string $passphrase ): SigningKey {
		$secretKey = sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() );
		$signingKey = new SigningKey( $secretKey );
		$key = $signingKey->getKey();
		$this->keyStore->add( $user, $key );
		$row = [ 'mmk_key' => $key->getBytes() ] + $this->protect( $secretKey, $passphrase );
		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$old = $this->fetch( $user );
		if ( $old ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( self::TABLE )
				->where( [ 'mmk_key' => $old->mmk_key ] )
				->caller( __METHOD__ )
				->execute();
		}
		$dbw->newInsertQueryBuilder()
			->insertInto( self::TABLE )
			->row( $row )
			->caller( __METHOD__ )
			->execute();
		return $signingKey;
	}

	/** Null if the user has no managed key or the passphrase does not match. */
	public function unlock( UserIdentity $user, string $passphrase ): ?SigningKey {
		$row = $this->fetch( $user );
		if ( !$row ) {
			return null;
		}
		$secretKey = sodium_crypto_secretbox_open( $row->mmk_secret, $row->mmk_nonce,
			$this->derive( $passphrase, $row->mmk_salt, (int)$row->mmk_opslimit, (int)$row->mmk_memlimit ) );
		return $secretKey === false ? null : new SigningKey( $secretKey );
	}

	private function protect( string $secretKey, string $passphrase ): array {
		$salt = random_bytes( SODIUM_CRYPTO_PWHASH_SALTBYTES );
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$opsLimit = SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
		$memLimit = SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;
		return [
			'mmk_secret' => sodium_crypto_secretbox( $secretKey, $nonce,
				$this->derive( $passphrase, $salt, $opsLimit, $memLimit ) ),
			'mmk_salt' => $salt,
			'mmk_nonce' => $nonce,
			'mmk_opslimit' => $opsLimit,
			'mmk_memlimit' => $memLimit,
		];
	}

	private function derive( string $passphrase, string $salt, int $opsLimit, int $memLimit ): string {
		// The prefix lets libsodium accept an empty passphrase.
		return sodium_crypto_pwhash( SODIUM_CRYPTO_SECRETBOX_KEYBYTES, "mathsearch-managed-key:$passphrase", $salt,
			$opsLimit, $memLimit, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13 );
	}

	private function fetch( UserIdentity $user ): ?stdClass {
		return $this->connectionProvider->getPrimaryDatabase()->newSelectQueryBuilder()
			->select( [ 'mmk_key', 'mmk_secret', 'mmk_salt', 'mmk_nonce', 'mmk_opslimit', 'mmk_memlimit' ] )
			->from( self::TABLE )
			->join( 'math_key', null, 'mk_key = mmk_key' )
			->where( [ 'mk_user' => $user->getId() ] )
			->caller( __METHOD__ )
			->fetchRow() ?: null;
	}
}
