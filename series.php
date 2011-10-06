<?php

require_once('./../inc/db/db_sqlite.php'); // https://github.com/rudiedirkx/db_generic
$db = db_sqlite::open('./series.sqlite3');

if ( !$db || !$db->connected() ) {
	exit('<p>Que pasa, amigo!? No connecto to databaso! Si? <strong>No bueno!</strong></p>');
}

// db & table exist?
if ( !$db->table('series') ) {
	// create table `series`
	echo '<pre>';
	echo "creating table `series`...\n";
	var_dump($db->table('series', array(
		'id' => array('pk' => true),
		'name',
		'next_episode',
		'missed',
		'active',
		'url',
		'deleted',
		'o',
		'watching'
	)));
	echo "created table `series`\n";
	echo '</pre>';
}

// New show
if ( isset($_POST['name']) ) {
	$_POST['deleted'] = 0;
	$_POST['active'] = 1;
	$_POST['o'] = 0;
	header('Location: ./');
	var_dump($db->insert('series', $_POST));
	echo $db->error;
	exit;
}

// Change show order
else if ( isset($_POST['order']) ) {
	$db->begin();
	foreach ( explode(',', $_POST['order']) AS $i => $id ) {
		$db->update('series', 'o = '.(int)$i, 'id = '.(int)$id);
	}
	$db->commit();
	exit('OK');
}

// Edit scrollable field: next
else if ( isset($_POST['id'], $_POST['dir']) ) {
	if ( 0 != (int)$_POST['dir'] ) {
		$ne = $db->select_one('series', 'next_episode', 'id = '.(int)$_POST['id']);
		list($major, $minor) = explode('.', $ne);
		if ( $_POST['dir'] < 0 ) $minor--;
		else $minor++;
		$ne = $major . '.' . ( 10 > $minor ? '0' : '' ) . $minor;
		$db->update('series', "next_episode = '".$ne."'", 'id = '.(int)$_POST['id']);
	}
	exit($db->select_one('series', 'next_episode', 'id = '.(int)$_POST['id']));
}

// Edit field: next
else if ( isset($_POST['id'], $_POST['next_episode']) ) {
	$db->update('series', "next_episode = '".addslashes($_POST['next_episode'])."'", 'id = '.(int)$_POST['id']);
	exit($db->select_one('series', 'next_episode', 'id = '.(int)$_POST['id']));
}

// Edit field: missed
else if ( isset($_POST['id'], $_POST['missed']) ) {
	$db->update('series', "missed = '".addslashes($_POST['missed'])."'", 'id = '.(int)$_POST['id']);
	exit(($m=$db->select_one('series', 'missed', 'id = '.(int)$_POST['id']))?$m:'?');
}

// Toggle active status
else if ( isset($_GET['id'], $_GET['active']) ) {
	$db->update('series', "active = ".(int)(bool)$_GET['active'], 'id = '.(int)$_GET['id']);
	header('Location: ./');
	exit;
}

// Delete show
else if ( isset($_GET['delete']) ) {
	$db->update('series', 'deleted = 1', 'id = '.(int)$_GET['delete']);
	header('Location: ./');
	exit;
}

// Set current/watching show
else if ( isset($_GET['watching']) ) {
	$db->update('series', 'watching = 0', '1');
	$db->update('series', 'watching = 1', 'id = '.(int)$_GET['watching']);
	header('Location: ./');
	exit;
}

?>
<!DOCTYPE html>
<html>
<head>
<title>Series</title>
<script src="http://hotblocks.nl/js/mootools_1_11.js"></script>
<script>
function changeValue(o, id, n) {
	var nv = prompt('New value:', o.html());
	if ( null === nv ) { return false; }
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
<style>
body, table { font-family: Verdana; font-size: 14px; border-collapse: separate; border-spacing: 0; }
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
</style>
</head>

<body>
<table id="series">
<thead>
<tr class="hd" bgcolor="#bbbbbb">
	<th><a href="?name">Name</a></th>
	<th>Next</th>
	<th>Missed</th>
	<th colspan="2"></th>
</tr>
</thead>
<tbody class="sortable">
<?php

$series = $db->select('series', 'deleted = 0 ORDER BY active DESC'.( 0 and !isset($_GET['name']) ? ', o ASC' : '' ).', LOWER(IF(\'the \' == lower(substr(name, 1, 4)), substr(name, 5), name)) ASC');
echo $db->error;
foreach ( $series AS $n => $arrShow ) {
	$show = (object)$arrShow;

	$classes = array();
	$show->active && $classes[] = 'active';
	$show->watching && $classes[] = 'watching';

	echo '<tr class="'.implode(' ', $classes).'" showid="'.$show->id.'">'."\n\t";
	echo '<td><a'.( $show->url ? ' href="'.$show->url.'"' : '' ).' style="color:'.( '1' === $show->active ? 'green' : 'red' ).';">'.$show->name.'</a></td>';
	echo '<td class="oc"><a href="#" onclick="return changeValue(this,'.$show->id.',\'next_episode\');">'.( trim($show->next_episode) ? str_replace(' ', '&nbsp;', $show->next_episode) : '&nbsp;' ).'</a></td>';
	echo '<td class="oc"><a href="#" onclick="return changeValue(this,'.$show->id.',\'missed\');">'.( trim($show->missed) ? trim($show->missed) : '&nbsp;' ).'</a></td>';
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
<fieldset style="display:inline-block;">
<legend>Add show <?=count($series)+1?></legend>
Name: <input type="text" name="name" /><br />
<input type="submit" value="Save" />
</fieldset>
</form>

<br />
<br />
</body>

</html>
