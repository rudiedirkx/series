<?php

class Show extends db_generic_record {
	static public function get( $id ) {
		global $db;
		$show = $db->select('series', array('id' => $id), null, 'Show')->first();
		return $show;
	}

	public $_cached = array();

	public function __get( $name ) {
		if ( is_callable($method = array($this, 'get_' . $name)) ) {
			$this->_cached[] = $name;
			return $this->$name = call_user_func($method);
		}
	}

	public function get_current_season() {
		return (int)$this->next_episode;
	}

	public function get_seasons() {
		global $db;
		return $db->select_by_field('seasons', 'season', array('series_id' => $this->id))->all();
	}

	public function get_season() {
		$seasons = $this->seasons; // Don't error suppress, because of __get magic
		return @$seasons[$this->current_season]; // No magic here, so do error suppress
	}

	public function get_total_episodes() {
		$episodes = 0;
		foreach ( $this->seasons as $season ) {
			$episodes += $season->episodes;
		}
		return $episodes;
	}

	public function get_num_seasons() {
		return count($this->seasons);
	}
}
