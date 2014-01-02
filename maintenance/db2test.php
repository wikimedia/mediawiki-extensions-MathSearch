<?php
echo "basic test if db2 is setup correctly and the sample database is installed! \n";
echo "enter db2 (db2inst1)-password\n";
$f = fopen( 'php://stdin', 'r' );
$passwd = fgets( $f ) ;
try { 
  $connection = new PDO("ibm:SAMPLE", "db2inst1", $passwd, array(
    PDO::ATTR_PERSISTENT => TRUE, 
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
  ); 
}
catch (Exception $e) {
  echo($e->getMessage());
}
$result = $connection->query('SELECT firstnme, lastname FROM employee');
if (!$result) {
  print "<p>Could not retrieve employee list: " . $connection->errorMsg(). "</p>";
}
while ($row = $result->fetch()) {
  print "<p>Name:". $row[0]. $row[1]."</p>";
}

