<?php

header('Content-type: text/html; charset=utf-8');

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


require_once '../inc/db/db_sqlite.php'; // https://github.com/rudiedirkx/db_generic
//$db = db_mysql::open(array('user' => 'usagerplus', 'pass' => 'usager', 'database' => 'tests'));
$db = db_sqlite::open(array('database' => 'db/series.sqlite3'));

if ( !$db ) {
	exit('<p>Que pasa, amigo!? No connecto to databaso! Si? <strong>No bueno!</strong></p>');
}

// verify db schema
$schema = require 'db-schema.php';
$db->schema($schema);

// Everything. UTF-8. Always. Everywhere.
mb_internal_encoding('UTF-8');



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

class Config {
	public $defaults = array(
		'upload_label' => 1,
		'sortable' => 0,
		'active_up_top' => 1,
		'watching_up_top' => 1,
		'max_watching' => 3,
	);
	public $vars = null;

	function loadVars() {
		global $db;

		$this->vars = $db->select_fields('variables', 'name, value', '1');
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
			if ( isset($this->defaults[$name]) ) {
				$alt = $this->defaults[$name];
			}
		}

		return isset($this->vars[$name]) ? $this->vars[$name] : $alt;
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

			// save description
			$db->update('series', array(
				'description' => $xml->Series->Overview,
			), array('id' => $id));

			// get seasons
			$seasons = array();
			foreach ( $xml->Episode AS $episode ) {
				$S = (int)$episode->Combined_season;
				$E = (int)$episode->Combined_episodenumber;
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

	header('Location: ./#show-' . $id);
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
.name a:first-child { color: red; }
tr.active .name a:first-child { color: green; }
td.seasons { text-align: center; }
td.oc a { display: block; text-decoration: none; color: black; }
td.oc a:hover { background-color: #ccc; }
td.oc a.eligable, td.oc a.eligable:hover { background-color: #faa; color: #000; }
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
tr:target td {
	 background:lightblue;
}
</style>
</head>

<body>

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
	<th></th>
	<th>Name <a href="javascript:$('#showname').focus();void(0);">+</a></th>
	<th>Nxt</th>
	<th>Not</th>
	<th title="Existing seasons">S</th>
	<th colspan="2"></th>
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

	$thisSeasonsEpisodes = '';
	if ( $show->tvdb_series_id ) {
		$classes[] = 'with-tvdb';

		if ( isset($show->seasons[$show->current_season]) ) {
			$episodes = $show->seasons[$show->current_season];
			if ( $episodes ) {
				$thisSeasonsEpisodes = ' title="Season ' . (int)$show->current_season . ' has ' . $episodes . ' episodes"';
			}
		}
	}

	$title = '';
	if ( $show->description ) {
		$title = ' title="' . html(substr($show->description, 0, 200)) . '...' . '"';
	}

	echo '<tr class="' . implode(' ', $classes) . '" id="show-' . $show->id . '" showid="' . $show->id . '">' . "\n";
	if ($cfg->sortable) {
		echo "\t" . '<td class="move"><img src="move.png" alt="Move" /></td>' . "\n";
	}
	echo "\t" . '<td class="tvdb"><a href="?updateshow=' . $show->id . '" title="Click to (connect to TVDB and) download meta information"><img src="tvdb.png" alt="TVDB" /></a></td>' . "\n";
	echo "\t" . '<td class="name"><a' . $title . ' id="show-name-' . $show->id . '"' . ( $show->url ? ' href="' . $show->url . '"' : '' ) . '>' . $show->name . '</a> (<a href="#" onclick="return changeValue(this.parentNode.firstChild, ' . $show->id . ',\'name\');" title="Click to edit show name">e</a>)</td>' . "\n";
	echo "\t" . '<td class="next oc"><a' . $thisSeasonsEpisodes . ' href="#" onclick="return changeValue(this, ' . $show->id . ', \'next_episode\');">' . ( trim($show->next_episode) ? str_replace(' ', '&nbsp;', $show->next_episode) : '&nbsp;' ) . '</a></td>' . "\n";
	echo "\t" . '<td class="missed oc"><a href="#" onclick="return changeValue(this, ' . $show->id . ', \'missed\');">' . ( trim($show->missed) ? trim($show->missed) : '&nbsp;' ) . '</a></td>' . "\n";
	echo "\t" . '<td class="seasons">' . ( $show->seasons ? '<a title="Total episodes: ' . array_sum($show->seasons) . "\n\n" . 'Click to reset seasons/episodes list" href="?resetshow=' . $show->id . '" onclick="return confirm(\'Want to delete all tvdb data for this show?\');">' . $show->num_seasons . '</a>' : '' ) . '</td>' . "\n";
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
		<p><input type="submit" value="Next" /><p>

		<?if (@$adding_show_tvdb_result):?>
			<script>window.onload = function() { scrollTo(0, document.body.scrollHeight); };</script>

			<p><label><input type="checkbox" name="dont_connect_tvdb" /> Don't connect to The TVDB</label></p>
			<p<?if (false === @$existingShow): ?> class="error"<? endif ?>><label><input type="checkbox" name="replace_existing" <? if (in_array(mb_strtolower(@$_POST['name']), $showNames)): ?>checked<? endif ?> /> Save The TVDB into existing show</label></p>

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

<script src="jquery-1.7.2.min.js"></script>
<script>
$('a.tvdb-search-result').on('click', function(e) {
	e.preventDefault();
	var $this = $(this),
		id = $this.data('id');
	$('#add_tvdb_series_id').val(id);
});

function changeName(id, name) {
	$.post('', 'id=' + id + '&name=' + encodeURIComponent(name), function(t) {
		$('#show-name-' + id).html(t);
	});
	return false;
}

function changeValue(o, id, n, v) {
	var $o = $(o);
	v == undefined && (v = $o.html())
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
	var $o = $(o);
	$o.html('<img src="spinner.gif" />');
	$.post('', d, function(rsp) {
		if ( 'string' == typeof rsp ) {
			$o.html(rsp);
		}
		else {
			$o.html(rsp.next_episode);
			if ( rsp.season && rsp.episodes ) {
				$o.attr('title', 'Season ' + rsp.season + ' has ' + rsp.episodes + ' episodes');
			}
		}
	});
	return false;
}

$('#series')
	.on('contextmenu', '.next.oc a', function(e) {
		e.preventDefault();
		var $this = $(this);
		$this.addClass('eligable');
	})
	.on('mouseleave', '.next.oc a', function(e) {
		var $this = $(this);
		$this.removeClass('eligable');
	})
	.on('mousewheel DOMMouseScroll', '.next.oc a', function(e) {
		var $this = $(this),
			direction = 'number' == typeof e.originalEvent.wheelDelta ? -e.originalEvent.wheelDelta : e.originalEvent.detail;
		// Firefox messes up here... It doesn't cancel the scroll event. If I move
		// the preventDefault to the top of this function, sometimes it does cancel
		// the event (and sometimes it doesn't!?). Very strange behaviour that I can't
		// seem to reproduce in http://jsfiddle.net/rudiedirkx/dDW63/show/ (always works).
		if ( $this.hasClass('eligable') && direction ) {
			e.preventDefault();
			direction /= -Math.abs(direction);
			doAndRespond($this, 'id=' + $this.closest('tr').attr('showid') + '&dir=' + direction);
		}
	});
</script>

</body>

<!-- <?= count($db->queries); ?> queries -->

</html>
<?php

function html($str) {
	return htmlspecialchars($str, ENT_COMPAT, 'UTF-8');
}


