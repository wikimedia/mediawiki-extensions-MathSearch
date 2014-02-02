<?php

require_once( dirname(__FILE__) . '/../../../maintenance/Maintenance.php' );

class db2Test extends Maintenance {

	public function execute() {

		global $wgMathSearchDB2ConnStr;
		if ($wgMathSearchDB2ConnStr !== false) {
			$conn = db2_connect($wgMathSearchDB2ConnStr, '', '');

			if ($conn) {
				echo "Connection succeeded.";
				db2_close($conn);
			} else {
				echo "Connection failed.";
			}
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

}

$maintClass = "db2Test";
require_once( RUN_MAINTENANCE_IF_MAIN );
