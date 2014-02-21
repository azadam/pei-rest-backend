<?php

$db_host = 'endor-rw-db.c0nfdnl1qvqa.us-west-2.rds.amazonaws.com';
$db_db = 'pei';
$db_user = 'pei';
$db_pass = 'aLpFzxVdLPs27gBo';

mysql_connect($db_host, $db_user, $db_pass);
if (mysql_select_db($db_db)) {
    $load_database = false;
    
    if (isset($_GET['force']) && $_GET['force']) {
        $load_database = true;
    } else {
        $search = mysql_query("SHOW TABLES");
        if (mysql_num_rows($search) > 0) {
            echo "Tables already exist in the db -- this action WILL destroy any stored data!<br />\n";
            echo "<a href='?force=1'>Click here to force the load</a>\n";
        } else {
            $load_database = true;
        }
    }
    
    if ($load_database) {
        $search = mysql_query("SHOW TABLES");
        echo mysql_num_rows($search) . " tables before load<br />\n";
        
        $mysql_cmd = "/usr/bin/mysql -h " . escapeshellarg($db_host) . " -u " . escapeshellarg($db_user) . " -p" . escapeshellarg($db_pass) . " " . escapeshellarg($db_db);
        passthru('cat PEI.sql | ' . $mysql_cmd);
        
        $search = mysql_query("SHOW TABLES");
        echo mysql_num_rows($search) . " tables after load<br />\n";
    }
} else {
    die("Could not connect to database");
}
