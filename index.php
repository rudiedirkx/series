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
$db = db_sqlite::open(array('database' => 'series.sqlite3', 'exceptions' => true));

class myRecordObject {}

db_generic_result::$return_object_class = 'myRecordObject';

if ( !$db ) {
	exit('<p>Que pasa, amigo!? No connecto to databaso! Si? <strong>No bueno!</strong></p>');
}



/**
echo '<pre>';
$success = $db->transaction(function($db) {
	echo "one `series` object:\n";
	var_dump($db->select('series', array('id' => '45'), null, true));
	echo "delete with 0 affected:\n";
	var_dump($db->delete('seasons', 'series_id = ?', 2346));
	echo "update with bogus columns:\n";
	var_dump($db->update('oele', array('a' => true, 'b' => false, 'c' => null), 'x = 4'));
	echo "select by field > 150:\n";
	var_dump($db->select_by_field('series', 'id', 'id > ?', array(150)));
	echo $db->error();
}, $context);
if ( $success ) {
	echo "\n -- SUCCESS -- result:\n";
	var_dump($context['result']);
}
else {
	echo "\n -- FAIL -- exception:\n";
	var_dump($context['exception']);
}
exit('</pre>');
/**/



// verify db tables
$schema = require 'schema.php';
$updates = false;
foreach ( $schema AS $table => $columns ) {
	if ( !$db->table($table) ) {
		if ( !$updates ) {
			echo '<pre>';
			$updates = true;
		}
		echo "creating table `".$table."`\n";
		if ( $db->table($table, $columns) ) {
			echo " -- SUCCESS\n";
		}
		else {
			echo " -- FAIL -- " . $db->error() . "\n";
		}
	}
}
if ( $updates ) {
	echo '</pre>';
}



// New show
if ( isset($_POST['name']) && !isset($_POST['id']) ) {
	$db->insert('series', array(
		'name' => $_POST['name'],
		'deleted' => 0,
		'active' => 1,
		'watching' => 0,
	));

	header('Location: ./');
	exit;
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
		$d = $_POST['dir'] < 0 ? -1 : 1;

		// fetch show
		$show = $db->select('series', array('id' => $_POST['id']), null, true);
		$ne = $show->next_episode;

		// do +1 or -1 for smallest/last part
		$x = array_map('intval', explode('.', $ne));
		$i = count($x)-1;
		$x[$i] += $d;

		// end of season -> next season
		if ( $show->tvdb_series_id && 2 == count($x) ) {
			$S =& $x[0];
			$E =& $x[1];
			$episodes = $db->select_one('seasons', 'episodes', array('series_id' => $show->id, 'season' => $S));
			if ( false !== $episodes ) {
				// next season
				if ( $E > $episodes ) {
					$S++;
					$E = 1;
				}
				// prev season
				else if ( 0 >= $E ) {
					$episodes = $db->select_one('seasons', 'episodes', array('series_id' => $show->id, 'season' => $S-1));
					if ( false !== $episodes ) {
						$S--;
						$E = $episodes;
					}
				}
			}
		}

		// prepend 0's
		if ( 2 <= count($x) ) {
			$S = array_shift($x);
			$x = array_map(function($n) {
				return str_pad((string)$n, 2, '0', STR_PAD_LEFT);
			}, $x);
			array_unshift($x, $S);
		}

		// save
		$ne = implode('.', $x);
		$db->update('series', array('next_episode' => $ne), array('id' => $_POST['id']));
		exit($ne);
	}

	exit($db->select_one('series', 'next_episode', array('id' => $_POST['id'])));
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
	$db->update('series', array('active' => (bool)$_GET['active']), array('id' => $_GET['id']));

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
	$db->update('series', 'watching = 0', '1'); // all of them
	$db->update('series', 'watching = 1', array('id' => $_GET['watching']));

	header('Location: ./');
	exit;
}

// Update one show
else if ( isset($_GET['updateshow']) ) {
	$id = (int)$_GET['updateshow'];

	if ( $show = $db->select('series', array('id' => $id), null, true) ) {
		if ( !$show->tvdb_series_id ) {
			// get tvdb's series_id // simple API's rule!
			$xml = simplexml_load_file('http://www.thetvdb.com/api/GetSeries.php?seriesname='.urlencode($show->name));
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
			$zipfile = './tmp/show-'.$show->tvdb_series_id.'.zip';
			file_put_contents($zipfile, file_get_contents('http://www.thetvdb.com/api/'.TVDB_API_KEY.'/series/'.$show->tvdb_series_id.'/all/en.zip'));

			// read from it
			$zip = zip_open($zipfile);
			while ( $entry = zip_read($zip) ) {
				$filename = zip_entry_name($entry);
				if ( 'en.xml' == $filename ) {
					if ( zip_entry_open($zip, $entry) ) {
						$xml = '';
						while ( $data = zip_entry_read($entry) ) {
							$xml .= $data;
						}
						zip_entry_close($entry);

						$xml = simplexml_load_string($xml);
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
						$db->delete('seasons', array('series_id' => $show->id));
						foreach ( $seasons AS $S => $E ) {
							$db->insert('seasons', array(
								'series_id' => $show->id,
								'season' => $S,
								'episodes' => $E,
							));
						}
					}
				}
			}
		}
	}

	header('Location: ./');
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
						sleep(1); // any reason?
					}

