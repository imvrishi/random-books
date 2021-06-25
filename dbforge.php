<?php 
$fromDB = 'globalin_gap';
$toDB = 'globalin_myhr';
$fromUser = 'globalin';
$toUser = 'globalin';

$link = mysqli_connect('localhost', 'globalin_myhr', 'Myhr2020');
// $dbh2 = mysqli_connect('localhost', 'globalin_gap', 'GlobalHR2020', true);

if (! $link) {
	echo "<br/>Error: Unable to connect to MySQL." . PHP_EOL;
	echo "<br/>Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
	echo "<br/>Debugging error: " . mysqli_connect_error() . PHP_EOL;
	exit;
}

echo "<br/>Success: A proper connection to MySQL was made!";
echo "<br/>Host information: " . mysqli_get_host_info($link);

mysqli_select_db($link, $fromDB);

$fromTables = mysqli_query($link, "SELECT TABLE_TYPE, TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = '{$fromDB}' AND ( TABLE_TYPE = 'BASE TABLE' OR TABLE_TYPE = 'VIEW' )");
if( $fromTables === NULL ) {
	die("<br/>Could not fetch from table list: " . mysql_error());
}
$fromTbls = $fromViews = $fromTables2 = [];
while( $singleTable = mysqli_fetch_assoc( $fromTables ) ) {
	if ( $singleTable['TABLE_TYPE'] == 'BASE TABLE' ) {
		$fromTbls[] = $singleTable['TABLE_NAME'];
		$fromTables2[] = $singleTable;
	} else if ( $singleTable['TABLE_TYPE'] == 'VIEW' ) {
		$fromViews[] = $singleTable['TABLE_NAME'];
	}
}
// var_dump( $fromViews );

$toTables = mysqli_query($link, "SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = '{$toDB}' AND TABLE_TYPE = 'BASE TABLE'");
if( $toTables === NULL ) {
	die("<br/>Could not fetch to table list: " . mysql_error());
}
$toTbls = [];
while( $singleTable = mysqli_fetch_assoc( $toTables ) ) {
	$toTbls[] = $singleTable['TABLE_NAME'];
}

$createTables = array_diff($fromTbls, $toTbls);
foreach( $createTables as $singleTable ) {
	echo "<br/>-- Creating Table {$singleTable} --";
	$showQ = mysqli_query($link, "SHOW CREATE TABLE {$singleTable}");
	$showR = mysqli_fetch_assoc( $showQ )['Create Table'];
	echo "<br/>", $showR, ';';
	echo "<br/>-- Created Table {$singleTable} --";
}
// var_dump( $createTables );
// mysqli_select_db($link, $toDB);

echo "<br/><br/>-- Alter Table Starts --";
foreach( $fromTables2 as $singleTable ) {
	$tblName = $singleTable['TABLE_NAME'];
	if ( in_array( $tblName, $createTables ) ) continue;
	
	$result = mysqli_query($link, "
		SELECT A.column_name, A.ordinal_position, A.data_type, A.column_type, A.column_default, A.is_nullable, A.table_schema, A.table_type FROM
		(
			SELECT c.column_name, c.ordinal_position, c.data_type, c.column_type, c.column_default, c.is_nullable, COUNT(1) rowcount, c.table_schema, t.table_type
			FROM information_schema.columns c
			JOIN information_schema.tables t ON t.table_schema = c.table_schema AND t.table_name = c.table_name
			WHERE
			(
				(c.table_schema='{$fromDB}' AND c.table_name='{$tblName}') OR
				(c.table_schema='{$toDB}' AND c.table_name='{$tblName}')
			)
			AND c.table_name IN ('{$tblName}')
			GROUP BY c.column_name
			HAVING COUNT(1)=1
		) A
	");
	// GROUP BY c.column_name, c.ordinal_position, c.data_type, c.column_type
	
	if ( $result === NULL OR $result->num_rows === 0 ) continue;
	
	echo "<br/>-- Difference found in {$tblName} --";
	while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
		// echo "<br/>", var_export($row, true);
		if ( $row['table_type'] == 'BASE TABLE' )
			$table = 'TABLE';
		else continue;
		
		if ( $toDB == $row['table_schema'] )
			echo "<br/>ALTER {$table} {$tblName} DROP COLUMN {$row['column_name']};";
		if ( $fromDB == $row['table_schema'] ) {
			$sql = "<br/>ALTER {$table} {$tblName} ADD COLUMN {$row['column_name']} {$row['column_type']} ";
			
			if ( $row['is_nullable'] == 'NO' )
				$sql .= "NOT NULL ";
			else
				$sql .= "NULL ";
			
			if ( $row['column_default'] !== NULL )
				$sql .= "DEFAULT {$row['column_default']} ";
			
			echo $sql . ";";
		}
	}
	mysqli_free_result($result);
}

foreach( $fromViews as $singleView ) {
	echo "<br/>-- Creating Table {$singleView} --";
	$showQ = mysqli_query($link, "SHOW CREATE VIEW {$singleView}");
	$showR = mysqli_fetch_assoc( $showQ )['Create View'];
	echo "<br/>DROP VIEW IF EXISTS {$singleView};";
	echo "<br/>", strtr( $showR, $fromUser, $toUser ), ';';
	echo "<br/>-- Created Table {$singleView} --";
}

echo "<br/>-- Alter Table Ends --<br/>";
echo "<br/>Freed Mysql Resources";

echo "<br/>Connection Closed";
mysqli_close($link);
