
<form id="add-show" method="post" action>
	<? if (@$linkingShow): ?>
		<input type="hidden" name="id" value="<?= $linkingShow->id ?>" />
	<? endif ?>

	<fieldset style="display: inline-block">
		<legend><?= @$linkingShow ? 'Link show' : 'Add show' ?></legend>
		<p>Name: <input id="showname" type="search" name="name" value="<?= html($_POST['name'] ?? '') ?>" /></p>
		<p>Remote ID: <input id="<?= $remote->field ?>" type="search" name="<?= $remote->field ?>" value="<?= html($_POST[$remote->field] ?? '') ?>" /></p>

		<p>
			<button name="_action" value="search" class="submit">Search</button>
			<button name="_action" value="save">Save</button>
			<a href="seasons.php" style="float: right">seasons</a>
		</p>

		<?if (isset($remote->searchResults)):?>
			<script>
			window.on('load', function() {
				scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight);
			});
			$$('.add-exists').removeClass('add-exists');
			</script>

			<div class="search-results">
				<ul>
					<?foreach ($remote->searchResults AS $result):
						$exists = (int) $db->select_one('series', 'id', [$remote->field => $result->id, 'user_id' => USER_ID]);
						?>
						<li class="<?= $exists ? 'exists' : '' ?>">
							<a
								class="tvdb-search-result"
								title="<?= html($result->plot) ?>"
								data-id="<?= $result->id ?>"
								data-name="<?= html($result->name) ?>"
								href="#<?= $result->id ?>"
							><?= html($result->name) ?></a>
							(<?= $result->year ?>)
							<?if ($exists):?>
								(<strong>you have this</strong>)
								<script>$$('[data-showid="<?= $exists ?>"]', true).addClass('add-exists');</script>
							<?endif?>
							(<a target="_blank" href="<?= $result->url ?>">=&gt;</a>)
							<div class="tvdb-search-result-description"><?= html($result->plot) ?></div>
						</li>
					<?endforeach?>
				</ul>
			</div>

			<details>
				<summary><?= count($db->queries) ?> queries</summary>
				<ol><li><?= implode('</li><li>', $db->queries) ?></li></ol>
			</details>
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
	$('imdb_id').value = id;
});
</script>
