<?php

use rdx\series\Show;

return array(
	'version' => 4,
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
			'url' => array('null' => false, 'default' => ''),
			'deleted' => array('null' => false, 'unsigned' => true, 'size' => 1, 'default' => 0),
			'o' => array('unsigned' => true),
			'watching' => array('null' => false, 'unsigned' => true, 'size' => 1, 'default' => 0),
			'tvdb_series_id' => array('null' => false, 'default' => '0'),
			'data',
			'uptodate' => array('null' => false, 'unsigned' => true, 'size' => 1, 'default' => 0),
			'description',
			'tvtorrents_show_id',
			'dailytvtorrents_name',
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
			Show::$_db = $db;
			$shows = Show::all("imdb_id is null and data like '%IMDB_ID%'");
			foreach ($shows as $show) {
				$data = json_decode($show->data, true);
				if (is_string($data['IMDB_ID'] ?? 0)) {
					$show->update([
						'imdb_id' => $data['IMDB_ID'],
					]);
				}
			}
		},
	],
);
