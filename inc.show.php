<?php

class Show extends db_generic_record {
	static public function get( $id ) {
		global $db;
		$conditions = array('id' => $id, 'user_id' => USER_ID);
		$show = $db->select('series', $conditions, null, 'Show')->first();
		return $show;
	}

	public function get_current_season() {
		return (int)$this->next_episode;
	}

	public function get_seasons() {
		global $db;
		return $db->select_by_field('seasons', 'season', array('series_id' => $this->id))->all();
	}

	public function get_first_season() {
		return array_reduce($this->seasons, function($first, $season) {
			return !$first || $season->runs_from < $first->runs_from ? $season : $first;
		});
	}

	public function get_last_season() {
		return array_reduce($this->seasons, function($last, $season) {
			return !$last || $season->runs_from > $last->runs_from ? $season : $last;
		});
	}

	public function get_runs_from() {
		if ( $this->seasons ) {
			$season = $this->first_season;
			return $season->runs_from ?: null;
		}
	}

	public function get_runs_to() {
		if ( $this->seasons ) {
			$season = $this->last_season;
			return $season->runs_to ?: null;
		}
	}

	public function get_pretty_runs_from() {
		if ( $this->runs_from ) {
			return date('M Y', strtotime($this->runs_from));
		}

		return '?';
	}

	public function get_pretty_runs_to() {
		if ( $this->runs_to ) {
			return date('M Y', strtotime($this->runs_to));
		}

		return '?';
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

	public function update( $data ) {
		foreach ($data as $key => $value) {
			$this->$key = $value;
		}

		global $db;
		return $db->update('series', $data, array('id' => $this->id));
	}

	public function downloadTVDVInfo( $filepath ) {
		$url = 'http://www.thetvdb.com/api/' . TVDB_API_KEY . '/series/' . $this->tvdb_series_id . '/all/en.zip';
		$context = stream_context_create(array('http' => array('timeout' => 2)));
		$content = @file_get_contents($url, FALSE, $context);
		if ( !$content ) {
			return 0;
		}
		return file_put_contents($filepath, $content);
	}

	public function updateTVDB() {
		global $db;

		if ( $this->tvdb_series_id ) {
			$zipfile = tempnam(sys_get_temp_dir(), 'series_');
			if ( !$this->downloadTVDVInfo($zipfile) ) {
				return false;
			}

			// read from it
			$zip = new ZipArchive;
			$zip->open($zipfile);
			$xml = $zip->getFromName('en.xml');
			$zip->close();

			// delete it, no cache
			unlink($zipfile);

			$xml = simplexml_load_string($xml);
			$data = (array)$xml->Series;

			// save description
			$db->update('series', array(
				'description' => $data['Overview'],
				'data' => json_encode($data),
				'changed' => time(),
			), array('id' => $this->id));

			// get seasons
			$seasons = $runsFrom = $runsTo = array();
			foreach ( $xml->Episode AS $episode ) {
				// There are 3 season/episode sources in TVDB's data. See env.php.original
				$S = (int) (string) $episode->{TVDB_DATA_FIELD_SEASON};
				$E = (int) (string) $episode->{TVDB_DATA_FIELD_EPISODE};

				if ( $S && $E ) {
					$seasons[$S] = isset($seasons[$S]) ? max($seasons[$S], $E) : $E;

					$aired = (string)$episode->FirstAired;
					if ( $aired ) {
						$date = date('Y-m-d', is_numeric($aired) ? $aired : strtotime($aired));

						$runsFrom[$S] = isset($runsFrom[$S]) ? min($runsFrom[$S], $date) : $date;
						$runsTo[$S] = isset($runsTo[$S]) ? max($runsTo[$S], $date) : $date;
					}
				}
			}

			// save seasons
			$db->begin();
			$db->delete('seasons', array('series_id' => $this->id));
			foreach ( $seasons AS $S => $E ) {
				if ( isset($runsFrom[$S], $runsTo[$S]) ) {
					$db->insert('seasons', array(
						'series_id' => $this->id,
						'season' => $S,
						'episodes' => $E,
						'runs_from' => $runsFrom[$S],
						'runs_to' => $runsTo[$S],
					));
				}
			}
			$db->commit();

			return true;
		}
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
	}

}
