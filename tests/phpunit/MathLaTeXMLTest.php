<?php

namespace MediaWiki\Extension\MathSearch\Tests;

use MediaWiki\Extension\MathSearch\MathLaTeXML;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use StatusValue;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\MathSearch\MathLaTeXML
 * @group MathSearch
 */
class MathLaTeXMLTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;

	public function testSerializeSettings() {
		$renderer = new MathLaTeXML();
		$sampleSettings = [
			'k1' => 'v1',
			'k2&=' => 'v2 + & *üö',
			'k3' => [
				'v3A', 'v3b'
			]
		];
		$expected = 'k1=v1&k2%26%3D=v2+%2B+%26+%2A%C3%BC%C3%B6&k3=v3A&k3=v3b';
		$this->assertSame( $expected, $renderer->serializeSettings( $sampleSettings ) );
		$this->assertSame( $expected, $renderer->serializeSettings( $expected ) );
	}

	public function testGetRenderer() {
		$factory = $this->getServiceContainer()->get( 'Math.RendererFactory' );
		$this->assertInstanceOf( MathLaTeXML::class,
			MathLaTeXML::getRenderer( $factory, 'a', [], 'latexml' ) );
		$this->assertNotInstanceOf( MathLaTeXML::class,
			MathLaTeXML::getRenderer( $factory, 'a', [], 'source' ) );
	}

	public function testDefaultSettings() {
		$this->overrideConfigValue( 'MathDefaultLaTeXMLSetting', [ 'format' => 'test' ] );
		$renderer = new MathLaTeXML();
		$this->assertSame( [ 'format' => 'test' ], $renderer->getLaTeXMLSettings() );
		$renderer->setLaTeXMLSettings( 'profile=mwsquery' );
		$this->assertSame( 'profile=mwsquery', $renderer->getLaTeXMLSettings() );
	}

	public function testPostData() {
		$this->overrideConfigValue( 'MathLaTeXMLUrl', 'http://example.com/convert' );
		$renderer = new MathLaTeXML( 'a+b', [ 'display' => 'block' ] );
		$renderer->setLaTeXMLSettings( 'format=xhtml' );
		$this->assertSame( 'format=xhtml&tex=%7B%5Cdisplaystyle%20a%2Bb%7D', $renderer->getPostData() );
		$renderer->setHost( 'http://example.com' );
		$this->assertSame( 'format=xhtml&tex=literal:%7B%5Cdisplaystyle%20a%2Bb%7D', $renderer->getPostData() );
	}

	public function testCache() {
		$renderer = new MathLaTeXML( 'a+b', [ 'display' => 'block' ] );
		$this->assertStringMatchesFormat( '%x', $renderer->getInputHash() );
		$renderer->setMathml( '<math><mi>x</mi></math>' );
		$renderer->writeCache();

		$renderer2 = new MathLaTeXML( 'a+b', [ 'display' => 'block' ] );
		$this->assertTrue( $renderer2->readFromCache() );
		$this->assertSame( '{\\displaystyle a+b}', $renderer2->getTex() );
		$this->assertSame( '<math><mi>x</mi></math>', $renderer2->getMathml() );
	}

	public function testMakeRequestInvalid() {
		$this->installMockHttp( $this->makeFakeHttpRequest( 'Method Not Allowed', 405 ) );
		$status = ( new MathLaTeXML( 'a' ) )->makeRequest();
		$this->assertNull( $status->getValue() );
		$this->assertStatusError( 'math_invalidresponse', $status );
	}

	public function testMakeRequestSuccess() {
		$this->installMockHttp( $this->makeFakeHttpRequest( 'test content' ) );
		$status = ( new MathLaTeXML( 'a' ) )->makeRequest();
		$this->assertStatusGood( $status );
		$this->assertSame( 'test content', $status->getValue() );
	}

	public function testMakeRequestTimeout() {
		$this->installMockHttp(
			$this->makeFakeHttpRequest( '', StatusValue::newFatal( 'http-timed-out' ) )
		);
		$status = ( new MathLaTeXML( 'a' ) )->makeRequest();
		$this->assertNull( $status->getValue() );
		$this->assertStatusError( 'math_timeout', $status );
	}

	public function testRender() {
		$this->installMockHttp( $this->makeFakeHttpRequest(
			json_encode( [ 'result' => '<math><mi>x</mi></math>' ] )
		) );
		$renderer = new MathLaTeXML( 'x' );
		$this->assertTrue( $renderer->render() );
		$this->assertSame( '<math><mi>x</mi></math>', $renderer->getMathml() );
		$this->assertSame( '', $renderer->getLastError() );
	}

	public function testRenderErrorMessage() {
		$this->installMockHttp( $this->makeFakeHttpRequest( 'Bad Gateway', 502 ) );
		$renderer = new MathLaTeXML( 'x' );
		$this->assertFalse( $renderer->render() );
		$this->assertStringContainsString( 'Bad Gateway', $renderer->getLastError() );
	}

	public function testRenderInvalidMathML() {
		$this->installMockHttp( $this->makeFakeHttpRequest(
			json_encode( [ 'result' => '<notmath/>' ] )
		) );
		$renderer = new MathLaTeXML( 'x' );
		$this->assertFalse( $renderer->render() );
		$this->assertNotSame( '', $renderer->getLastError() );
	}

	public function testEmpty() {
		$renderer = TestingAccessWrapper::newFromObject( new MathLaTeXML( '' ) );
		$this->assertStatusError( 'math_empty_tex', $renderer->doRender() );
	}

	public function testIsValidMathML() {
		$renderer = new MathLaTeXML();
		$this->assertTrue( $renderer->isValidMathML( '<math>content</math>' ) );
		$this->assertTrue( $renderer->isValidMathML( '<query>content</query>' ) );
		$this->assertFalse( $renderer->isValidMathML( '<notmath />' ) );
		$this->assertFalse( $renderer->isValidMathML( '<mat' ) );
		$renderer->setAllowedRootElements( [ 'query' ] );
		$this->assertSame( [ 'query' ], $renderer->getAllowedRootElements() );
		$this->assertFalse( $renderer->isValidMathML( '<math>content</math>' ) );
	}

	public function testGetHtmlOutputInline() {
		$math = new MathLaTeXML( 'a+b', [ 'display' => 'inline', 'id' => 'unique.id' ] );
		$math->setMathml( '<math display="block">example</math>' );
		$out = $math->getHtmlOutput();
		$this->assertStringContainsString( 'mwe-math-element-inline', $out );
		$this->assertStringContainsString( 'mwe-math-mathml-inline', $out );
		$this->assertStringContainsString( 'id="unique.id"', $out );
		$this->assertStringContainsString( '<math class="mathjax_ignore" >', $out );
		$this->assertStringNotContainsString( 'mwe-math-mathml-a11y', $out );
		$this->assertStringNotContainsString( '<img', $out );
	}

	public function testGetHtmlOutputDisplay() {
		$math = new MathLaTeXML( 'a+b', [ 'display' => 'block' ] );
		$math->setMathml( '<math>example</math>' );
		$out = $math->getHtmlOutput();
		$this->assertStringContainsString( 'mwe-math-element-block', $out );
		$this->assertStringContainsString( 'display="block"', $out );
	}

	public function testGetHtmlOutputQID() {
		$math = new MathLaTeXML( 'a+b', [ 'qid' => 'Q123' ] );
		$this->assertStringContainsString( 'data-qid="Q123"', $math->getHtmlOutput() );
		$math = new MathLaTeXML( 'a+b', [ 'qid' => '123' ] );
		$this->assertStringNotContainsString( 'data-qid', $math->getHtmlOutput() );
	}
}
