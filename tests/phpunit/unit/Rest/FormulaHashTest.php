<?php

namespace MediaWiki\Extension\MathSearch\Tests\Rest;

use MediaWiki\Extension\MathSearch\Rest\FormulaHash;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MathSearch\Rest\FormulaHash
 */
class FormulaHashTest extends MediaWikiUnitTestCase {

	/**
	 * The value the live RESTBase API returns in x-resource-location for
	 * POST /media/math/check/tex with q=E=mc^{2}. If this ever fails, hashes
	 * minted here have stopped matching production.
	 */
	public function testMatchesRestbaseForTheDocumentedExample() {
		$this->assertSame(
			'4c0004393a88f350a93bcef62106d556c7fc827b',
			FormulaHash::fromTex( 'E=mc^{2}', 'tex' )
		);
	}

	public function testTypeIsPartOfTheAddress() {
		$this->assertNotSame(
			FormulaHash::fromTex( 'E=mc^{2}', 'tex' ),
			FormulaHash::fromTex( 'E=mc^{2}', 'inline-tex' )
		);
	}

	/** Slashes and non-ASCII must not be escaped, or the bytes hashed differ. */
	public function testEncodingMatchesStableStringify() {
		$this->assertSame(
			sha1( '{"q":"a/b","type":"tex"}' ),
			FormulaHash::fromTex( 'a/b', 'tex' )
		);
		$this->assertSame(
			sha1( '{"q":"ä","type":"tex"}' ),
			FormulaHash::fromTex( "\u{00E4}", 'tex' )
		);
	}

	/** The stored row is the preimage, so it has to hash back to its own key. */
	public function testPreimageHashesToTheAddress() {
		foreach ( [ 'E=mc^{2}', 'a/b', "\u{00E4}" ] as $q ) {
			$this->assertSame(
				FormulaHash::fromTex( $q ),
				sha1( FormulaHash::preimage( $q ) )
			);
		}
		$this->assertSame( '{"q":"E=mc^{2}","type":"tex"}', FormulaHash::preimage( 'E=mc^{2}' ) );
	}

	public function testRecognisesOnlyWellFormedAddresses() {
		$this->assertTrue( FormulaHash::isRestbase( FormulaHash::fromTex( 'x' ) ) );
		// RESTBase never issues anything else, so nothing else resolves.
		$this->assertFalse( FormulaHash::isRestbase( md5( 'x' ) ) );
		$this->assertFalse( FormulaHash::isRestbase( 'not a hash' ) );
		$this->assertFalse( FormulaHash::isRestbase( strtoupper( FormulaHash::fromTex( 'x' ) ) ) );
	}
}
