<?php

use rdx\series\Season;
use rdx\series\Show;

require 'inc.bootstrap.php';

is_logged_in(true);

if ( isset($_POST['add_season']) ) {
	$show = Show::find($_POST['add_season']);
	$seasons = array_keys($show->seasons);
	$max = $seasons ? max($seasons) : 0;
	Season::insert([
		'series_id' => $show->id,
		'season' => $max + 1,
		'edited' => 1,
	]);

	echo 'OK';
	exit;
}

if ( isset($_POST['episodes']) ) {
	header('Content-type: text/plain; charset=utf-8');

	$db->begin();
	foreach ( $_POST['episodes'] as $sid => $seasons ) {
		foreach ( $seasons as $season => $eps ) {
			$db->update('seasons', ['episodes' => $eps, 'edited' => 1], ['series_id' => $sid, 'season' => $season]);
		}
	}
	$db->commit();

	do_redirect('seasons');
	exit;
}

$series = $db->select('series', "1 ORDER BY LOWER(IF('the ' = LOWER(substr(name, 1, 4)), SUBSTR(name, 5), name)) ASC", 'Show');
$seasons = $db->select('seasons', '1 ORDER BY season')->all();

?>
<style>
table {
	border-spacing: 0;
}
tr:first-child:not(.open) ~ tr {
	display: none;
}
tr.active {
	font-weight: bold;
	background: #eee;
}
</style>

<h1>Seasons</h1>

<form accept method="post">
	<table border="1">
		<? foreach ($series as $show): ?>
			<tbody data-showid="<?= $show->id ?>">
				<tr>
					<th colspan="3">
						<a href="#" onclick="return toggleShow(this)"><?= html($show->name) ?></a>
						&nbsp;
						<a href="#" onclick="return addSeason(this)">+</a>
					</th>
				</tr>
				<? foreach ($seasons as $season): if ($season->series_id == $show->id): ?>
					<tr class="<? if ($season->season == (int) $show->next_episode): ?>active<? endif ?>">
						<td><?= $season->season ?></td>
						<td><input onchange="this.name=this.dataset.name" data-name="episodes[<?= $show->id ?>][<?= $season->season ?>]" value="<?= $season->episodes ?>" size="2" /></td>
						<td><?= date('M Y', strtotime($season->runs_from)) . ' - ' . date('M Y', strtotime($season->runs_to)) ?></td>
					</tr>
				<? endif; endforeach ?>
			</tbody>
		<? endforeach ?>
	</table>
	<p><button>Save</button></p>
</form>

<script>
function toggleShow(a) {
	var tr = a.closest('tr');
	tr.classList.toggle('open');
	return false;
}

function addSeason(a) {
	var tbody = a.closest('tbody');
	var id = tbody.dataset.showid;
	var xhr = new XMLHttpRequest;
	xhr.open('post', '?', true);
	xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	xhr.onload = function(e) {
		if (this.responseText === 'OK') {
			location.reload();
		}
		else {
			alert(this.responseText);
		}
	};
	xhr.send('add_season=' + id);
	return false;
}
</script>

<details>
	<summary><?= count($db->queries) ?> queries</summary>
	<ol><li><?= implode('</li><li>', $db->queries) ?></li></ol>
</details>
