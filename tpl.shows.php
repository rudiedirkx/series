<?php

$lazyload = $cfg->async_inactive ? 'AND active = ' . (int)!isset($_GET['inactive']) : '';
$watching = $cfg->watching_up_top ? '(watching > 0) DESC,' : '';
$sortable = $cfg->sortable ? 'o ASC,' : '';

try {
	$series = $db->fetch('
		SELECT
			*
		FROM
			series
		WHERE
			deleted = 0 ' . $lazyload . '
		GROUP BY
			id
		ORDER BY
			active DESC,
			' . $watching . '
			' . $sortable . '
			LOWER(IF(\'the \' = LOWER(substr(name, 1, 4)), SUBSTR(name, 5), name)) ASC
	', 'Show');
}
catch ( db_exception $ex ) {
	exit('Query error: ' . $ex->getMessage() . "\n");
}

$showNames = array();
$n = -1;
foreach ( $series AS $n => $show ) {
	$showNames[] = mb_strtolower($show->name);

	$classes = array();
	$show->active && $classes[] = 'active';
	$show->watching && $classes[] = 'watching';

	$hilite = $hilited == $show->id;
	if ( $hilite ) {
		$classes[] = 'hilited';
	}

	$thisSeasonsEpisodes = $banner = '';
	if ( $show->tvdb_series_id ) {
		$classes[] = 'with-tvdb';

		if ( ($show->active || $cfg->load_tvdb_inactive || $hilite) && !empty($show->seasons[$show->current_season]) ) {
			$episodes = $show->seasons[$show->current_season];
			$thisSeasonsEpisodes = ' title="Season ' . (int)$show->current_season . ' has ' . $episodes . ' episodes"';
		}

		if ( $cfg->banners ) {
			if ( $data = @json_decode($show->data) ) {
				if ( @$data->banner ) {
					$banner = 'data-banner="' . html($data->banner) . '"';
				}
			}
		}
	}

	$title = '';
	if ( $show->description ) {
		$title = ' title="' . html(substr($show->description, 0, 200)) . '...' . '"';
	}

	echo '<tr class="' . implode(' ', $classes) . '" id="show-' . $show->id . '" showid="' . $show->id . '"' . $banner . '>' . "\n";
	if ($cfg->sortable) {
		echo "\t" . '<td class="move"><img src="move.png" alt="Move" /></td>' . "\n";
	}
	echo "\t" . '<td class="tvdb"><a href="?updateshow=' . $show->id . '" title="Click to (connect to TVDB and) download meta information"><img src="tvdb.png" alt="TVDB" /></a></td>' . "\n";
	echo "\t" . '<td class="name"><span' . $title . ' class="show-name" id="show-name-' . $show->id . '">' . $show->name . '</span> <span class="edit-title">(<a href="#" onclick="return changeValue(this.parentNode.parentNode.firstElementChild, ' . $show->id . ',\'name\');" title="Click to edit show name">e</a>)</span></td>' . "\n";
	if ($cfg->banners) {
		echo "\t" . '<td class="picture">' . ( $banner ? '<img src="picture.png" alt="banner" />' : '' ) . '</td>' . "\n";
	}
	echo "\t" . '<td class="next oc"><a' . $thisSeasonsEpisodes . ' href="#" onclick="return changeValue(this, ' . $show->id . ', \'next_episode\');">' . ( trim($show->next_episode) ? str_replace(' ', '&nbsp;', $show->next_episode) : '&nbsp;' ) . '</a></td>' . "\n";
	echo "\t" . '<td class="missed oc"><a href="#" onclick="return changeValue(this, ' . $show->id . ', \'missed\');">' . ( trim($show->missed) ? trim($show->missed) : '&nbsp;' ) . '</a></td>' . "\n";
	echo "\t" . '<td class="seasons">' . ( ($show->active || $cfg->load_tvdb_inactive || $hilite) && $show->seasons ? '<a title="Total episodes: ' . array_sum($show->seasons) . "\n\n" . 'Click to reset seasons/episodes list" href="?resetshow=' . $show->id . '" onclick="return confirm(\'Want to delete all tvdb data for this show?\');">' . $show->num_seasons . '</a>' : '' ) . '</td>' . "\n";
	echo "\t" . '<td class="icon"><a href="?id=' . $show->id . '&active=' . ( $show->active ? 0 : 1 ) . '" title="' . ( $show->active ? 'Active. Click to deactivate' : 'Inactive. Click to activate' ) . '"><img src="' . ( $show->active ? 'no' : 'yes' ) . '.gif" alt="' . ( $show->active ? 'ACTIVE' : 'INACTIVE' ) . '" /></a></td>' . "\n";
	echo "\t" . '<td class="icon">' . ( !$show->watching || $cfg->max_watching > 1 ? '<a href="?watching=' . $show->id . '" title="Click to highlight currently watching. Max ' . $cfg->max_watching . '"><img src="arrow_right.png" alt="ARROW" /></a>' : '' ) . '</td>' . "\n";
	echo '</tr>' . "\n\n\n\n\n\n";
}
