<?php

define('REQUEST_MICROTIME', microtime(1));

require 'env.php';

require 'inc.functions.php';

require '../inc/db/db_sqlite.php'; // https://github.com/rudiedirkx/db_generic
//$db = db_mysql::open(array('user' => 'usagerplus', 'pass' => 'usager', 'database' => 'tests'));
$db = db_sqlite::open(array('database' => 'db/series.sqlite3'));

if ( !$db ) {
	exit("<p>Que pasa, amigo!? I can't read from or write to the database! Do you have a writable ./db/ folder? <strong>No bueno!</strong></p>");
}

// Everything. UTF-8. Always. Everywhere.
mb_internal_encoding('UTF-8');
header('Content-type: text/html; charset=utf-8');

// Env constants
define('AJAX', strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
define('MOBILE', is_int(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'mobile')));

// Load config structure
require 'inc.config.php';
$cfg = new Config;

require 'inc.ensure-db-schema.php';
