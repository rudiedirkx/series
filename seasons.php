<?php

require 'inc.bootstrap.php';

is_logged_in(true);

if ( isset($_POST['seasons'], $_POST['episodes']) ) {
	header('Content-type: text/plain; charset=utf-8');

	$db->begin();
	foreach ( $_POST['episodes'] as $sid => $seasons ) {
		foreach ( $seasons as $season => $eps ) {
			$db->update('seasons', ['episodes' => $eps], ['series_id' => $sid, 'season' => $season]);
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
			<tbody>
				<tr>
					<th colspan="3"><a href="#" onclick="return toggleShow(this)"><?= html($show->name) ?></a></th>
				</tr>
				<? foreach ($seasons as $season): if ($season->series_id == $show->id): ?>
					<tr class="<? if ($season->season == (int) $show->next_episode): ?>active<? endif ?>">
						<td><?= $season->season ?></td>
						<td><input name="episodes[<?= $show->id ?>][<?= $season->season ?>]" value="<?= $season->episodes ?>" size="2" /></td>
						<td><?= date('M Y', strtotime($season->runs_from)) . ' - ' . date('M Y', strtotime($season->runs_to)) ?></td>
					</tr>
				<? endif; endforeach ?>
				<!-- <tr>
					<td><input name="seasons[<?= $show->id ?>]" size="2" /></td>
					<td><input name="episodes[<?= $show->id ?>][0]" size="2" /></td>
					<td></td>
				</tr> -->
			</tbody>
		<? endforeach ?>
	</table>
	<p><button>Save</button></p>
</form>

<script>
function toggleShow(a) {
	var tr = a.parentNode.parentNode;
	tr.classList.toggle('open');
	return false;
}
</script>
