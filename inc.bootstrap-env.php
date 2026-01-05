<?php

if ( file_exists($file = __DIR__ . '/env.php') ) {
	require $file;
}

defined('IMDB_AT_MAIN') or define('IMDB_AT_MAIN', getenv('IMDB_AT_MAIN'));
defined('IMDB_UBID_MAIN') or define('IMDB_UBID_MAIN', getenv('IMDB_UBID_MAIN'));

defined('LIVE_IMDB_RATINGS_ODDS') or define('LIVE_IMDB_RATINGS_ODDS', getenv('LIVE_IMDB_RATINGS_ODDS') ?: 5);

defined('USER_ID') or define('USER_ID', getenv('USER_ID'));
