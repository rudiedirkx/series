<?php

define('TVDB_API_KEY', '94F0BD0D5948FE69');
define('TVDB_DVD_OVER_TV', true);

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
require 'inc.show.php';

// Define env vars
define('AJAX', strtolower(@$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
define('MOBILE', is_int(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'mobile')));

// Get and reset highlighted show
$hilited = (int)@$_GET['series_hilited'] ?: (int)@$_COOKIE['series_hilited'];
if ( $hilited ) {
	setcookie('series_hilited', '', 1);
}



$async = MOBILE && $cfg->async_inactive;
$skip = $cfg->dont_load_inactive;



// Link a show to TVDB, pt II
if ( isset($_POST['id'], $_POST['name'], $_POST['tvdb_series_id'], $_POST['_action']) && $_POST['_action'] == 'save' ) {
	$id = $_POST['id'];
	$db->update('series', array(
		'name' => $_POST['name'],
		'tvdb_series_id' => $_POST['tvdb_series_id'] ?: 0,
		'changed' => time(),
	), compact('id'));

	if ( $_POST['tvdb_series_id'] ) {
		if ( $show = Show::get($id) ) {
			$show->updateTVDB();
		}
	}

	exit('OK' . $id);
}

// New show
else if ( isset($_POST['name'], $_POST['tvdb_series_id']) ) {
	$action = @$_POST['_action'] ?: 'search';

	$name = trim($_POST['name']);
	if ( !$name ) {
		$action = 'search';
	}

	if ( isset($_POST['id']) ) {
		$linkingShow = Show::get($_POST['id']);
	}

	$insert = array(
		'name' => $name,
		'tvdb_series_id' => $_POST['tvdb_series_id'] ?: 0,
		'deleted' => 0,
		'active' => 1,
		'watching' => 0,
		'created' => time(),
	);

	// Search
	if ( $action == 'search' ) {
		$adding_show_tvdb_result = null;
		if ( $name ) {
			$url = 'http://www.thetvdb.com/api/GetSeries.php?seriesname=' . urlencode($name);
			$adding_show_tvdb_result = simplexml_load_file($url);
		}
		require 'tpl.add-show.php';
		exit;
	}

	// Save
	$db->insert('series', $insert);
	$id = $db->insert_id();

	if ( $show = Show::get($id) ) {
		$show->updateTVDB();
	}

	exit('OK' . $id);
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
		$ne = $show->next_episode ?: '1.0';

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
					$E = $show->seasons[$S]->episodes;
				}
			}
			// Moving up
			else if ( isset($show->seasons[$S]) && $E > $show->seasons[$S]->episodes ) {
				$S += 1;
				$E = 1;
			}

			// Add "0" padding
			$E = str_pad($E, 2, '0', STR_PAD_LEFT);

			// More detailed feedback
			$season = $db->select('seasons', array('series_id' => $show->id, 'season' => $S))->first();
			$episodes = $season ? $season->episodes : 0;

			$season_from = $season_to = '';
			if ( $season && $season->runs_from && $season->runs_to ) {
				$season_from = date('M Y', strtotime($season->runs_from));
				$season_to = date('M Y', strtotime($season->runs_to));
			}
		}

		// Save
		$ne = implode('.', $parts);
		$update = array('next_episode' => $ne, 'changed' => time());
		$db->update('series', $update, array('id' => $show->id));

		// respond
		header('Content-type: text/json');
		exit(json_encode(array(
			'next_episode' => $ne,
			'season' => $S,
			'episodes' => (int)$episodes,
			'season_from' => $season_from,
			'season_to' => $season_to,
			'runtime' => round((microtime(1) - REQUEST_MICROTIME) * 1000, 3),
		)));
	}

	exit('W00t!?');
}

// Edit field: next
else if ( isset($_POST['id'], $_POST['next_episode']) ) {
	$db->update('series', array('next_episode' => $_POST['next_episode'], 'changed' => time()), array('id' => $_POST['id']));

	exit($db->select_one('series', 'next_episode', array('id' => $_POST['id'])));
}

// Edit field: missed
else if ( isset($_POST['id'], $_POST['missed']) ) {
	$db->update('series', array('missed' => $_POST['missed'], 'changed' => time()), array('id' => $_POST['id']));

	exit($db->select_one('series', 'missed', array('id' => $_POST['id'])));
}

// Edit field: name
else if ( isset($_POST['id'], $_POST['name']) ) {
	$db->update('series', array('name' => $_POST['name'], 'changed' => time()), array('id' => $_POST['id']));

	exit($db->select_one('series', 'name', array('id' => $_POST['id'])));
}

