<?php

require 'inc.bootstrap.php';

$options = Config::$options;
$cfg = new Config;

if ( isset($_POST['cfg']) ) {
	$values = array();
	foreach ( $_POST['options'] AS $name ) {
		$value = @$_POST['cfg'][$name];

		$el = Config::$options[$name];
		$modifier = 'el_' . $el['type'] . '_value_post';

		$values[$name] = $modifier($value, $el);
	}

	$db->delete('variables', '1');
	foreach ( $values AS $name => $value ) {
		$db->insert('variables', compact('name', 'value'));
	}

	header('Location: config.php');
	exit;
}

?>
<style>
label { font-weight: bold; display: block; }
</style>

<form method="post" action>
	<? foreach ($options as $name => $el):
		$modifier = 'el_' . $el['type'] . '_value_pre';
		$value = $modifier($cfg->$name);
		?>
		<p>
			<input type="hidden" name="options[]" value="<?= $name ?>" />
			<label for="cfg_<?= $name ?>"><?= $el['title'] ?></label>
			<input id="cfg_<?= $name ?>" <?= $value ?> name="cfg[<?= $name ?>]" type="<?= $el['type'] ?>" <?= attributes($el, array('type', 'default')) ?> />
		</p>

	<? endforeach ?>

	<p><input type="submit" /></p>
</form>


