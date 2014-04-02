<?php

/**
 * Test the db2 access of  MathSearch.
 *
 * @group MathSearch
 * @group Database
 */
class MathD2ConnectionTest extends MediaWikiTestCase {
	private $phpErrorLevel = -1;
	protected function setUp(){
		if ( ! MathSearchHooks::isDB2Supported() ) {
			$this->markTestSkipped( 'DB2 php client is not installed.' );
		}
		$this->phpErrorLevel = intval( ini_get( 'error_reporting' ) );
		parent::setUp();
	}
	protected function tearDown(){
		ini_set( 'error_reporting', $this->phpErrorLevel );
	}
	public function testConnect() {
		global $wgMathSearchDB2ConnStr;
		if ( $wgMathSearchDB2ConnStr !== false ) {

			$conn = db2_connect( $wgMathSearchDB2ConnStr, '', '' );
			$this->assertInternalType( 'resource', $conn, 'Connection failed.' );
			db2_close( $conn );
		} else {
			$message = <<<'EOT'
Add something like that to LocalSettings.php
$database = 'SAMPLE';
$user = 'db2inst1';
$password = 'ibmdb2';
$hostname = 'localhost';
$port = 50000;
$wgMathSearchDB2ConnStr = "DRIVER={IBM DB2 ODBC DRIVER};DATABASE=$database;" .
  "HOSTNAME=$hostname;PORT=$port;PROTOCOL=TCPIP;UID=$user;PWD=$password;";
EOT;
			echo $message;
		}
	}

	public function testBasicSQL(){
		global $wgMathSearchDB2ConnStr;
		$conn = db2_connect($wgMathSearchDB2ConnStr, '', '');
		$stmt = db2_exec($conn,'select "math_tex" from "math"');
		$this->assertInternalType( 'resource', $stmt, 'Connection failed.' );
		/*while($row = db2_fetch_object($stmt)){
			printf("$row->math_tex\n");
		}*/
	}
	private $testquery = <<<'EOT'
xquery declare default element namespace "http://www.w3.org/1998/Math/MathML";
 for $m in db2-fn:xmlcolumn("math.math_mathml") return
for $x in $m//*:ci
[./text() = 'E']
 where
fn:count($x/*) = 0

 return
data($m/*[1]/@alttext)
EOT;
	public  function testBasicXQuery(){
		global $wgMathSearchDB2ConnStr;
		if ( ! MathSearchHooks::isDB2Supported() ) {
			$this->markTestSkipped( 'DB2 php client is not installed.' );
		}
		$conn = db2_connect($wgMathSearchDB2ConnStr, '', '');
		$stmt = db2_exec($conn,$this->testquery);
		$row = db2_fetch_row($stmt);
		$this->assertEquals('<?xml version="1.0" encoding="UTF-8" ?>{\displaystyle E=mc^{2}}', db2_result($stmt,0));

	}
}