<?php

use rdx\series\Show;

return array(
	'version' => 5,
	'tables' => array(
		'users' => array(
			'id' => array('pk' => true),
			'email' => array('null' => false),
			'password' => array('null' => false),
		),
		'series' => array(
			'id' => array('pk' => true),
			'user_id' => array('unsigned' => true, 'default' => 1),
			'name' => array('null' => false),
			'next_episode' => array('null' => false, 'default' => ''),
			'active' => array('null' => false, 'unsigned' => true, 'size' => 1, 'default' => 1),
			'deleted' => array('null' => false, 'unsigned' => true, 'size' => 1, 'default' => 0),
			'watching' => array('null' => false, 'unsigned' => true, 'size' => 1, 'default' => 0),
			'description',
			'banner_url',
			'imdb_id',
			'created' => array('null' => false, 'unsigned' => true, 'default' => 0),
			'changed' => array('null' => false, 'unsigned' => true, 'default' => 0),
		),
		'seasons' => array(
			'id' => array('pk' => true),
			'series_id' => array('unsigned' => true),
			'season' => array('unsigned' => true),
			'episodes' => array('unsigned' => true),
			'runs_from' => array('type' => 'date'),
			'runs_to' => array('type' => 'date'),
			'edited' => array('type' => 'int', 'default' => 0),
		),
		'variables' => array(
			'name' => array('type' => 'text', 'unique' => true),
			'value' => array('type' => 'text'),
		),
	),
	'updates' => [
		1 => function($db) {
			// imdb_id
		},
	],
);
