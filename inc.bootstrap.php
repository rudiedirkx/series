<?php

define('REQUEST_MICROTIME', microtime(1));

require 'env.php';
require __DIR__ . '/vendor/autoload.php';

$db = db_sqlite::open(array('database' => __DIR__ . '/db/series.sqlite3'));
if ( !$db ) {
	exit("<p>Que pasa, amigo!? I can't read from or write to the database! Do you have a writable ./db/ folder? <strong>No bueno!</strong></p>");
}

$db->ensureSchema(require 'inc.db-schema.php');

// Everything. UTF-8. Always. Everywhere.
mb_internal_encoding('UTF-8');
header('Content-type: text/html; charset=utf-8');

// Env constants
define('AJAX', strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
define('MOBILE', is_int(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'mobile')));

// Load config structure
require 'inc.config.php';
$cfg = new Config;
