<?php

if ( file_exists($file = __DIR__ . '/env.php') ) {
	require $file;
}

defined('TVDB_API_KEY') or define('TVDB_API_KEY', getenv('TVDB_API_KEY'));

// Possible: Combined_season, SeasonNumber, DVD_season
defined('TVDB_DATA_FIELD_SEASON') or define('TVDB_DATA_FIELD_SEASON', getenv('TVDB_DATA_FIELD_SEASON') ?: 'SeasonNumber');

// Possible: Combined_episodenumber, EpisodeNumber, DVD_episodenumber
defined('TVDB_DATA_FIELD_EPISODE') or define('TVDB_DATA_FIELD_EPISODE', getenv('TVDB_DATA_FIELD_EPISODE') ?: 'EpisodeNumber');

defined('USER_ID') or define('USER_ID', getenv('USER_ID'));
