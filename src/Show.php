<?php

namespace rdx\series;

use ZipArchive;

class Show extends UserModel {

	static public $_table = 'series';

	protected function get_current_season() {
		return (int)$this->next_episode;
	}

	protected function relate_seasons() {
		return $this->to_many(Season::class, 'series_id')->order('season ASC')->key('season');
	}

	protected function get_first_season() {
		return array_reduce($this->seasons, function($first, $season) {
			return !$first || $season->runs_from < $first->runs_from ? $season : $first;
		});
	}

	protected function get_last_season() {
		return array_reduce($this->seasons, function($last, $season) {
			return !$last || $season->runs_from > $last->runs_from ? $season : $last;
		});
	}

	protected function get_runs_from() {
		if ( $this->seasons ) {
			$season = $this->first_season;
			return $season->runs_from ?: null;
		}
	}

	protected function get_runs_to() {
		if ( $this->seasons ) {
			$season = $this->last_season;
			return $season->runs_to ?: null;
		}
	}

	protected function get_pretty_runs_from() {
		if ( $this->runs_from ) {
			return date('M Y', strtotime($this->runs_from));
		}

		return '?';
	}

	protected function get_pretty_runs_to() {
		if ( $this->runs_to ) {
			return date('M Y', strtotime($this->runs_to));
		}

		return '?';
	}

	protected function get_season() {
		$seasons = $this->seasons; // Don't error suppress, because of __get magic
		return @$seasons[$this->current_season]; // No magic here, so do error suppress
	}

	protected function get_total_episodes() {
		$episodes = 0;
		foreach ( $this->seasons as $season ) {
			$episodes += $season->episodes;
		}
		return $episodes;
	}

	protected function get_num_seasons() {
		return count($this->seasons);
	}

	public function updateRemote() : bool {
		return $this->updateImdb();
	}

	public function updateImdb() : bool {
		$result = $GLOBALS['remote']->getInfo($this->imdb_id);
		if (!$result) return false;

		self::$_db->transaction(function($db) use ($result) {
			$this->update([
				'description' => $result->plot,
				'banner_url' => $result->banner,
				'changed' => time(),
			]);

			Season::deleteAll(['series_id' => $this->id, 'edited' => 0]);
			$overriddenSeasons = $db->select_fields('seasons', 'season, season', ['series_id' => $this->id, 'edited' => 1]);
			foreach ( $result->episodes AS $S => $season ) {
				if ( !isset($overriddenSeasons[$S]) ) {
					$db->insert('seasons', array(
						'series_id' => $this->id,
						'season' => $S,
						'episodes' => $season->episodes,
						'runs_from' => $season->runs_from,
						'runs_to' => $season->runs_to,
					));
				}
			}
		});

		return true;
	}

	public function getNextEpisodeSummary() {
		global $db;

		$parts = array_map('intval', explode('.', $this->next_episode));
		if ( count($parts) == 2 ) {
			$season = $db->select('seasons', array('series_id' => $this->id, 'season' => $parts[0]))->first();
			$episodes = $season ? $season->episodes : 0;

			$season_from = $season_to = '';
			if ( $season && $season->runs_from && $season->runs_to ) {
				$season_from = date('M Y', strtotime($season->runs_from));
				$season_to = date('M Y', strtotime($season->runs_to));
			}

			return array(
				'next_episode' => $this->next_episode,
				'season' => $parts[0],
				'episodes' => $episodes,
				'season_from' => $season_from,
				'season_to' => $season_to,
			);
		}

		return array();
	}

}
