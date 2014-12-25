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

	public function get_runs_from() {
		if ( $this->seasons ) {
			$season = $this->seasons[1];
			return $season->runs_from ?: null;
		}
	}

	public function get_runs_to() {
		if ( $this->seasons ) {
			$season = $this->seasons[count($this->seasons)];
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

	public function updateTVDB() {
		global $db;

		if ( $this->tvdb_series_id ) {
			// get package with details
			$zipfile = tempnam(sys_get_temp_dir(), 'series_');
			$url = 'http://www.thetvdb.com/api/' . TVDB_API_KEY . '/series/' . $this->tvdb_series_id . '/all/en.zip';
			$context = stream_context_create(array('http' => array('timeout' => 2)));
			$content = @file_get_contents($url, FALSE, $context);
			if ( !$content ) {
				return false;
			}
			file_put_contents($zipfile, $content);

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
				// TV airings might have different episode/season numbers than DVD productions, so the number
				// of episodes in a season depends on this constant, which should be a Config var.
				if ( TVDB_DVD_OVER_TV ) {
					$S = (int)(string)$episode->Combined_season;
					$E = (int)(string)$episode->Combined_episodenumber;
				}
				else {
					$S = (int)(string)$episode->SeasonNumber;
					$E = (int)(string)$episode->EpisodeNumber;
				}

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
				$db->insert('seasons', array(
					'series_id' => $this->id,
					'season' => $S,
					'episodes' => $E,
					'runs_from' => @$runsFrom[$S] ?: '',
					'runs_to' => @$runsTo[$S] ?: '',
				));
			}
			$db->commit();

			return true;
		}
	}

}
