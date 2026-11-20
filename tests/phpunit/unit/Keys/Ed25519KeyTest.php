<?php

namespace MediaWiki\Extension\MathSearch\Tests\Unit\Keys;

use MediaWiki\Extension\MathSearch\Keys\Ed25519Key;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MathSearch\Keys\Ed25519Key
 */
class Ed25519KeyTest extends MediaWikiUnitTestCase {

	/** Made with `ipfs key gen --type=ed25519` and `ipfs key sign --ipns-base=b58mh` of Kubo 0.43.1. */
	private const string KEY = '12D3KooWAWPGcVHdKsSJdVUED6fGKzuSejhbChXKQZrT4ZCmrkUa';
	private const string MESSAGE = 'mathsearch-key-registration portal.mardi4nfdi.de User13 1800000000';
	private const string SIGNATURE =
		'u03sStdFNV-larroseRRfGV5YNexqTMCaD5Tubh2TfZ6BsOHCvPyE33eGPw_fMPVNSgCZ76omD--y01K8XEXPCg';

	public function testAcceptsKuboSignature() {
		$this->assertTrue( Ed25519Key::fromString( self::KEY )->verify( self::MESSAGE, self::SIGNATURE ) );
	}

	public function testDecodesTheRawKey() {
		$this->assertSame( 32, strlen( Ed25519Key::fromString( self::KEY )->getBytes() ) );
	}

	public function testRejectsOtherMessageOrKey() {
		$this->assertFalse( Ed25519Key::fromString( self::KEY )->verify( self::MESSAGE . 'x', self::SIGNATURE ) );
		$other = Ed25519Key::fromString( '12D3KooWJsXtSD4VxENSTkRo3XFqmV7fzimqcMd47rw5ngxYN7qW' );
		$this->assertFalse( $other->verify( self::MESSAGE, self::SIGNATURE ) );
		$this->assertFalse( Ed25519Key::fromString( self::KEY )->verify( self::MESSAGE, 'u' ) );
	}

	public function testRejectsOtherFormats() {
		$this->assertNull( Ed25519Key::fromString( '12D3KooWEXAMPLE' ) );
		$this->assertNull( Ed25519Key::fromString( 'k51qzi5uqu5djjavoa8anoesk9z2vogb3bo2o42s4lc46kw6j6v4ozira3enjh' ) );
		$this->assertNull( Ed25519Key::fromString( substr( self::KEY, 0, -1 ) ) );
	}
}
