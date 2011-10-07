<?php

return array(
	'series' => array(
		'id' => array('pk' => true),
		'name',
		'next_episode',
		'missed',
		'active' => array('unsigned' => true),
		'url',
		'deleted' => array('unsigned' => true),
		'o' => array('unsigned' => true),
		'watching' => array('unsigned' => true),
		'tvdb_series_id',
		'data',
		'uptodate' => array('unsigned' => true, 'default' => 0),
	),
	'seasons' => array(
		'series_id' => array('unsigned' => true),
		'season' => array('unsigned' => true),
		'episodes' => array('unsigned' => true),
	),
	'variables' => array(
		'name',
		'value',
	),
);


