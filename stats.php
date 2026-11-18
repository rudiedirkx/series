<?php

require 'inc.bootstrap.php';

is_logged_in(true);

$total = db()->count('series', '1=1');
$active = db()->count('series', 'active = ?', array(1));
$watching = db()->count('series', 'watching >= ?', array(1));
$withRemote = db()->count('series', remote()->field . " <> '0'");
$deleted = db()->count('series', 'deleted = ?', array(1));

?>
<h1>Stats</h1>

<table>
	<tr>
		<th align="right">Total</th>
		<td>:</td>
		<td><?= $total ?></td>
	</tr>
	<tr>
		<th align="right">Active</th>
		<td>:</td>
		<td><?= $active ?></td>
		<td></td>
		<td>(<?= round($active / $total * 100) ?> %)</td>
	</tr>
	<tr>
		<th align="right">Watching</th>
		<td>:</td>
		<td><?= $watching ?></td>
	</tr>
	<tr>
		<th align="right">With Remote ID</th>
		<td>:</td>
		<td><?= $withRemote ?></td>
		<td></td>
		<td>(<?= round($withRemote / $total * 100) ?> %, <?= $total - $withRemote ?> without)</td>
	</tr>
	<tr>
		<th align="right">Deleted</th>
		<td>:</td>
		<td><?= $deleted ?></td>
		<td></td>
		<td>(<?= round($deleted / $total * 100) ?> %)</td>
	</tr>
</table>
