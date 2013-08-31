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
		return $db->select_fields('seasons', 'season, episodes', array('series_id' => $this->id));
	}

	public function get_num_seasons() {
		return count($this->seasons);
	}
}
