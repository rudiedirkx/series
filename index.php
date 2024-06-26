<?php

use rdx\series\Show;

require 'inc.bootstrap.php';

is_logged_in(true);

// Get and reset highlighted show
$hilited = (int) ($_GET['series_hilited'] ?? $_COOKIE['series_hilited'] ?? 0);
if ( isset($_COOKIE['series_hilited']) ) {
	setcookie('series_hilited', '', 1);
}



$async = MOBILE && $cfg->async_inactive;
$skip = $cfg->dont_load_inactive;



// Link a show to TVDB, pt II
if ( isset($_POST['id'], $_POST['name'], $_POST[$remote->field], $_POST['_action']) && $_POST['_action'] == 'save' ) {
	if ( $show = Show::find($_POST['id']) ) {
		$show->update(array(
			'name' => $_POST['name'],
			$remote->field => $_POST[$remote->field] ?: 0,
			'changed' => time(),
		));

		if ( $_POST[$remote->field] ) {
			$show->updateRemote();
		}
	}

	exit('OK' . $_POST['id']);
}

// New show
elseif ( isset($_POST['name'], $_POST[$remote->field]) ) {
	$action = @$_POST['_action'] ?: 'search';

	$name = trim($_POST['name']);
	if ( !$name ) {
		$action = 'search';
	}

	if ( isset($_POST['id']) ) {
		$linkingShow = Show::find($_POST['id']);
	}

	// Search
	if ( $action == 'search' ) {
		$remote->init();
		$remote->search($name);
		require 'tpl.add-show.php';
		exit;
	}

	// Save
	$show = Show::create([
		'user_id' => USER_ID,
		'name' => $name,
		$remote->field => $_POST[$remote->field] ?? null,
		'deleted' => 0,
		'active' => 1,
		'watching' => 0,
		'created' => time(),
	]);
	$show->updateRemote();

	exit('OK' . $show->id);
}

