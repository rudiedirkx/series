
<form id="add-show" method="post" action>
	<fieldset style="display: inline-block">
		<legend>Add show</legend>
		<p>Name: <input id="showname" type="search" name="name" value="<?= (string)@$_POST['name'] ?>" /></p>
		<p>The TVDB id: <input id="add_tvdb_series_id" type="search" name="tvdb_series_id" value="<?= (string)@$_POST['tvdb_series_id'] ?>" /></p>

		<?if (@$existingShow):?>
			<?if ($cfg->with_tvtorrents):?>
				<p>TvTorrents id: <input type="search" name="tvtorrents_show_id" value="<?= $existingShow->tvtorrents_show_id ?>" /></p>
			<?endif?>
			<?if ($cfg->with_dailytvtorrents):?>
				<p>DailyTvTorrents name: <input type="search" name="dailytvtorrents_name" value="<?= $existingShow->dailytvtorrents_name ?>" /></p>
			<?endif?>
		<?endif?>

		<p><input type="submit" name="submit" value="<?= @$adding_show_tvdb_result ? 'Save' : 'Next' ?>" /><p>

		<?if (@$adding_show_tvdb_result):?>
			<script>window.on('load', function() { scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight); });</script>

			<p><label><input type="checkbox" name="dont_connect_tvdb" /> Don't connect to The TVDB</label></p>
			<p<?if (false === @$existingShow): ?> class="error"<? endif ?>><label><input type="checkbox" name="replace_existing" <? if ($exists): ?>checked<? endif ?> /> Save The TVDB into existing show</label></p>

			<?if (!is_scalar($adding_show_tvdb_result)):?>
				<div class="search-results">
					<ul>
						<?foreach ($adding_show_tvdb_result->Series AS $show):?>
							<li>
								<a class="tvdb-search-result" title="<?=html($show->Overview)?>" data-id="<?=$show->seriesid?>" href="#<?=$show->seriesid?>"><?=html($show->SeriesName)?></a>
								(<a target=_blank href="http://www.thetvdb.com/?tab=series&id=<?=$show->seriesid?>">=&gt;</a>)
								<div class="tvdb-search-result-description"><?=html($show->Overview)?></div>
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
	var f = this, s = f.elements.submit;
	s.disabled = true;
	$.post(f.action, new FormData(f)).on('success', function(e, html) {
		if ( html.substr(0, 2) === 'OK' ) {
			var id = html.substr(2);
			document.cookie = 'series_hilited=' + id;
			scrollTo(0, 0);
			location.reload();
		}
		else {
			$('add-show-wrapper').setHTML(html);
		}
	}).on('done', function(e) {
		s.disabled = false;
	});
});

$$('a.tvdb-search-result').on('click', function(e) {
	e.preventDefault();
	var id = this.data('id');
	$('add_tvdb_series_id').value = id;
});
</script>
