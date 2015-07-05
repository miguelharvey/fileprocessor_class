<?php
/**
 * User: mjharvey
 * Date: 7/5/15
 * Time: 13:45
 */

require_once __DIR__.'/../vendor/autoload.php';


define("APP_DB_HOST",			"localhost");
define("APP_DB_USER",			"<you user>");
define("APP_DB_CRED",			"<your pass>");
define("MODEL_STUDENT_DB",		"FakeStudentData");

$fn = "GPA_Testing.csv";
echo "Input Filename:  " . $fn . PHP_EOL;

// Sets the source filename and instantiates
$a = new FileProcessor($fn);

// Sets the MySQLi object
$a->con_obj =  new mysqli( APP_DB_HOST, APP_DB_USER, APP_DB_CRED, MODEL_STUDENT_DB);

// Loads all reasonable rows from the file
$a->load();

// Loops over those rows to check them more closely
echo ( $a->validate() );

// Looks up the remaining rows in the database and reports on successes and failures per row
echo ( $a->process() );

// Saves the resulting good row data to an output file
$a->save();

// fin
exit;