// Edit scrollable field: next
elseif ( isset($_POST['id'], $_POST['dir']) ) {
	if ( $show = Show::find($_POST['id']) ) {
		if ( 0 != (int)$_POST['dir'] ) {
			$delta = $_POST['dir'] < 0 ? -1 : 1;

			// fetch show
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
				elseif ( isset($show->seasons[$S]) && $E > $show->seasons[$S]->episodes ) {
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
			$show->update(array('next_episode' => $ne, 'changed' => time()));

			// respond
			header('Content-type: text/json');
			exit(json_encode($show->getNextEpisodeSummary() + array(
				'runtime' => round((microtime(1) - REQUEST_MICROTIME) * 1000, 3),
			)));
		}
	}

	exit('W00t!?');
}

// Edit field: next
elseif ( isset($_POST['id'], $_POST['next_episode']) ) {
	if ( $show = Show::find($_POST['id']) ) {
		$show->update(array(
			'next_episode' => $_POST['next_episode'],
			'changed' => time(),
		));

		header('Content-type: text/json');
		exit(json_encode($show->getNextEpisodeSummary() + array(
			'runtime' => round((microtime(1) - REQUEST_MICROTIME) * 1000, 3),
		)));
	}

	exit($_POST['next_episode']);
}

// Edit field: name
elseif ( isset($_POST['id'], $_POST['name']) ) {
	if ( $show = Show::find($_POST['id']) ) {
		$show->update(array('name' => $_POST['name'], 'changed' => time()));
	}

	exit($_POST['name']);
}

// Toggle active status
elseif ( isset($_GET['id'], $_GET['active']) ) {
	if ( $show = Show::find($_GET['id']) ) {
		$active = (bool)$_GET['active'];

		$update = array('active' => $active, 'changed' => time());
		if ( !$active ) {
			$update['watching'] = false;
		}

		$show->update($update);

		setcookie('series_hilited', $show->id);
	}

	return do_redirect('index');
}

// Delete show
elseif ( isset($_GET['delete']) ) {
	if ( $show = Show::find($_GET['delete']) ) {
		$show->update(array('deleted' => 1, 'changed' => time()));
	}

	if ( AJAX ) {
		exit('OK');
	}

	return do_redirect('index');
}

// Undelete show
elseif ( isset($_GET['undelete']) ) {
	if ( $show = Show::find($_GET['undelete']) ) {
		$show->update(array('deleted' => 0, 'changed' => time()));

		setcookie('series_hilited', $show->id);
	}

	if ( AJAX ) {
		exit('OK');
	}

	return do_redirect('index');
}

// Set current/watching show
elseif ( isset($_GET['watching']) ) {
	if ( $show = Show::find($_GET['watching']) ) {

		// Toggle selected
		if ( $cfg->max_watching > 1 ) {

			// Unwatch
			if ( $show->watching ) {
				$show->update(array('watching' => 0, 'changed' => time()));
			}
			// Watch
			else {
				$maxWatching = $db->select_one('series', 'MAX(watching)', array('user_id' => USER_ID));
				$show->update(array('watching' => $maxWatching + 1, 'active' => 1, 'changed' => time()));

				// Only allow $cfg->max_watching shows to have watching > 1
				$allWatching = $db->select_fields('series', 'id, watching', 'watching > 0 AND user_id = ? ORDER BY watching DESC, id DESC', USER_ID);
				$allWatching = array_keys($allWatching);
				$illegallyWatching = array_slice($allWatching, $cfg->max_watching);
				count($illegallyWatching) and $db->update('series', array('watching' => 0, 'changed' => time()), array('id' => $illegallyWatching, 'user_id' => USER_ID));
			}
		}
		// Only selected (no toggle, just ON)
		else {
			$db->update('series', array('watching' => 0, 'changed' => time()), array('user_id' => USER_ID));
			$show->update(array('watching' => 0, 'changed' => time()));
		}

		setcookie('series_hilited', $show->id);
	}

	return do_redirect('index');
}

// Link a show to TVDB, pt I
elseif ( isset($_GET['linkshow']) ) {
	$id = (int)$_GET['linkshow'];

	if ( $linkingShow = Show::find($id) ) {
		if ( !$linkingShow->{$remote->field} ) {
			$name = $_POST['name'] = $linkingShow->name;

			$remote->init();
			$remote->search($name);
			require 'tpl.add-show.php';
			exit;
		}
	}

	exit('Some error. Whatever.');
}

// Update one show
elseif ( isset($_GET['updateshow']) ) {
	$id = (int)$_GET['updateshow'];

	$rsp = "Invalid id/show";
	if ( $show = Show::find($id) ) {
		$success = $show->updateRemote();
		if ( $success === true ) {
			$rsp = 'OK';
			setcookie('series_hilited', $id);
		}
		elseif ( $success === false ) {
			$rsp = 'Something failed. TVDB gone?';
		}
	}

	if ( AJAX ) {
		exit($rsp);
	}

	return do_redirect('index');
}

// reset one show
elseif ( isset($_GET['resetshow']) ) {
	if ( $show = Show::find($_GET['resetshow']) ) {
		// delete seasons/episodes
		$db->delete('seasons', ['series_id' => $show->id]);

		$show->update([
			$remote->field => null,
			'changed' => time(),
		]);
	}

	return do_redirect('index');
}

// keep db hot
elseif ( isset($_GET['keepalive']) ) {
	$db->delete('variables', array('name' => 'keepalive'));
	$db->insert('variables', array('name' => 'keepalive', 'value' => time()));
	exit('OK');
}

// search inactive
elseif ( isset($_GET['search']) ) {
	$search = trim($_GET['search']);
	$series = strlen($search) ? Show::all("
		user_id = ? AND id <> ? AND active = '0' AND name LIKE ?
		ORDER BY LOWER(REGEXP_REPLACE('^(the|a) ', '', name)) ASC
	", [USER_ID, $hilited, "%$search%"]) : [];
	Show::eager('seasons', $series);

	require 'tpl.shows.php';
	exit;
}

$series = Show::all("
	user_id = ? AND (active = '1' OR id = ?)
	ORDER BY active DESC, (watching <> 0) DESC, LOWER(REGEXP_REPLACE('^(the|a) ', '', name)) ASC
", [USER_ID, $hilited]);
Show::eager('seasons', $series);

?>
<!doctype html>
<html>

<head>
	<meta charset="utf-8" />
	<link rel="icon" type="image/png" href="favicon-128.png" sizes="128x128" />
	<link rel="icon" href="favicon.ico" type="image/x-icon" />
	<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Series</title>
	<link rel="stylesheet" href="<?= html_asset('series.css') ?>" />
</head>

<body>

<img id="banner" />

<table id="series">
	<thead>
		<tr class="hd" bgcolor="#bbbbbb">
			<th class="tvdb"></th>
			<th>Name</th>
			<? if ($cfg->banners): ?>
				<th class="picture"></th>
			<? endif ?>
			<th class="imdb"></th>
			<th class="next">Nxt</th>
			<th class="info"></th>
			<th class="seasons" title="Existing seasons">S</th>
			<th class="icon active"></th>
			<th class="icon watching"></th>
		</tr>
	</thead>
	<tbody id="tb-active">
		<?php require 'tpl.shows.php' ?>
	</tbody>
	<?php require 'tpl.search.php' ?>
	<tbody id="tb-inactive"></tbody>
</table>

<script src="rjs.js"></script>

<div id="add-show-wrapper">
	<?php require 'tpl.add-show.php' ?>
</div>

<script>
var timer = 0;

<? if ($hilited): ?>
	window.on('load', function(e) {
		var el = $('show-<?= $hilited ?>');
		if (el) {
			setTimeout(function() {
				el.scrollIntoViewIfNeeded();
			}, 200);
		}
	});
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
		$('show-name-' + id).setText(t);
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

		o.setHTML(rsp.next_episode || '');
		if ( rsp.season && rsp.episodes ) {
			var title = 'Season ' + rsp.season + ' has ' + rsp.episodes + ' episodes. ';
			if ( rsp.season_from && rsp.season_to ) {
				title += 'It ran from ' + rsp.season_from + ' to ' + rsp.season_to + '. ';
			}
			o.attr('title', title.trim());
		}
		else {
			o.attr('title', '');
		}
		console.timeEnd && console.timeEnd(id)
	});
	return false;
}

