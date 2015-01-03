<?php
/**
 * MediaWiki MathSearch extension
 *
 */

class MathEngineTest extends MediaWikiTestCase {



	private $stmt= <<<'EOT'
xquery declare default element namespace "http://www.w3.org/1998/Math/MathML";
 for $m in db2-fn:xmlcolumn("math.math_mathml") return
for $x in $m//*:apply
[*[1]/name() ='eq' and *[2]/name() ='ci' and *[2][./text() = 'E'] and *[3]/name() ='apply' and *[3][*[1]/name() ='times' and *[2]/name() ='ci' and *[2][./text() = 'm'] and *[3]/name() ='apply' and *[3][*[1]/name() ='csymbol' and *[1][./text() = 'superscript'] and *[2]/name() ='ci' and *[2][./text() = 'c'] and *[3]/name() ='cn' and *[3][./text() = '2']]]]
 where
fn:count($x/*[2]/*) = 0
 and fn:count($x/*[3]/*[2]/*) = 0
 and fn:count($x/*[3]/*[3]/*[1]/*) = 0
 and fn:count($x/*[3]/*[3]/*[2]/*) = 0
 and fn:count($x/*[3]/*[3]/*[3]/*) = 0
 and fn:count($x/*[3]/*[3]/*) = 3
 and fn:count($x/*[3]/*) = 3
 and fn:count($x/*) = 3

 return
data($m/*[1]/@alttext)
EOT;

	private $stmt2= <<<'EOT'
<?xml version="1.0"?>
    <mws:query xmlns:mws="http://search.mathweb.org/ns" xmlns:m="http://www.w3.org/1998/Math/MathML" limitmin="0" answsize="30">
        <mws:expr>
            <m:apply xml:id="p1.1.m1.1.6.cmml" xref="p1.1.m1.1.6">
                <m:eq xml:id="p1.1.m1.1.2.cmml" xref="p1.1.m1.1.2"/>
                <m:ci xml:id="p1.1.m1.1.1.cmml" xref="p1.1.m1.1.1">E</m:ci>
                <m:apply xml:id="p1.1.m1.1.6.1.cmml" xref="p1.1.m1.1.6.1">
                    <m:times xml:id="p1.1.m1.1.6.1.1.cmml" xref="p1.1.m1.1.6.1.1"/>
                    <m:ci xml:id="p1.1.m1.1.3.cmml" xref="p1.1.m1.1.3">m</m:ci>
                    <m:apply xml:id="p1.1.m1.1.6.1.2.cmml" xref="p1.1.m1.1.6.1.2">
                        <m:csymbol cd="ambiguous" xml:id="p1.1.m1.1.6.1.2.1.cmml">superscript</m:csymbol>
                        <m:ci xml:id="p1.1.m1.1.4.cmml" xref="p1.1.m1.1.4">c</m:ci>
                        <m:cn type="integer" xml:id="p1.1.m1.1.5.1.cmml" xref="p1.1.m1.1.5.1">2</m:cn>
                    </m:apply>
                </m:apply>
            </m:apply>
        </mws:expr>
    </mws:query>
EOT;


	private $sel = "SELECT
        `mathindex`.`mathindex_revision_id` AS `mathindex_revision_id`,
        `mathindex`.`mathindex_anchor` AS `mathindex_anchor`,
        `mathindex`.`mathindex_inputhash` AS `mathindex_inputhash`,
        `mathindex`.`mathindex_timestamp` AS `mathindex_timestamp`,
        `math`.`math_inputhash` AS `math_inputhash`,
        `math`.`math_mathml` AS `math_mathml`,
        `math`.`math_outputhash` AS `math_outputhash`,
        `math`.`math_html_conservativeness` AS `math_html_conservativeness`,
        `math`.`math_html` AS `math_html`
        FROM (`mathindex` JOIN `math` ON((`mathindex`.`mathindex_revision_id` = `mathindex`.`mathindex_anchor`)))";





	protected function setUp(){
		if ( ! MathSearchHooks::isDB2Supported() ) {
			$this->markTestSkipped( 'DB2 php client is not installed.' );
		}
		parent::setUp();
		$this->getTestResultObject()->setTimeoutForLargeTests(60);
	}

	/**
	 *
	 */
	public function testDirectDB2Query(){
		global $wgMathSearchDB2ConnStr;
		$conn = db2_connect($wgMathSearchDB2ConnStr, '', '');

		$stmt = db2_exec($conn, $this->stmt );
		$this->assertEquals(1, db2_num_fields ( $stmt ) );
		db2_close( $conn );
	}

	public function testHash(){
		$test_tex = 'E=mc^2';
		$test_hash = '826676a6a5ad24552f0d5af1593434cc';
		$renderer = new MathLaTeXML($test_tex);
		$realHash = $renderer->getMd5();
		$this->assertEquals($realHash,$test_hash,'wrong hash');
	}
	public function testGetAllOcc(){
		global $wgMathSearchDB2ConnStr;
		$conn = db2_connect($wgMathSearchDB2ConnStr, '', '');
		$stmt = db2_exec($conn, $this->stmt );
		$hash = '826676a6a5ad24552f0d5af1593434cc';

		$moArray=array();
		while($row = db2_fetch_row( $stmt ) ){
			$tex =   db2_result( $stmt, 0 );
			$tex = str_replace( '<?xml version="1.0" encoding="UTF-8" ?>' , '' , $tex );
			$this->assertEquals($tex, '{\displaystyle E=mc^{2}}');



			$mo = MathObject::newFromMd5($hash);

			$rFD = $mo->readFromDatabase();
			$this->assertTrue( $rFD, 'readFromDatabase() was not successful');


			//create the MathObject out of the tex
			/*
				$mo = new MathObject($tex);
				$test= $mo->readFromDatabase();
				$this->assertEquals($test,true,'readFromDatabase() was not successful');
				*/



			$all  = $mo->getAllOccurences( false );
			$this->assertType( 'array', $all, 'getAllOccurences() return false');

			array_push($moArray, $all);

		}
		//Fixme: Test the content of $moArray
		//$this->assertEquals('content', $moArray[0]);
	}


	/**
	 * Function to test the class MathEngineDB2
	 * @Large
	 */
	public function testMathEngineDB2(){
		$query = new XQueryGeneratorDB2($this->stmt2);
		$this->assertEquals($this->stmt, $query->getXQuery() , "XQuery expression did not match");
		$qo = new MathQueryObject();
		$qo->setXQueryGenerator($query);
		$eng = new MathEngineDB2($qo);
		//$this->assertEquals($query,$eng->getQuery()->getXQuery());
		$this->assertEquals($qo, $eng->getQuery() , "XQuery expression did not match");
		//$eng->postQuery();


		/*
		 * Fixme: If everything is fine until here try this:
		 *
				$map = $eng->getRelevanceMap();

				$this->assertTrue($map[1]);
				$this->asserFalse($map[2]);
		*/

	}



	/**
	 * Function to test the join of math and mathindex from MySQL
	 * @TODO: I think we don't need that test.
	 * @markTestSkipped
	 */
	public function testJoin(){
		$this->markTestSkipped('Skipping test');

		$con = mysqli_connect('localhost','root','vagrant','wiki', 3306);

		// Check connection
		if (mysqli_connect_errno()) {
			echo "Failed to connect to MySQL:". mysqli_connect_error();
		}

		$result = mysqli_query($con, $this->sel);

		// Check result from query
		if($result == false){
			echo "Failed to execute query!";
		}

		while($row = mysqli_fetch_array($result)){
			$this->assertEquals($row['mathindex_revision_id'], 1);
			//echo $row['mathindex_revision_id'] . " " . $row['mathindex_anchor'] . " " . $row['math_html_conservativeness']. "\n";
		}

		mysqli_close($con);


	}








}