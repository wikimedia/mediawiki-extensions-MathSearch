<?php

class LaTeX2MathematicaTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var LaTeXTranslator
	 */
	private $translator;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp(): void {
		$this->translator = new LaTeXTranslator();
		parent::setUp();
	}

	/**
	 * Loops over all test cases provided by the provider function.
	 * Compares each the rendering result of each input with the expected output.
	 * @dataProvider provideCoverage
	 * @coversNothing
	 */
	public function testCoverage( $input, $output ) {
		$this->assertEquals(
			$this->normalize( $output ),
			$this->normalize( $this->translator->processInput( $input ) ),
			"Failed to render $input"
		);
	}

	/**
	 * Gets the test-data from the json file
	 * @return array [ $input $output ] where $input is the input LaTeX string and $output is the
	 * rendered Mathematica string
	 */
	public static function provideCoverage() {
		return json_decode( file_get_contents( __DIR__ . '/tex2nb.json' ) );
	}

	private function normalize( $input ) {
		return $input;
	}
}
