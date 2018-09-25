<?php

require_once("/home/uesp/secrets/slavecheck.secrets");

$DB_MASTER_HOST = "db1.uesp.net";
$DB_SLAVE_HOST  = "content3.uesp.net";

$DATABASE_TO_CHECK = "uesp_net_wiki5";
$TABLE_TO_CHECK = "text";
//$TABLES_TO_IGNORE = array( "text", "pagerenderlog", "logging" );
$TABLES_TO_IGNORE = array( );

$SHOW_DIFF_ROW_DETAILS = false;
$SHOW_COMPARE_PROGRESS = false;

if ($DATABASE_TO_CHECK == "") die("No database specified!");
if ($TABLE_TO_CHECK == "") $TABLE_TO_CHECK = "*";

print("Checking slave replication from $DB_MASTER_HOST to $DB_SLAVE_HOST on $DATABASE_TO_CHECK.$TABLE_TO_CHECK...\n");

$db1 = new mysqli($DB_MASTER_HOST, $uespSlaveCheckUser, $uespSlaveCheckPW, $DATABASE_TO_CHECK);
if ($db1->connect_error) die("Could not connect to mysql master database on $DB_MASTER_HOST!");

$db2 = new mysqli($DB_SLAVE_HOST, $uespSlaveCheckUser, $uespSlaveCheckPW, $DATABASE_TO_CHECK);
if ($db2->connect_error) die("Could not connect to mysql slave database on $DB_SLAVE_HOST!");

$LAST_PROFILE_TIME = 0;


function ShouldIgnoreTable($table)
{
	global $TABLES_TO_IGNORE;
	
	if (in_array($table, $TABLES_TO_IGNORE)) return true;
	
	return false;
}


function LoadRecords(&$db, $database, $table)
{
	global $LAST_PROFILE_TIME;
	
	$records = array();
	$startTime = microtime(true);
	
	$query = "SHOW KEYS FROM `$database`.`$table` WHERE Key_name='PRIMARY';";
	$result = $db->query($query);
	
	if ($result === false || $result->num_rows == 0)
	{
		$query = "SHOW COLUMNS FROM `$database`.`$table` WHERE `Key`='PRI';";
		$result = $db->query($query);
		
		if ($result === false || $result->num_rows == 0)
		{
			print("\tError: Failed to find the primary key in table $database.$table! " . $db->error . "\n");
			return false;
		}
		
		$columns = $result->fetch_assoc();
		$primaryKey = $columns['Field'];
	}
	else
	{
		$columns = $result->fetch_assoc();
		$primaryKey = $columns['Column_name'];
	}
	
	$query = "SELECT * FROM `$database`.`$table`;";
	$result = $db->query($query);
	
	if ($result === false)
	{
		print("\tError: Failed to load records from table $database.$table! " . $db->error . "\n");
		return false;
	}
	
	while (($row = $result->fetch_assoc()))
	{
		$id = $row[$primaryKey];
		$records[$id] = $row;
	}

	$LAST_PROFILE_TIME = round(microtime(true) - $startTime, 3);
	return $records;
}


function CheckRecordData($id, &$data1, &$data2)
{
	global $SHOW_DIFF_ROW_DETAILS;
	
	foreach ($data1 as $field => &$value1)
	{
		$value2 = $data2[$field];
		
		if (!array_key_exists($field, $data2))
		{
			if ($SHOW_DIFF_ROW_DETAILS) print("\t$id:$field - Missing slave record value!\n");
			continue;
		}
		
		if ($value1 !== $value2)
		{
			if ($SHOW_DIFF_ROW_DETAILS)
			{
				print("\t$id:$field - Value mismatch!\n");
				print("\t\t\tMaster = $value1\n");
				print("\t\t\tMaster = $value2\n");
			}
			continue;
		}
	}
	
	foreach ($data2 as $field => &$value2)
	{
		$value1 = $data1[$field];
		
		if (!array_key_exists($field, $data1))
		{
			if ($SHOW_DIFF_ROW_DETAILS) print("\t$id:$field - Missing master record value!\n");
			continue;
		}
	}
	
	return true;
}


