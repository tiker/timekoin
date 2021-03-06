<?PHP
include 'configuration.php';
include 'function.php';
set_time_limit(300);
//***********************************************************************************
//***********************************************************************************
if(BALANCE_DISABLED == TRUE || TIMEKOIN_DISABLED == TRUE)
{
	// This has been disabled
	exit;
}
//***********************************************************************************
//***********************************************************************************
mysql_connect(MYSQL_IP,MYSQL_USERNAME,MYSQL_PASSWORD);
mysql_select_db(MYSQL_DATABASE);

$loop_active = mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'balance_heartbeat_active' LIMIT 1"),0,"field_data");

// Check if loop is already running
if($loop_active == 0)
{
	// Set the working status of 1
	mysql_query("UPDATE `main_loop_status` SET `field_data` = '1' WHERE `main_loop_status`.`field_name` = 'balance_heartbeat_active' LIMIT 1");
}
else
{
	// Loop called while still working
	exit;
}
//***********************************************************************************
//***********************************************************************************
$current_transaction_cycle = transaction_cycle(0);
$next_transaction_cycle = transaction_cycle(1);

$foundation_active = intval(mysql_result(mysql_query("SELECT * FROM `main_loop_status` WHERE `field_name` = 'foundation_heartbeat_active' LIMIT 1"),0,"field_data"));

// Can we work on the key balances in the database?
// Not allowed 120 seconds before and 45 seconds after transaction cycle.
if(($next_transaction_cycle - time()) > 120 && (time() - $current_transaction_cycle) > 45 && $foundation_active == 0)
{
	// 2000 Transaction Cycles Back in time to index
	$time_2000 = time() - 600000;

	$sql = "SELECT public_key_to FROM `transaction_history` WHERE `timestamp` > $time_2000 GROUP BY `public_key_to` ORDER BY RAND() LIMIT 1";
	$sql_result = mysql_query($sql);
	$sql_row = mysql_fetch_array($sql_result);
		
	$public_key_hash = hash('md5', $sql_row["public_key_to"]);

	// Run a balance index if one does not already exist
	$balance_index = mysql_result(mysql_query("SELECT public_key_hash FROM `balance_index` WHERE `public_key_hash` = '$public_key_hash' LIMIT 1"),0,0);

	if(empty($balance_index) == TRUE)
	{
		// No index balance, go ahead and create one
		check_crypt_balance($sql_row["public_key_to"]);
	}
}
//***********************************************************************************
//***********************************************************************************
// Script finished, set status to 0
mysql_query("UPDATE `main_loop_status` SET `field_data` = 0 WHERE `main_loop_status`.`field_name` = 'balance_heartbeat_active' LIMIT 1");

// Record when this script finished
mysql_query("UPDATE `main_loop_status` SET `field_data` = " . time() . " WHERE `main_loop_status`.`field_name` = 'balance_last_heartbeat' LIMIT 1");
?>