// Toggle active status
else if ( isset($_GET['id'], $_GET['active']) ) {
	$id = $_GET['id'];
	$active = (bool)$_GET['active'];

	$update = array('active' => $active, 'changed' => time());
	if ( !$active ) {
		$update['watching'] = false;
	}

	$db->update('series', $update, compact('id'));

	setcookie('series_hilited', $id);

	header('Location: ./');
	exit;
}

// Delete show
else if ( isset($_GET['delete']) ) {
	$db->update('series', 'deleted = 1', array('id' => $_GET['delete']));

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

	setcookie('series_hilited', $_GET['watching']);

	header('Location: ./');
	exit;
}

// Link a show to TVDB, pt I
else if ( isset($_GET['linkshow']) ) {
	$id = (int)$_GET['linkshow'];

	if ( $linkingShow = Show::get($id) ) {
		if ( !$linkingShow->tvdb_series_id ) {
			$name = $_POST['name'] = $linkingShow->name;
			$url = 'http://www.thetvdb.com/api/GetSeries.php?seriesname=' . urlencode($name);
			$adding_show_tvdb_result = simplexml_load_file($url);
			require 'tpl.add-show.php';
			exit;
		}
	}

	exit('Some error. Whatever.');
}

// Update one show
else if ( isset($_GET['updateshow']) ) {
	$id = (int)$_GET['updateshow'];

	$rsp = "Invalid id/show";
	if ( $show = Show::get($id) ) {
		$success = $show->updateTVDB();
		if ( $success === true ) {
			$rsp = 'OK';
		}
		else if ( $success === false ) {
			$rsp = 'Something failed. TVDB gone?';
		}
	}

	if ( AJAX ) {
		setcookie('series_hilited', $id);
		echo $rsp;
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
	$db->update('series', array('tvdb_series_id' => 0, 'changed' => time()), array('id' => $_GET['resetshow']));

	header('Location: ./');
	exit;
}

// keep db hot
else if ( isset($_GET['keepalive']) ) {
	$db->delete('variables', array('name' => 'keepalive'));
	$db->insert('variables', array('name' => 'keepalive', 'value' => time()));
	exit('OK');
}

// lazy/async load inactive shows
else if ( isset($_GET['inactive']) ) {
	require 'tpl.shows.php';
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
	<style><?php require 'series.css' ?></style>
</head>

<body>

<img id="banner" />

<table id="series">
	<thead>
		<tr class="hd" bgcolor="#bbbbbb">
			<th class="tvdb"></th>
			<th>Name <a href="javascript:$('showname').focus();void(0);">+</a></th>
			<? if ($cfg->banners): ?>
				<th class="picture"></th>
			<? endif ?>
			<th class="next">Nxt</th>
			<th class="info"></th>
			<th class="missed">Not</th>
			<th class="seasons" title="Existing seasons">S</th>
			<th class="icon" colspan="2"></th>
		</tr>
	</thead>
	<tbody>
		<?php require 'tpl.shows.php' ?>
	</tbody>
	<? if ($cfg->search_inactives && ($async || $skip)): ?>
		<?php require 'tpl.search.php' ?>
	<? endif ?>
</table>

<script src="rjs.js"></script>

<div id="add-show-wrapper">
	<?php require 'tpl.add-show.php' ?>
</div>

<script>
var timer = 0;

<? if ($async || $skip): ?>
	function startLazyLoad(delay) {
		var $series = $('series');
		var $loadingMore = document.el('tbody').attr('id', 'loading-more').addClass('loading-more').setHTML('<tr><td colspan="9">&nbsp;</td></tr>').inject($series);
		setTimeout(function() {
			$loadingMore.addClass('loading');
			$.get('?inactive=1&series_hilited=<?= $hilited ?>').on('done', function(e, html) {
				$loadingMore.remove();
				document.el('tbody', {"id": 'shows-inactive'}).setHTML(html).inject($series);
				document.body.addClass('show-all');
			});
		}, delay || 1);
	}
	<? if ($skip): ?>
		var $series = $('series');
		var $loadMore = document.el('tbody').attr('id', 'load-more').addClass('load-more').setHTML('<tr><td colspan="9"><a href>Load the rest</a></td></tr>').inject($series);
		$loadMore.getElement('a').on('click', function(e) {
			e.preventDefault();

			$loadMore.remove();
			startLazyLoad();
		});
	<? else: ?>
		window.on('load', function(e) {
			startLazyLoad(2000);
		});
	<? endif ?>
<? else: ?>
	document.body.addClass('show-all');
<? endif ?>

function RorA(t, fn) {
	fn || (fn = function() {
		location.reload();
	});
	if ( t == 'OK' ) {
		return fn() || true;
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
	var id = '[' + ++timer + '] Some Ajax';
	console.time && console.time(id)

	o.setHTML('<img src="spinner.gif" />');
	$.post('', d).on('done', function(e, rsp) {
		if ( typeof rsp == 'string' ) {
			return o.setHTML(rsp);
		}

		o.setHTML(rsp.next_episode);
		if ( rsp.season && rsp.episodes ) {
			var title = 'Season ' + rsp.season + ' has ' + rsp.episodes + ' episodes. ';
			if ( rsp.season_from && rsp.season_to ) {
				title += 'It ran from ' + rsp.season_from + ' to ' + rsp.season_to + '. ';
			}
			o.attr('title', title.trim());
		}
		console.timeEnd && console.timeEnd(id)
	});
	return false;
}

<? if ($cfg->search_inactives): ?>
	document.on('keydown', function(e) {
		if ( document.activeElement == document.body ) {
			if ( e.which == 191 ) {
				e.preventDefault();
				try {
					$('load-more').getElement('a').click();
				}
				catch (ex) {
					$('search').focus();
				}
			}
		}
	});
	var trs;
	$('search')
		.on('search', function(e) {
			if ( !trs ) {
				trs = new Elements($('shows-inactive').rows);
				trs.forEach(function(tr) {
					var span = tr.getElement('.show-name');
					tr._txt = (span.textContent + ' ' + (span.title || '')).toLowerCase();
				});
			}

			var q = this.value.trim().toLowerCase();
			if ( !q ) {
				trs.removeClass('filtered-out');
			}
			else {
				trs.forEach(function(tr) {
					var match = tr._txt.indexOf(q) != -1,
						method = match ? 'removeClass' : 'addClass';
					tr[method]('filtered-out');
				});
			}
		})
		.on('keyup', function(e) {
			if ( this.value != this.$lastValue ) {
				this.$lastValue = this.value;
				this.fire('search', e);
			}
		})
	;
<? endif ?>

var lastAlive = Date.now();
document.on('mousemove', function(e) {
	if ( Date.now() - lastAlive > 60000 ) {
		lastAlive = Date.now();

		console.time('Keep-alive');
		$.post('?keepalive').on('done', function() {
			console.timeEnd('Keep-alive');
		});
	}
});

$('series')
	.on('click', '.info > a', function(e) {
		e.preventDefault();
		var tr = this.ancestor('tr');
		var next = tr.getElement('.next > a');
		var seasons = tr.getElement('.seasons > a');
		alert(next.title + "\n\n" + seasons.title.split('\n\n')[0]);
	})
	.on('contextmenu', '.next.oc a', function(e) {
		e.preventDefault();
		this.addClass('eligible');
	})
	.on('keydown', '.next.oc a', function(e) {
		var space = e.key == Event.Keys.space,
			esc = e.key == Event.Keys.esc,
			up = e.key == Event.Keys.up,
			down = e.key == Event.Keys.down;
		if ( space ) {
			e.preventDefault();
			this.toggleClass('eligible');
		}
		else if ( esc ) {
			this.blur();
		}
		else if ( up || down ) {
			if ( this.hasClass('eligible') ) {
				e.preventDefault();
				var direction = up ? 1 : -1;
				doAndRespond(this, 'id=' + this.ancestor('tr').attr('showid') + '&dir=' + direction);
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
			doAndRespond(this, 'id=' + this.ancestor('tr').attr('showid') + '&dir=' + direction);
		}
	})
	.on('mouseover', 'tr[data-banner] .show-banner', function(e) {
		var src = 'http://thetvdb.com/banners/' + this.ancestor('tr').data('banner');
		$('banner').attr('src', src).show();

		this.on('mouseout', this._onmouseout = function(e) {
			$('banner').hide();

			this.off('mouseout', this._onmouseout);
		});
	})
	.on('click', 'td.tvdb > a', function(e) {
		e.preventDefault();
		var $img = this.getElement('img'),
			src = $img.attr('src');
		$img.attr('src', 'loading16.gif');

		var handler = this.hasClass('update') ? function(e) {
			var t = this.responseText;
			if ( !RorA(t) ) {
				$img.attr('src', src);
			}
		} : function(e) {
			$img.attr('src', src);

			var html = this.responseText;
			if ( '<' == html.trim()[0] ) {
				$('add-show-wrapper').setHTML(html);
				$('showname').select();
			}
		};
		$.post(this.attr('href')).on('done', handler);
	})
;
</script>

</body>

<!-- <?= count($db->queries) ?> queries -->
<!-- <? print_r($db->queries) ?> -->

</html>


