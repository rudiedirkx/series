<?php

define('TVDB_API_KEY', '94F0BD0D5948FE69');

## tvdb
# get mirror
#   http://www.thetvdb.com/api/94F0BD0D5948FE69/mirrors.xml
# get show id
#   http://www.thetvdb.com/api/GetSeries.php?seriesname=community
# get show details/episodes/etc
#   http://www.thetvdb.com/api/94F0BD0D5948FE69/series/<seriesid>/all/en.zip

# 1. get server time
# 2. http://www.thetvdb.com/api/Updates.php?type=all&time=1318015462
# 3. store in vars[last_tvdb_update]
# 4. get info from tvdb
# 5. store in series.data


require 'inc.bootstrap.php';

// Define env vars
define('AJAX', strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

// Get and reset highlighted show
$hilited = (int)@$_COOKIE['series_hilited'];
if ( $hilited ) {
	setcookie('series_hilited', '', 1);
}



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

$cfg = new Config;

/*echo '<pre>';
print_r($var);
var_dump($var->ass);
var_dump($var->upload_label);
print_r($var);
echo '</pre>';*/



// New show
if ( isset($_POST['name']) && !isset($_POST['id']) ) {
	$insert = array(
		'name' => $_POST['name'],
		'deleted' => 0,
		'active' => 1,
		'watching' => 0,
	);

	if ( !empty($_POST['dont_connect_tvdb']) || !empty($_POST['tvdb_series_id']) ) {
		if ( !empty($_POST['tvdb_series_id']) ) {
			$insert['tvdb_series_id'] = $_POST['tvdb_series_id'];

			if ( !empty($_POST['replace_existing']) ) {
				$existingShow = $db->select('series', array('deleted' => 0, 'name' => $insert['name']), null, 'Show')->first();
				if ( $existingShow ) {
					$update = array('name' => $insert['name'], 'tvdb_series_id' => $insert['tvdb_series_id']);
					@$_POST['tvtorrents_show_id'] && $update['tvtorrents_show_id'] = @$_POST['tvtorrents_show_id'];
					@$_POST['dailytvtorrents_name'] && $update['dailytvtorrents_name'] = @$_POST['dailytvtorrents_name'];
					$db->update('series', $update, array('id' => $existingShow->id));
				}
				else {
					$adding_show_tvdb_result = true;
					$noredirect = true;
				}

				$insert = false;
			}
		}

		if ( $insert ) {
			$db->insert('series', $insert);
		}

		if ( empty($noredirect) ) {
			header('Location: ./');
			exit;
		}
	}
	else {
		$adding_show_tvdb_result = simplexml_load_file('http://www.thetvdb.com/api/GetSeries.php?seriesname=' . urlencode($_POST['name']));
	}
}

// Change show order
else if ( isset($_POST['order']) ) {
	$db->begin();
	foreach ( explode(',', $_POST['order']) AS $i => $id ) {
		$db->update('series', array('o' => $i), array('id' => $id));
	}
	$db->commit();

	exit('OK');
}

// Edit scrollable field: next
else if ( isset($_POST['id'], $_POST['dir']) ) {
	if ( 0 != (int)$_POST['dir'] ) {
		$delta = $_POST['dir'] < 0 ? -1 : 1;

		// fetch show
		$show = Show::get($_POST['id']);
		$ne = $show->next_episode;

		// Parse and up/down `next_episode`
		$parts = array_map('intval', explode('.', $ne));
		$parts[count($parts)-1] += $delta;

		// Default feedback
		$episodes = 0;

		// Check S if E changed.
		if ( 2 == count($parts) ) {
			$S =& $parts[0];
			$E =& $parts[1];

			// Moving down
			if ( $E < 1 && $S > 1 ) {
				if ( isset($show->seasons[$S-1]) ) {
					$S -= 1;
					$E = $show->seasons[$S];
				}
			}
			// Moving up
			else if ( isset($show->seasons[$S]) && $E > $show->seasons[$S] ) {
				$S += 1;
				$E = 1;
			}

			// Add "0" padding
			$E = str_pad($E, 2, '0', STR_PAD_LEFT);

			// More detailed feedback
			$episodes = $db->select_one('seasons', 'episodes', array('series_id' => $show->id, 'season' => $S));
		}

		// Save
		$ne = implode('.', $parts);
		$db->update('series', array('next_episode' => $ne), array('id' => $show->id));

		// respond
		header('Content-type: text/json');
		exit(json_encode(array(
			'next_episode' => $ne,
			'season' => $S,
			'episodes' => (int)$episodes,
		)));
	}

	exit('W00t!?');
}

// Edit field: next
else if ( isset($_POST['id'], $_POST['next_episode']) ) {
	$db->update('series', array('next_episode' => $_POST['next_episode']), array('id' => $_POST['id']));

	exit($db->select_one('series', 'next_episode', array('id' => $_POST['id'])));
}

// Edit field: missed
else if ( isset($_POST['id'], $_POST['missed']) ) {
	$db->update('series', array('missed' => $_POST['missed']), array('id' => $_POST['id']));

	exit($db->select_one('series', 'missed', array('id' => $_POST['id'])));
}

// Edit field: name
else if ( isset($_POST['id'], $_POST['name']) ) {
	$db->update('series', array('name' => $_POST['name']), array('id' => $_POST['id']));

	exit($db->select_one('series', 'name', array('id' => $_POST['id'])));
}

// Toggle active status
else if ( isset($_GET['id'], $_GET['active']) ) {
	$active = (bool)$_GET['active'];

	$update = array('active' => $active);
	!$active && $update['watching'] = false;

	$db->update('series', $update, array('id' => $_GET['id']));

	header('Location: ./');
	exit;
}

// Delete show
else if ( isset($_GET['delete']) ) {
	$db->update('series', 'deleted = 1', array('id' => $_GET['id']));

	header('Location: ./');
	exit;
}

// Set current/watching show
else if ( isset($_GET['watching']) ) {
	// Toggle selected
	if ( $cfg->max_watching > 1 ) {
		$show = Show::get($_GET['watching']);

		// Unwatch
		if ($show->watching) {
			$update = array('watching' => 0);
			$db->update('series', $update, array('id' => $show->id));
		}
		// Watch
		else {
			$maxWatching = $db->select_one('series', 'max(watching)', '1');
			$update = array('watching' => $maxWatching + 1);
			$db->update('series', $update, array('id' => $show->id));

			// Only allow $cfg->max_watching shows to have watching > 1
			$allWatching = $db->select_fields('series', 'id,watching', 'watching > 0 ORDER BY watching DESC, id DESC');
			$allWatching = array_keys($allWatching);
			$illegallyWatching = array_slice($allWatching, $cfg->max_watching);
			$db->update('series', array('watching' => 0), array('id' => $illegallyWatching));
		}
	}
	// Only selected (no toggle, just ON)
	else {
		$db->update('series', 'watching = 0', '1');
		$db->update('series', 'watching = 1', array('id' => $_GET['watching']));
	}

	header('Location: ./');
	exit;
}

// Update one show
else if ( isset($_GET['updateshow']) ) {
	$id = (int)$_GET['updateshow'];

	if ( $show = Show::get($id) ) {
		if ( !$show->tvdb_series_id ) {
			// get tvdb's series_id // simple API's rule!
			$xml = simplexml_load_file('http://www.thetvdb.com/api/GetSeries.php?seriesname=' . urlencode($show->name));
			if ( isset($xml->Series[0]) ) {
				$Series = (array)$xml->Series[0];
				if ( isset($Series['seriesid'], $Series['IMDB_ID']) ) {
					// okay, this is the right one
					$db->update('series', array(
						'name' => $Series['SeriesName'],
						'tvdb_series_id' => $Series['seriesid'],
						'data' => json_encode($Series),
					), array('id' => $id));

					$show->tvdb_series_id = $Series['seriesid'];
				}
			}
		}

		if ( $show->tvdb_series_id ) {
			// get package with details
			$zipfile = './tmp/show-' . $show->tvdb_series_id . '.zip';
			file_put_contents($zipfile, file_get_contents('http://www.thetvdb.com/api/' . TVDB_API_KEY . '/series/' . $show->tvdb_series_id . '/all/en.zip'));

			// read from it
			$zip = new ZipArchive;
			if ($zip->open($zipfile) !== TRUE) {
				exit('Ugh?');
			}
			$xml = $zip->getFromName('en.xml');
			$zip->close();

			$xml = simplexml_load_string($xml);
			$data = (array)$xml->Series;

			// save description
			$db->update('series', array(
				'description' => $data['Overview'],
				'data' => json_encode($data),
			), array('id' => $id));

			// get seasons
			$seasons = array();
			foreach ( $xml->Episode AS $episode ) {
				$S = (int)(string)$episode->SeasonNumber;
				$E = (int)(string)$episode->EpisodeNumber;
				if ( $S && $E ) {
					if ( !isset($seasons[$S]) ) {
						$seasons[$S] = $E;
					}
					else {
						$seasons[$S] = max($seasons[$S], $E);
					}
				}
			}

			// save seasons
			$db->begin();
			$db->delete('seasons', array('series_id' => $show->id));
			foreach ( $seasons AS $S => $E ) {
				$db->insert('seasons', array(
					'series_id' => $show->id,
					'season' => $S,
					'episodes' => $E,
				));
			}
			$db->commit();
		}
	}

	if ( AJAX ) {
		setcookie('series_hilited', $id);
		echo 'OK';
	}
	else {
		header('Location: ./#show-' . $id);
	}

	exit;
}

// reset one show
else if ( isset($_GET['resetshow']) ) {
	// delete seasons/episodes
	$db->delete('seasons', array('series_id' => $_GET['resetshow']));

	// delete tvdb series id
	$db->update('series', array('tvdb_series_id' => 0), array('id' => $_GET['resetshow']));

	header('Location: ./');
	exit;
}

// parse .torrent to download subs
else if ( isset($_FILES['torrent']) ) {
	echo '<pre>';
	print_r($_FILES['torrent']);

	// must be a .torrent file
	if ( preg_match('/\.torrent$/i', $_FILES['torrent']['name']) ) {
		// must have the torrent mime type
		if ( !isset($_FILES['torrent']['type']) || is_int(strpos($_FILES['torrent']['type'], 'torrent')) ) {
			require_once '../tests/torrent/TorrentReader.php'; // https://github.com/rudiedirkx/Torrent-reader

			// parse torrent
			$torrent = TorrentReader::parse(file_get_contents($_FILES['torrent']['tmp_name']), $reader);

			if ( $torrent ) {
				$download = array('name' => '', 'episodes' => array());

				// parse file names
				foreach ( $torrent['info']['files'] AS $i => $file ) {
					$filename = end($file['path']);

					// get Season and Episode
					if ( preg_match('/s(\d\d?)e(\d\d?)/i', $filename, $match) ) {
						if ( !$download['name'] ) {
							$download['name'] = strtr(substr($filename, 0, strpos($filename, $match[0])), array('.' => ' '));
						}

						array_shift($match);
						list($S, $E) = array_map('intval', $match);

						// get 'scene' (?)
						if ( preg_match('/[\.\-]([^\.\-]+)\.[a-z0-9]+$/', $filename, $match) ) {
							$scene = $match[1];
							$download['episodes'][] = array(
								'season' => $S,
								'episode' => $E,
								'scene' => $scene,
							);
						}
					}
				}
//print_r($download);

				// search for subtitles on $site
				$site = 'http://www.podnapisi.net';
				foreach ( $download['episodes'] AS $i => $episode ) {
					if ( $i ) {
//						sleep(1); // any reason?
					}

//echo "\n";
//print_r($episode);
					$q = array(
						'sK' => $download['name'],
						'sT' => 1, // Series, not movie
						'sTE' => $episode['episode'],
						'sTS' => $episode['season'],
						'sR' => $episode['scene'],
						'sJ' => 2, // English
					);
					$url1 = $site . '/en/ppodnapisi/search?' . http_build_query($q);
					echo '<a href="'.$url1.'">'.$url1.'</a>' . "\n";
/*echo $url1 . "\n";
					$searchPage = file_get_contents($url1);
					if ( preg_match('#href="(/[a-z]{2}/(?:[a-z0-9\-]+?)\-subtitles\-[a-z]\d+)"#i', $searchPage, $match) ) {
						$url2 = $site . $match[1];
echo $url2 . "\n";
						$downloadPage = file_get_contents($url2);
						if ( preg_match('#href="(/[a-z]{2}/ppodnapisi/download/i/\d+/k/[a-f0-9]+)"#i', $downloadPage, $match) ) {
							$url3 = $site . $match[1];
//var_dump($url3);
							echo '<a href="'.$url3.'">'.$url3.'</a>' . "\n";
						}
					}*/

					flush();
				}
			}
		}
	}
	exit;
}

$exists = false;
if (@$adding_show_tvdb_result) {
	$existingShow = $db->select('series', array('deleted' => 0, 'name' => $_POST['name']), null, 'Show')->first();
	if ($existingShow) {
		$exists = true;
		if ($existingShow->tvdb_series_id && empty($_POST['tvdb_series_id'])) {
			$_POST['tvdb_series_id'] = $existingShow->tvdb_series_id;
		}
	}
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<link rel="shortcut icon" type="image/x-icon" href="favicon.ico" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Series</title>
<style>
::-webkit-scrollbar {
	background: #f7f7f7;
	width: 30px;
}
::-webkit-scrollbar:hover {
	background: #ddd;
}
::-webkit-scrollbar-thumb {
	background: #aaa;
	border-radius: 15px;
}
:hover ::-webkit-scrollbar-thumb {
	background: #888;
}

body, table { font-family: Verdana, Arial, sans-serif; font-size: 14px; border-collapse: separate; border-spacing: 0; }
a { color: blue; }
a img { border: 0; }
.error { color: red; }
table { border: solid 1px #000; }
table.loading { opacity: 0.5; }
tbody tr { background-color: #eee; }
tbody tr:nth-child(even) { background-color: #ddd; }
tbody tr.hilited td { background-color: lightblue; }
td, th { border: solid 1px #fff; vertical-align: middle; }
a { text-decoration: none; }
a[href] { text-decoration: underline; }
.name .show-name { color: red; }
tr.active .show-name { color: green; }
td.seasons { text-align: center; }
td.oc a { display: block; text-decoration: none; color: black; }
td.oc a:hover { background-color: #ccc; }
td.oc a.eligible, td.oc a.eligible:hover { background-color: #faa; color: #000; }
td.next a, td.missed a { color: #888; }
tr.hd th { padding: 4px; }
tr.watching td { font-weight: bold; }
td.icon { padding-right: 4px; padding-left: 4px; }
tr:not(.with-tvdb) .tvdb img { opacity: 0.3; }
td:not(.move) img { width: 16px; height: 16px; display: block; }
<? if ($cfg->upload_label): ?>
	label[for=torrent] { cursor: pointer; text-decoration: underline; color: blue; }
	#torrent { position: absolute; left: -999px; }
<? else: ?>
	label[for=torrent] > span:after { content: ":"; }
<? endif ?>
td.move { cursor: move; }
tr:target td,
tr.hilite td {
	background: lightblue;
}
#banner { position: fixed; top: 10px; right: 10px; }
@media (max-width: 1100px) {
	#banner { display: none !important; }
}
@media (max-width: 400px) {
	tr > .tvdb,
	span.edit-title,
	tr > .missed,
	tr > .icon {
		display: none;
	}
}
</style>
</head>

<body>

<img id="banner" />

<form method=post action enctype="multipart/form-data">
	<p>
		<label tabindex="0" for="torrent">
			<span>Upload .torrent to download subs</span>
			<input type="file" name="torrent" id="torrent" onchange="this.form.submit();">
		</label>
	</p>
</form>

<table id="series">
<thead>
<tr class="hd" bgcolor="#bbbbbb">
	<? if ($cfg->sortable): ?>
		<th></th>
	<? endif ?>
	<th class="tvdb"></th>
	<th>Name <a href="javascript:$('showname').focus();void(0);">+</a></th>
	<? if ($cfg->banners): ?>
		<th class="picture"></th>
	<? endif ?>
	<th>Nxt</th>
	<th class="missed">Not</th>
	<th class="seasons" title="Existing seasons">S</th>
	<th class="icon" colspan="2"></th>
</tr>
</thead>
<tbody class="sortable">
<?php

try {
	$series = $db->fetch('
		SELECT
			*
		FROM
			series
		WHERE
			deleted = 0
		GROUP BY
			id
		ORDER BY
			active DESC,
			' . ( $cfg->watching_up_top ? '(watching > 0) DESC,' : '' ) . '
			' . ( $cfg->sortable ? 'o ASC,' : '' ) . '
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

?>
</tbody>
</table>

<br />

<form method="post" action style="padding-top: 10px;">
	<fieldset style="display: inline-block;">
		<legend>Add show <?=$n+2?></legend>
		<p>Name: <input id="showname" type="search" name="name" value="<?=(string)@$_POST['name']?>" /></p>
		<p>The TVDB id: <input id="add_tvdb_series_id" type="search" name="tvdb_series_id" value="<?=(string)@$_POST['tvdb_series_id']?>" /></p>

		<?if (@$existingShow):?>
			<?if ($cfg->with_tvtorrents):?>
				<p>TvTorrents id: <input type="search" name="tvtorrents_show_id" value="<?=$existingShow->tvtorrents_show_id?>" /></p>
			<?endif?>
			<?if ($cfg->with_dailytvtorrents):?>
				<p>DailyTvTorrents name: <input type="search" name="dailytvtorrents_name" value="<?=$existingShow->dailytvtorrents_name?>" /></p>
			<?endif?>
		<?endif?>

		<p><input type="submit" value="Next" /><p>

		<?if (@$adding_show_tvdb_result):?>
			<script>window.onload = function() { scrollTo(0, document.body.scrollHeight); };</script>

			<p><label><input type="checkbox" name="dont_connect_tvdb" /> Don't connect to The TVDB</label></p>
			<p<?if (false === @$existingShow): ?> class="error"<? endif ?>><label><input type="checkbox" name="replace_existing" <? if ($exists): ?>checked<? endif ?> /> Save The TVDB into existing show</label></p>

			<?if (!is_scalar($adding_show_tvdb_result)):?>
				<div class="search-results">
					<ul>
						<?foreach ($adding_show_tvdb_result->Series AS $show):?>
							<li>
								<a class="tvdb-search-result" title="<?=html($show->Overview)?>" data-id="<?=$show->seriesid?>" href="#<?=$show->seriesid?>"><?=html($show->SeriesName)?></a>
								<!--
									(<?=$show->banner?>)
									<img src="http://www.thetvdb.com/banners/graphical/<?=$show->seriesid?>-g.jpg" alt="banner" />
								-->
								(<a target=_blank href="http://www.thetvdb.com/?tab=series&id=<?=$show->seriesid?>">=&gt;</a>)
								<div class="tvdb-search-result-description"><?=html($show->Overview)?></div>
							</li>
						<?endforeach?>
					</ul>
				</div>
			<?endif?>
		<?endif?>
	</fieldset>
</form>

<br />
<br />

<script src="rjs.js"></script>
<script>
$$('a.tvdb-search-result').on('click', function(e) {
	e.preventDefault();
	var id = this.data('id');
	$('add_tvdb_series_id').value = id;
});

$$('td.tvdb > a').on('click', function(e) {
	e.preventDefault();
	this.getChildren('img').attr('src', 'loading16.gif');
	$.post(this.attr('href')).on('done', function(e) {
		var t = this.responseText;
		RorA(t);
	});
});

$$('tr[data-banner] .name').on('mouseover', function(e) {
	var src = 'http://thetvdb.com/banners/' + this.firstAncestor('tr').data('banner');
	$('banner').attr('src', src).show();
}).on('mouseout', function(e) {
	$('banner').hide();
});

function RorA(t, fn) {
	fn || (fn = function() {
		location.reload();
	});
	if ( t == 'OK' ) {
		return fn();
	}
	alert(t);
}

function changeName(id, name) {
	$.post('', 'id=' + id + '&name=' + encodeURIComponent(name)).on('done', function(e, t) {
		$('show-name-' + id).setHTML(t);
	});
	return false;
}

function changeValue(o, id, n, v) {
	v == undefined && (v = o.getText().trim());
	var nv = prompt('New value:', v);
	if ( null === nv ) {
		return false;
	}
	if ( 'name' == n ) {
		return changeName(id, nv);
	}
	return doAndRespond(o, 'id=' + id + '&' + n + '=' + nv);
}

function doAndRespond(o, d) {
	o.setHTML('<img src="spinner.gif" />');
	$.post('', d).on('done', function(e, rsp) {
		if ( typeof rsp == 'string' ) {
			return o.setHTML(rsp);
		}

		o.setHTML(rsp.next_episode);
		if ( rsp.season && rsp.episodes ) {
			o.attr('title', 'Season ' + rsp.season + ' has ' + rsp.episodes + ' episodes');
		}
	});
	return false;
}

$('series')
	.on('contextmenu', '.next.oc a', function(e) {
		e.preventDefault();
		this.addClass('eligible');
	})
	.on('keydown', '.next.oc a', function(e) {
		var space = e.key == Event.Keys.space,
			up = e.key == Event.Keys.up,
			down = e.key == Event.Keys.down;
		if ( space ) {
			e.preventDefault();
			this.toggleClass('eligible');
		}
		else if ( up || down ) {
			if ( this.hasClass('eligible') ) {
				e.preventDefault();
				var direction = up ? 1 : -1;
				doAndRespond(this, 'id=' + this.firstAncestor('tr').attr('showid') + '&dir=' + direction);
			}
		}
	})
	.on('blur', '.next.oc a', function(e) {
		this.removeClass('eligible');
	})
	.on('mouseleave', '.next.oc a', function(e) {
		this.removeClass('eligible');
	})
	.on('mousewheel', '.next.oc a', function(e) {
		var direction = 'number' == typeof e.originalEvent.wheelDelta ? -e.originalEvent.wheelDelta : e.originalEvent.detail;
		// Firefox messes up here... It doesn't cancel the scroll event. If I move
		// the preventDefault to the top of this function, sometimes it does cancel
		// the event (and sometimes it doesn't!?). Very strange behaviour that I can't
		// seem to reproduce in http://jsfiddle.net/rudiedirkx/dDW63/show/ (always works).
		if ( this.hasClass('eligible') && direction ) {
			e.preventDefault();
			direction /= -Math.abs(direction);
			doAndRespond(this, 'id=' + this.firstAncestor('tr').attr('showid') + '&dir=' + direction);
		}
	});
</script>

</body>

<!-- <?= count($db->queries) ?> queries -->
<!-- <? print_r($db->queries) ?> -->

</html>


