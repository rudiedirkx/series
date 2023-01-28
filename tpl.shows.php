<?php

use rdx\series\Show;

foreach ( $series AS $n => $show ) {
	$classes = [];
	$show->active && $classes[] = 'active';
	$show->watching && $classes[] = 'watching';

	$hilite = $hilited == $show->id;
	if ( $hilite ) {
		$classes[] = 'hilited';
	}

	if ( $show->deleted ) {
		$classes[] = 'deleted';
	}

	$thisSeasonsEpisodes = '';
	if ( ($show->season || @$show->seasons[1]) ) {
		$season = $show->next_episode ? $show->season : $show->seasons[1];
		if ( $season ) {
			$episodes = $season->episodes;

			$thisSeasonsEpisodes = 'Season ' . $season->season . ' has ' . $episodes . ' episodes. ';

			if ( $season->runs_from && $season->runs_to ) {
				$from = date('M Y', strtotime($season->runs_from));
				$to = date('M Y', strtotime($season->runs_to));
				$thisSeasonsEpisodes .= 'It ran from ' . $from . ' to ' . $to . '. ';
			}

			$thisSeasonsEpisodes = ' title="' . trim($thisSeasonsEpisodes) . '"';
		}
	}

	$tvdbAction = 'link';
	$banner = '';
	if ( $show->tvdb_series_id ) {
		$tvdbAction = 'update';
		$classes[] = 'with-tvdb';

		if ( $cfg->banners ) {
			if ( $data = @json_decode($show->data) ) {
				if ( @$data->banner && is_string($data->banner) ) {
					$banner = 'data-banner="' . html($data->banner) . '"';
				}
			}
		}
	}

	$title = '';
	if ( $show->description ) {
		$title = ' title="' . html($show->description) . '"';
	}

	echo '<tr class="' . implode(' ', $classes) . '" data-showid="' . $show->id . '"' . $banner . '>' . "\n";
	if ($cfg->sortable) {
		echo "\t" . '<td class="move"><img src="move.png" alt="Move" /></td>' . "\n";
	}
	echo "\t" . '<td class="tvdb"><a class="' . $tvdbAction . '" href="?' . $tvdbAction . 'show=' . $show->id . '" title="Click to (connect to TVDB and) download meta information"><img src="tvdb.png" alt="TVDB" /></a></td>' . "\n";
	echo "\t" . '<td class="name show-banner"><span' . $title . ' class="show-name" id="show-name-' . $show->id . '">' . html($show->name) . '</span> <a class="edit-title" href="#" onclick="return changeValue(this.parentNode.parentNode.firstElementChild, ' . $show->id . ',\'name\', \'' . html(addslashes($show->name)) . '\');" title="Click to edit show name"><img src="pencil.png" /></a></td>' . "\n";
	if ($cfg->banners) {
		echo "\t" . '<td class="picture show-banner">' . ( $banner ? '<img src="picture.png" alt="banner" />' : '' ) . '</td>' . "\n";
	}
	echo "\t" . '<td class="picture imdb">' . ( $show->imdb_id ? '<a href="https://www.imdb.com/title/' . $show->imdb_id . '/" target="_blank"><img src="imdb.png" alt="imdb" /></a>' : '' ) . '</td>' . "\n";
	echo "\t" . '<td class="next oc"><a' . $thisSeasonsEpisodes . ' href="#" onclick="return changeValue(this, ' . $show->id . ', \'next_episode\');">' . ( trim($show->next_episode ?? '') ? str_replace(' ', '&nbsp;', $show->next_episode) : '&nbsp;' ) . '</a></td>' . "\n";
	echo "\t" . '<td class="info"><a href="#"><img src="information.png" alt="Info" /></a></td>' . "\n";
	echo "\t" . '<td class="seasons">' . ( $show->seasons ? '<a title="Total episodes: ' . $show->total_episodes . " (" . $show->pretty_runs_from . " - " . $show->pretty_runs_to . ")\n\n" . 'Click to reset seasons/episodes list" href="?resetshow=' . $show->id . '" onclick="return confirm(\'Want to delete all tvdb data for this show?\');">' . $show->num_seasons . '</a>' : '' ) . '</td>' . "\n";
	if ( !$show->deleted ) {
		echo "\t" . '<td class="icon active"><a href="?id=' . $show->id . '&active=' . ( $show->active ? 0 : 1 ) . '" title="' . ( $show->active ? 'Active. Click to deactivate' : 'Inactive. Click to activate' ) . '"><img src="' . ( $show->active ? 'no' : 'yes' ) . '.gif" alt="' . ( $show->active ? 'ACTIVE' : 'INACTIVE' ) . '" /></a></td>' . "\n";
	}
	else {
		echo "\t" . '<td class="icon active"></td>' . "\n";
	}
	if ( $show->active ) {
		echo "\t" . '<td class="icon watching">' . ( !$show->watching || $cfg->max_watching > 1 ? '<a href="?watching=' . $show->id . '" title="Click to highlight currently watching. Max ' . $cfg->max_watching . '"><img src="' . ($show->watching ? 'arrow_down' : 'arrow_up') . '.png" alt="ARROW" /></a>' : '' ) . '</td>' . "\n";
	}
	elseif ( !$show->deleted ) {
		echo "\t" . '<td class="icon watching"><a class="ajaxify" href="?delete=' . $show->id . '" title="Click to visually hide."><img src="delete.png" alt="DELETE" /></a></td>' . "\n";
	}
	else {
		echo "\t" . '<td class="icon watching"><a class="ajaxify" href="?undelete=' . $show->id . '" title="Click to unhide."><img src="delete.png" alt="UNDELETE" /></a></td>' . "\n";
	}
	echo "\t" . '<td class="icon download"><a href="?downloadtvdb=' . $show->id . '"><img src="disk.png" /></a></td>' . "\n";
	echo '</tr>' . "\n\n\n\n\n\n";
}
