<?php
/**
 * Test the db2 access of  MathSearch.
*
* @group MathSearch
* @group Database
*/
class MathD2ConnectionTest extends MediaWikiTestCase {
	public function testConnect() {
		global $wgMathSearchDB2ConnStr;
		if ( ! MathSearchHooks::isDB2Supported() ) {
			$this->markTestSkipped( 'DB2 php client is not installed.' );
		}
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
	public  function testBasicXQuery(){
		global $wgMathSearchDB2ConnStr;
		if ( ! MathSearchHooks::isDB2Supported() || true ) {
			$this->markTestSkipped( 'DB2 php client is not installed.' );
		}
		$conn = db2_connect($wgMathSearchDB2ConnStr, '', '');
		$stmt = db2_exec($conn,'xquery declare default element namespace "http://www.w3.org/1998/Math/MathML";
 for $m in db2-fn:xmlcolumn("math.math_mathml") return
for $x in $m//*:\'apply\'[*[1]/name() = \'eq\'] return
data($m/*[1]/@alttext)');
		while($row = db2_fetch_row($stmt)){
			echo db2_result($stmt,0);

		}
	}
}