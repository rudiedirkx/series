<?php

use rdx\imdb\AuthSession;
use rdx\imdb\Client;
use rdx\series\Model;
use rdx\series\RemoteImdb;

require __DIR__ . '/inc.bootstrap-env.php';
require __DIR__ . '/vendor/autoload.php';

// Heroku
if ( $uri = getenv('DATABASE_URL') ) {
	$uri = preg_replace('#^postgres://#', '', $uri);
	$db = new db_pgsql(array('uri' => $uri));
}
// Heroku/pgsql test
elseif ( defined('HEROKU_PG_URI') && HEROKU_PG_URI ) {
	$db = new db_pgsql(array('uri' => HEROKU_PG_URI));
}
// Local
else {
	$db = new db_sqlite(DB_FILE);
}
$db->ensureSchema(require 'inc.db-schema.php');

Model::$_db = $db;

// Everything. UTF-8. Always. Everywhere.
mb_internal_encoding('UTF-8');
header('Content-type: text/html; charset=utf-8');

// Env constants
define('AJAX', strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') == 'xmlhttprequest');

$imdb = new Client(new AuthSession(IMDB_AT_MAIN, IMDB_UBID_MAIN));
$remote = new RemoteImdb($imdb);