echo "\n";
print_r($episode);
					$q = array(
						'sK' => $download['name'],
						'sTE' => $episode['episode'],
						'sTS' => $episode['season'],
						'sR' => $episode['scene'],
						'sJ' => 2, // 2 = English
					);
					$url1 = $site . '/en/ppodnapisi/search?' . http_build_query($q);
					$searchPage = file_get_contents($url1);
					if ( preg_match('#href="(/[a-z]{2}/(?:[a-z0-9\-]+?)\-subtitles\-[a-z]\d+)"#i', $searchPage, $match) ) {
						$url2 = $site . $match[1];
						$downloadPage = file_get_contents($url2);
						if ( preg_match('#href="(/[a-z]{2}/ppodnapisi/download/i/\d+/k/[a-f0-9]+)"#i', $downloadPage, $match) ) {
							$url3 = $site . $match[1];
//var_dump($url3);
							echo '<a href="'.$url3.'">'.$url3.'</a>' . "\n";
						}
					}

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
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta charset="utf-8" />
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

body, table { font-family: Verdana; font-size: 14px; border-collapse: separate; border-spacing: 0; }
a { color: blue; }
table { border: solid 1px #000; }
table.loading { opacity: 0.5; }
tbody tr:nth-child(odd) { background-color: #eee; }
tbody tr:nth-child(even) { background-color: #ddd; }
tbody tr.hilited td { background-color: lightblue; }
td, th { border: solid 1px #fff; }
a { text-decoration: none; }
a[href] { text-decoration: underline; }
td.oc a { display: block; text-decoration: none; color: black; }
td.oc a:hover { background-color: #ccc; }
td.oc a.eligable, td.oc a.eligable:hover { background-color: #faa; }
tr.hd th { padding: 4px; }
tr.watching td { font-weight: bold; }
td.icon { padding-right: 4px; padding-left: 4px; }
tr:not(.with-tvdb) > .tvdb > a { opacity: 0.3; }
img { width: 16px; height: 16px; }
label[for=torrent] { cursor: pointer; text-decoration: underline; color: blue; }
#torrent { position: absolute; visibility: hidden; }
</style>
</head>

<body>
<form method=post action enctype="multipart/form-data">
	<p><label for="torrent"><input type=file name=torrent id=torrent onchange=this.form.submit()> Upload .torrent to download subs</label></p>
</form>

<table id="series">
<thead>
<tr class="hd" bgcolor="#bbbbbb">
	<th></th>
	<th>Name</th>
	<th>Nxt</th>
	<th>Not</th>
	<th title="Existing seasons">S</th>
	<th colspan="2"></th>
</tr>
</thead>
<tbody class="sortable">
<?php

try {
	$series = $db->fetch('SELECT s.*, COUNT(seasons.series_id) AS num_seasons, FLOOR(next_episode) AS current_season FROM series s LEFT JOIN seasons ON (s.id = seasons.series_id) WHERE s.deleted = 0 GROUP BY s.id ORDER BY s.active DESC, LOWER(IF(\'the \' = LOWER(substr(s.name, 1, 4)), SUBSTR(s.name, 5), s.name)) ASC');
}
catch ( db_exception $ex ) {
	exit('Query error: '.$ex->getMessage()."\n");
}

foreach ( $series AS $n => $show ) {
	$classes = array();
	$show->active && $classes[] = 'active';
	$show->watching && $classes[] = 'watching';

	$show->tvdb_series_id && $classes[] = 'with-tvdb';

	$thisSeasonsEpisodes = '';
	$show->seasons = $show->num_seasons = null;
	if ( $show->tvdb_series_id ) {
		$show->seasons = $db->select_fields('seasons', 'season,episodes', array('series_id' => $show->id));
		$show->num_seasons = count($show->seasons);
		if ( $show->active && $show->seasons ) {
			$episodes = $db->select_one('seasons', 'episodes', array('series_id' => $show->id, 'season' => (int)$show->current_season));
			if ( $episodes ) {
				$thisSeasonsEpisodes = ' title="Season '.(int)$show->current_season.' has '.$episodes.' episodes"';
			}
		}
	}

	echo '<tr class="'.implode(' ', $classes).'" showid="'.$show->id.'">'."\n\t";
	echo '<td class="tvdb"><a href="?updateshow='.$show->id.'"><img src="tv.png" /></a></td>'."\n";
	echo '<td><a id="show-name-'.$show->id.'"'.( $show->url ? ' href="'.$show->url.'"' : '' ).' style="color:'.( '1' === $show->active ? 'green' : 'red' ).';">'.$show->name.'</a> (<a href="#" onclick="return changeValue(this.parentNode.firstChild,'.$show->id.',\'name\');">e</a>)</td>';
	echo '<td class="oc"><a'.$thisSeasonsEpisodes.' href="#" onclick="return changeValue(this,'.$show->id.',\'next_episode\');">'.( trim($show->next_episode) ? str_replace(' ', '&nbsp;', $show->next_episode) : '&nbsp;' ).'</a></td>';
	echo '<td class="oc"><a href="#" onclick="return changeValue(this,'.$show->id.',\'missed\');">'.( trim($show->missed) ? trim($show->missed) : '&nbsp;' ).'</a></td>';
	echo '<td align=center>'.( $show->seasons ? '<a title="Total episodes: '.array_sum($show->seasons)."\n\n".'Click to reset seasons/episodes list" href="?resetshow='.$show->id.'" onclick="return confirm(\'Want to delete all tvdb data for this show?\');">'.$show->num_seasons.'</a>' : '' ).'</td>';
	echo '<td class="icon"><a href="?id='.$show->id.'&active='.( $show->active ? '0' : '1' ).'"><img style="border:0;" src="'.( $show->active ? 'yes' : 'no' ).'.gif" /></a></td>';
//	echo '<td class="icon"><a href="?delete='.$show->id.'"><img style="border:0;" src="cross.png" /></a></td>';
	echo '<td class="icon">'.( $show->watching ? '' : '<a href="?watching='.$show->id.'"><img src="arrow_right.png" /></a>' ).'</td>';
	echo '</tr>'."\n";
}

?>
</tbody>
</table>

<br />

<form method="post">
	<fieldset style="display: inline-block;">
		<legend>Add show <?=count($series)+1?></legend>
		<p>Name: <input type="text" name="name" /></p>
		<p><input type="submit" value="Save" /><p>
	</fieldset>
</form>

<br />
<br />

<script src="http://hotblocks.nl/js/mootools_1_11.js"></script>
<script>
function changeName(id, name) {
	new Ajax('?', {
		data : 'id='+id+'&name='+encodeURIComponent(name),
		onComplete : function(t) {
			$('show-name-'+id).html(t)
		}
	}).request();
	return false;
}
function changeValue(o, id, n, v) {
	v == undefined && (v = o.html())
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
	o.html('<img src="spinner.gif" />');
	new Ajax('?', {
		data : d,
		onComplete : function(t) {
			o.html(t);
		}
	}).request();
	return false;
}
var g_order;
function saveOrder(s) {
	var order = $$$('tbody.sortable > tr').map(function(tr) {
		return tr.attr('showid');
	}).join(',');
	if ( s || order === g_order ) {
		$$('.hilited').removeClass('hilited');
		g_order = order;
		return;
	}

	// ajax start
	$('series').addClass('loading');

	new Ajax('?', {
		data: 'order=' + order,
		onComplete: function(t) {
//			alert(t);
			$$('.hilited').removeClass('hilited');
			g_order = order;

			// ajax end
			$('series').removeClass('loading');
		}
	}).request();
}
$(function() {
	$$('td.oc a').addEvents({
		'contextmenu': function(e) {
			e = new Event(e).stop();
			e.target.addClass('eligable');
		},
		'mouseleave': function(e) {
			e.target.removeClass('eligable');
		},
		'mousewheel': function(e) {
			e = new Event(e);
			if ( e.target.hasClass('eligable') && e.wheel != 0 ) {
				e.stop();
				doAndRespond(e.target, 'id=' + e.target.parent('tr').attr('showid') + '&dir=' + e.wheel);
			}
		}
	});
	new Sortables($$('.sortable')[0], {
		ghost: false,
		onStart: function(elmt) {
			elmt.addClass('hilited');
		},
		onComplete: function(elmt) {
			saveOrder();
		}
	});
	saveOrder(1);
});
</script>
</body>

</html>