document.on('keyup', function(e) {
	if ( e.originalEvent.code == 'Slash' && document.activeElement.matches('body, a') ) {
		$('search').focus();
	}
});

$('search').on('input', function(e) {
	var q = this.value.trim();
	if (q.length == 0) {
		$('tb-inactive').setHTML('');
	}
	else if (q.length >= 2) {
		console.time('search');
		fetch('?series_hilited=<?= $hilited ?>&search=' + encodeURIComponent(q)).then(x => x.text()).then(html => {
			$('tb-inactive').setHTML(html);
			console.timeEnd('search');
		});
	}
});

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
				doAndRespond(this, 'id=' + this.ancestor('tr').data('showid') + '&dir=' + direction);
			}
		}
	})
	.on('blur', '.next.oc a', function(e) {
		this.removeClass('eligible');
	})
	.on('mouseleave', '.next.oc a', function(e) {
		this.removeClass('eligible');
	})
	.on('wheel', '.next.oc a', function(e) {
		if ( this.hasClass('eligible') ) {
			e.preventDefault();
			var direction = e.originalEvent.deltaY > 0 ? -1 : 1;
			doAndRespond(this, 'id=' + this.ancestor('tr').data('showid') + '&dir=' + direction);
		}
	})
	.on('mouseover', 'tr[data-banner] .show-banner .show-name', function(e) {
		var src = this.ancestor('tr').data('banner');
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

		var handler = this.hasClass('updateshow') ? function(e) {
			var t = this.responseText;
			if ( !RorA(t) ) {
				$img.attr('src', src);
			}
		} : function(e) {
			$img.attr('src', src);

			var html = this.responseText;
			if ( '<' == html.trim()[0] ) {
				$('add-show-wrapper').setHTML(html);
				setTimeout(function() {
					$('showname').focus();
				});
			}
		};
		$.post(this.attr('href')).on('done', handler);
	})
	.on('click', 'a.ajaxify', function(e) {
		e.preventDefault();

		var $img = this.getElement('img'),
			src = $img.attr('src');
		$img.attr('src', 'loading16.gif');

		$.get(this.href).on('done', function(e, rsp) {
			$img.attr('src', src);
			RorA(rsp);
		});
	})
;
</script>

<details>
	<summary><?= count($db->queries) ?> queries</summary>
	<ol><li><?= implode('</li><li>', $db->queries) ?></li></ol>
</details>

</body>

</html>


