<?php

return array(
	'series' => array(
		'id' => array('pk' => true),
		'name' => array('null' => false),
		'next_episode' => array('null' => false, 'default' => ''),
		'missed' => array('null' => false, 'default' => ''),
		'active' => array('null' => false, 'unsigned' => true, 'size' => 1, 'default' => 1),
		'url' => array('null' => false, 'default' => ''),
		'deleted' => array('null' => false, 'unsigned' => true, 'size' => 1, 'default' => 0),
		'o' => array('unsigned' => true),
		'watching' => array('null' => false, 'unsigned' => true, 'size' => 1, 'default' => 0),
		'tvdb_series_id',
		'data',
		'uptodate' => array('null' => false, 'unsigned' => true, 'size' => 1, 'default' => 0),
		'description',
		'tvtorrents_show_id',
		'dailytvtorrents_name',
		'created' => array('null' => false, 'unsigned' => true, 'default' => 0),
		'changed' => array('null' => false, 'unsigned' => true, 'default' => 0),
	),
	'seasons' => array(
		'series_id' => array('unsigned' => true),
		'season' => array('unsigned' => true),
		'episodes' => array('unsigned' => true),
	),
	'variables' => array(
		'name',
		'value' => array('type' => 'text'),
	),
);