function CheckRecords($records1, $records2, $database, $table)
{
	global $SHOW_COMPARE_PROGRESS;
	
	$totalRecords = 0;
	$errorCount = 0;
	$missingCount1 = 0;
	$missingCount2 = 0;
	
	foreach ($records1 as $id => &$data1)
	{
		$totalRecords++;
		
		$data2 = $records2[$id];
		
		if (!array_key_exists($id, $records2))
		{
			$errorCount++;
			$missingCount2++;
			print("\t$id: Missing slave record!\n");
			continue;
		}
		
		if ($totalRecords % 1000 == 0 && $SHOW_COMPARE_PROGRESS) print("\tChecking data at record $totalRecords...\n");
		
		if (!CheckRecordData($id, $data1, $data2)) $errorCount++;
		
		$records2[$id]['__checked'] = true;
	}
	
	foreach ($records2 as $id => &$data2)
	{
		$data1 = $records1[$id];
		
		if (!array_key_exists($id, $records1))
		{
			$errorCount++;
			$missingCount1++;
			print("\t$id: Missing master record!\n");
			continue;
		}
	}
	
	print("\tFound $errorCount data mismatches in $database.$table!\n");
}


function DetailTableCheck($database, $table)
{
	global $db1, $db2;
	global $LAST_PROFILE_TIME;
	
	$records1 = LoadRecords($db1, $database, $table);
	$loadTime1 = $LAST_PROFILE_TIME;
	
	$records2 = LoadRecords($db2, $database, $table);
	$loadTime2 = $LAST_PROFILE_TIME;
	
	if ($records1 === false || $records2 === false) return false;
	
	$count1 = count($records1);
	$count2 = count($records2);
	
	print("\tLoaded $count1 records from master database ($loadTime1 sec)!\n");
	print("\tLoaded $count2 records from slave database ($loadTime2 sec)!\n");
	
	CheckRecords($records1, $records2, $database, $table);
	
	return true;
}


function ChecksumTableDb(&$db, $database, $table)
{
	$query = "CHECKSUM TABLE `$database`.`$table`;";
	$result = $db->query($query);
	
	if ($result === false) 
	{
		print("\tError: Failed to checksum table $database.$table! " . $db->error . "\n");
		return false;
	}

	$row = $result->fetch_assoc();
	if ($row == null) return false;
	
	return $row['Checksum'];
}


function ChecksumTable($database, $table)
{
	global $db1, $db2;
	
	$checksum1 = ChecksumTableDb($db1, $database, $table);
	$checksum2 = ChecksumTableDb($db2, $database, $table);
	
	if ($checksum1 === false || $checksum2 === false) return false;
	
	if ($checksum1 != $checksum2)
	{
		print("$database.$table :: Checksum Error ($checksum1, $checksum2)!\n");
		DetailTableCheck($database, $table);
		return false;
	}
	
	print("$database.$table :: OK ($checksum1)\n");
	return true;
}


function ChecksumAllTables($database)
{
	global $db1, $db2;
	
	$query = "SHOW TABLES FROM `$database`;";
	$result = $db1->query($query);
	
	if ($result === false || $result->num_rows == 0)
	{
		print("Error: Failed to find tables in $database!\n");
		return false;
	}
	
	print("Found {$result->num_rows} tables...\n");
	$tables = array();
	
	while (($row = $result->fetch_array()))
	{
		$tableName = $row[0];
		
		if (ShouldIgnoreTable($tableName))
		{
			print("Ignoring table $tableName...\n");
			continue;
		}
		
		print("Found table $tableName...\n");
		$tables[] = $tableName;
		ChecksumTable($database, $tableName);
	}
	
	return true;
}
	

function DoTableChecks()
{
	global $DATABASE_TO_CHECK;
	global $TABLE_TO_CHECK;
	
	if ($TABLE_TO_CHECK == "*")
	{
		ChecksumAllTables($DATABASE_TO_CHECK);
	}
	else
	{
		ChecksumTable($DATABASE_TO_CHECK, $TABLE_TO_CHECK);
	}
}

DoTableChecks();
