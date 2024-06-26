<?php

use rdx\series\Config;

require 'inc.bootstrap.php';

is_logged_in(true);

$options = Config::$options;

if ( isset($_POST['cfg']) ) {
	$values = array();
	foreach ( $_POST['options'] AS $name ) {
		$value = @$_POST['cfg'][$name];

		$el = $options[$name];
		$modifier = 'el_' . $el['type'] . '_value_post';

		$values[$name] = $modifier($value, $el);
	}

	$db->begin();
	$db->delete('variables', '1');
	foreach ( $values AS $name => $value ) {
		$db->insert('variables', compact('name', 'value'));
	}
	$db->commit();

	header('Location: config.php');
	exit;
}

$total = $db->count('series', '1=1');
$active = $db->count('series', 'active = ?', array(1));
$watching = $db->count('series', 'watching >= ?', array(1));
$withRemote = $db->count('series', "{$remote->field} <> '0'");
$deleted = $db->count('series', 'deleted = ?', array(1));

?>
<style>
label { font-weight: bold; display: block; }
</style>

<h1>Config</h1>

<form method="post" action>
	<? foreach ($options as $name => $el):
		$modifier = 'el_' . $el['type'] . '_value_pre';
		$value = $modifier($cfg->$name);
		?>
		<p>
			<input type="hidden" name="options[]" value="<?= $name ?>" />
			<label for="cfg_<?= $name ?>"><?= html($el['title']) ?></label>
			<input id="cfg_<?= $name ?>" <?= $value ?> name="cfg[<?= $name ?>]" type="<?= $el['type'] ?>" <?= attributes($el, array('type', 'default', 'title')) ?> />
		</p>

	<? endforeach ?>

	<p><input type="submit" /></p>
</form>

<h2>Stats</h2>

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
