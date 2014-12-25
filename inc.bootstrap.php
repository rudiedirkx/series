<?php

define('REQUEST_MICROTIME', microtime(1));

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

// Load config structure
require 'inc.config.php';
$cfg = new Config;

// Verify db schema
if ( !$cfg->last_db_schema_sync || $cfg->last_db_schema_sync < strtotime('-1 hour') ) {
	$schema = require 'inc.db-schema.php';
	$db->schema($schema);

	// REPLACE INTO or MERGE won't work, because `variables` doesn't have a pk
	$db->delete('variables', array('name' => 'last_db_schema_sync'));
	$db->insert('variables', array('name' => 'last_db_schema_sync', 'value' => time()));
}
