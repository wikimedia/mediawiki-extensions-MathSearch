<?php
/**
 * Test the db2 access of  MathSearch.
 *
 * @group MathSearch
 * @group Database
 */
class BaseXSimpleTest extends MediaWikiTestCase {
	private $phpErrorLevel = -1;

	protected function setUp(){
		global $wgMathSearchBaseXSupport;
		if ( ! $wgMathSearchBaseXSupport ) {
			$this->markTestSkipped( 'This server does not support baseX.' );
		}
		$this->phpErrorLevel = intval( ini_get( 'error_reporting' ) );
		parent::setUp();
	}

	public function  testCreateDB(){
		$session = new BaseXSession();
		$this->assertType("null", $session->info() ,"");
		$session->execute("create db MathSearchUnitTest <root><user>
  <username>Username1</username><password>Password1</password>
  </user><user><username>Username2</username><password>Password2</password></user></root>");
		$this->assertContains('created in',$session->info());
		$session->close();
	}

	public function  testReadFromDB(){
		$session = new BaseXSession();
		$session->execute("open MathSearchUnitTest");
		$this->assertContains('opened in',$session->info());
		$res = $session->execute("xquery .");
		$this->assertContains('Username2',$res);
		$session->close();
	}
}