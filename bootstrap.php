<?php

require_once '../inc/db/db_sqlite.php'; // https://github.com/rudiedirkx/db_generic
//$db = db_mysql::open(array('user' => 'usagerplus', 'pass' => 'usager', 'database' => 'tests'));
$db = db_sqlite::open(array('database' => 'db/series.sqlite3'));

if ( !$db ) {
	exit("<p>Que pasa, amigo!? I can't read from or write to the database! Do you have a writable ./db/ folder? <strong>No bueno!</strong></p>");
}

// Verify db schema
$schema = require 'db-schema.php';
$db->schema($schema);

// Ensure writable tmp folder
if ( !is_dir('tmp') || !is_writable('tmp') ) {
	exit("<p>Que pasa, amigo!? You're missing a writable ./tmp/ folder to store tvdb downloads in. <strong>No bueno!</strong></p>");
}

// Everything. UTF-8. Always. Everywhere.
mb_internal_encoding('UTF-8');


