<?php

namespace rdx\series;

class Config {

	static public $options = array(
		'max_watching' => array(
			'title' => 'Max no. of `watching` shows',
			'type' => 'number',
			'default' => 3,
			'min' => 0,
		),
		'banners' => array(
			'title' => 'Show banners on mouseover',
			'type' => 'checkbox',
			'default' => 1,
		),
		'load_tvdb_inactive' => array(
			'title' => 'Load TVDB info for inactive shows (more queries!)',
			'type' => 'checkbox',
			'default' => 0,
		),
		'with_dailytvtorrents' => array(
			'title' => 'Allow for DailyTvTorrents names',
			'type' => 'checkbox',
			'default' => 1,
		),
		'with_tvtorrents' => array(
			'title' => 'Allow for TvTorrents ids',
			'type' => 'checkbox',
			'default' => 0,
		),
	);
	public $vars = null;

	function loadVars() {
		global $db;

		$this->vars = $db->select_fields('variables', 'name, value', '1=1');
	}

	function ensureVars() {
		is_array($this->vars) || $this->loadVars();
	}

	function __get( $name ) {
		return $this->get($name);
	}

	function get( $name, $alt = null ) {
		$this->ensureVars();

		if ( 2 > func_num_args() ) {
			if ( isset(self::$options[$name]['default']) ) {
				$alt = self::$options[$name]['default'];
			}
		}

		return isset($this->vars[$name]) ? $this->vars[$name] : $alt;
	}

}
