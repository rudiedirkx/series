<?php

use rdx\series\Config;
use rdx\series\Model;

define('REQUEST_MICROTIME', microtime(1));

require __DIR__ . '/inc.bootstrap-env.php';
require __DIR__ . '/vendor/autoload.php';

// Heroku
if ( $uri = getenv('DATABASE_URL') ) {
	$uri = preg_replace('#^postgres://#', '', $uri);
	$db = db_pgsql::open(array('uri' => $uri));
}
// Heroku/pgsql test
elseif ( defined('HEROKU_PG_URI') && HEROKU_PG_URI ) {
	$db = db_pgsql::open(array('uri' => HEROKU_PG_URI));
}
// Local
else {
	$db = db_sqlite::open(array('database' => __DIR__ . '/db/series.sqlite3'));
}
$db->ensureSchema(require 'inc.db-schema.php');

Model::$_db = $db;

// Everything. UTF-8. Always. Everywhere.
mb_internal_encoding('UTF-8');
header('Content-type: text/html; charset=utf-8');

// Env constants
define('AJAX', strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
define('MOBILE', is_int(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'mobile')));

$cfg = new Config;
