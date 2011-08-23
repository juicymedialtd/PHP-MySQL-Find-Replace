<?php
// Find and replace facility for complete MySQL database
//
// Originally written by Mark Jackson @ MJDIGITAL
// Can be used by anyone - but give me a nod if you do!
// http://www.mjdigital.co.uk/blog
// http://www.mjdigital.co.uk/blog/search-and-replace-text-in-whole-mysql-database/
//
// PKDAVIES:
// Changed REGEX to LIKE
// Added "`" qoutes around field names
// Added output to screen only

// SEARCH FOR
$search        = 'src="http://www.url.com/';

// REPLACE WITH
$replace    = 'src="/'; // (used if queryType below is set to 'replace')

// DB Details
$hostname = "localhost";
$database = "";
$username = "";
$password = "";

// Query Type: 'search' or 'replace'
//$queryType = 'replace';
$queryType = 'search';

// show errors (.ini file dependant) - true/false
$showErrors = true;


if($showErrors) {
    error_reporting(E_ALL);
    ini_set('error_reporting', E_ALL);
    ini_set('display_errors',1);
}

// Create connectio to DB
$MJCONN = mysql_pconnect($hostname, $username, $password) or trigger_error(mysql_error(),E_USER_ERROR);
mysql_select_db($database,$MJCONN);

// Get list of tables
$table_sql = 'SHOW TABLES';
$table_q = mysql_query($table_sql,$MJCONN) or die("Cannot Query DB: ".mysql_error());
$tables_r = mysql_fetch_assoc($table_q);
$tables = array();

do{
    $tables[] = $tables_r['Tables_in_'.strtolower($database)];
}while($tables_r = mysql_fetch_assoc($table_q));

// create array to hold required SQL
$use_sql = array();

$rowHeading = ($queryType=='replace') ? 
        'Replacing \''.$search.'\' with \''.$replace.'\' in \''.$database."'\n\nSTATUS    |    ROWS AFFECTED    |    TABLE/FIELD    (+ERROR)\n"
      : 'Searching for \''.$search.'\' in \''.$database."'\n\nSTATUS    |    ROWS CONTAINING    |    TABLE/FIELD    (+ERROR)\n";

$output = $rowHeading;

$summary = '';

// LOOP THROUGH EACH TABLE
foreach($tables as $table) {
    // GET A LIST OF FIELDS
    $field_sql = 'SHOW FIELDS FROM '.$table;
    $field_q = mysql_query($field_sql,$MJCONN);
    $field_r = mysql_fetch_assoc($field_q);

    // compile + run SQL
    do {
        $field = $field_r['Field'];
        $type = $field_r['Type'];

        switch(true) {
            // set which column types can be replaced/searched
            case stristr(strtolower($type),'char'): $typeOK = true; break;
            case stristr(strtolower($type),'text'): $typeOK = true; break;
            case stristr(strtolower($type),'blob'): $typeOK = true; break;
            case stristr(strtolower($field_r['Key']),'pri'): $typeOK = false; break; // do not replace on primary keys
            default: $typeOK = false; break;
        }

        if($typeOK) { 
        	// Field type is OK ro replacement
            // create unique handle for update_sql array
            $handle = $table.'_'.$field;
            if($queryType=='replace') {
                $sql[$handle]['sql'] = 'UPDATE `'.$table.'` SET `'.$field.'` = REPLACE(`'.$field.'`,\''.$search.'\',\''.$replace.'\')';
            } else {
                $sql[$handle]['sql'] = 'SELECT * FROM `'.$table.'` WHERE `'.$field.'` LIKE \'%'.$search.'%\'';
            }
            
            //$output .= $sql[$handle]['sql']."\n";
 
            // execute SQL
            $error = false;
            $query = @mysql_query($sql[$handle]['sql'],$MJCONN) or $error = mysql_error();
            $row_count = @mysql_affected_rows() or $row_count = 0;

            // store the output (just in case)
            $sql[$handle]['result'] = $query;
            $sql[$handle]['affected'] = $row_count;
            $sql[$handle]['error'] = $error;

            // Write out Results into $output
            $output .= ($query) ? 'OK        ' : '--        ';
            $output .= ($row_count>0) ? '<strong>'.$row_count.'</strong>            ' : '<span style="color:#CCC">'.$row_count.'</span>            ';
            $fieldName = '`'.$table.'`.`'.$field.'`';
            $output .= $fieldName;
            $erTab = str_repeat(' ', (60-strlen($fieldName)) );
            $output .= ($error) ? $erTab.'(ERROR: '.$error.')' : '';

            $output .= "\n";
        }
    }while($field_r = mysql_fetch_assoc($field_q));
}

// write the output out to the page
echo '<pre>';
echo $output."\n";
echo '<pre>';
?> 
