
<form id="add-show" method="post" action>
	<? if (@$linkingShow): ?>
		<input type="hidden" name="id" value="<?= $linkingShow->id ?>" />
	<? endif ?>

	<fieldset style="display: inline-block">
		<legend><?= @$linkingShow ? 'Link show' : 'Add show' ?></legend>
		<p>Name: <input id="showname" type="search" name="name" value="<?= (string)@$_POST['name'] ?>" /></p>
		<p>The TVDB id: <input id="add_tvdb_series_id" type="search" name="tvdb_series_id" value="<?= (string)@$_POST['tvdb_series_id'] ?>" /></p>

		<p>
			<button name="_action" value="search" class="submit">Search</button>
			<button name="_action" value="save">Save</button>
		</p>

		<?if (@$adding_show_tvdb_result):?>
			<script>window.on('load', function() { scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight); });</script>

			<?if (!is_scalar($adding_show_tvdb_result)):?>
				<div class="search-results">
					<ul>
						<?foreach ($adding_show_tvdb_result->Series AS $show):
							$exists = $db->count('series', array('tvdb_series_id' => $show->seriesid, 'user_id' => USER_ID));
							?>
							<li class="<?= $exists ? 'exists' : '' ?>">
								<a class="tvdb-search-result" title="<?= html($show->Overview) ?>" data-id="<?= $show->seriesid ?>" data-name="<?= html($show->SeriesName) ?>" href="#<?= $show->seriesid ?>"><?= html($show->SeriesName) ?></a>
								(<?= date('Y', strtotime((string)$show->FirstAired)) ?>)
								<?if ($exists):?>(<strong>you have this</strong>)<?endif?>
								(<a target="_blank" href="http://www.thetvdb.com/?tab=series&id=<?= $show->seriesid ?>">=&gt;</a>)
								<div class="tvdb-search-result-description"><?= html($show->Overview) ?></div>
							</li>
						<?endforeach?>
					</ul>
				</div>
			<?endif?>
		<?endif?>
	</fieldset>
</form>

<script>
$('add-show').on('submit', function(e) {
	e.preventDefault();

	var f = this,
		action = this.data('action') || '';
	f.getElements('button').attr('disabled', 1);

	var data = new FormData(f);
	data.append('_action', action);
	$.post(f.action, data).on('success', function(e, html) {
		if ( html.substr(0, 2) === 'OK' ) {
			var id = html.substr(2);
			document.cookie = 'series_hilited=' + id;
			scrollTo(0, 0);
			location.reload();
		}
		else {
			$('add-show-wrapper').setHTML(html);
			try {
				$('.search-results a', 1).focus();
			}
			catch (ex ) {
				$('showname').select();
			}
		}
	}).on('done', function(e) {
		f.getElements('button').attr('disabled', null);
	});
}).getElements('button').on('click', function(e) {
	this.form.data('action', this.value);
});

$$('a.tvdb-search-result').on('click', function(e) {
	e.preventDefault();

	var id = this.data('id'),
		name = this.data('name');
	$('showname').value = name;
	$('add_tvdb_series_id').value = id;
});
</script>
