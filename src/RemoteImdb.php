<?php

namespace rdx\series;

use rdx\imdb\Client;
use rdx\imdb\Title;

class RemoteImdb {

	public string $field = 'imdb_id';

	public ?array $searchResults = null;

	public function __construct(
		protected Client $imdb,
	) {}

	public function init() : void {
		$this->imdb->logIn();
	}

	public function search(string $query) : array {
		$titles = $this->imdb->searchGraphql($query, ['types' => ['TITLE']]);

		$results = [];
		foreach ($titles as $title) {
			if ($title->type == Title::TYPE_SERIES) {
				$results[] = new SearchResult($title->id, $title->name,
					year: $title->year,
					url: sprintf('https://www.imdb.com/title/%s/', $title->id),
					plot: $title->plot,
				);
			}
		}

		return $this->searchResults = $results;
	}

	public function getInfo(string $id) : ?SearchResult {
		$data = $this->imdb->graphqlData(file_get_contents(__DIR__ . '/title.graphql'), [
			'titleId' => $id,
		]);
		$title = $data['data']['title'];

		$id = $title['id'];
		$name = $title['titleText']['text'] ?? null;
		if (!$name) return null;

		$year = $title['releaseYear']['year'] ?? null;
		$plot = $title['plots']['edges'][0]['node']['plotText']['plainText'] ?? null;

		return new SearchResult($id, $name,
			year: $year,
			plot: $plot,
			episodes: $this->fetchAllEpisodes($id, $title['episodes']['episodes'] ?? []),
			banner: $title['primaryImage']['url'] ?? null,
		);
	}

	protected function fetchAllEpisodes(string $id, array $episodes) : array {
		$edges = $episodes['edges'] ?? [];
		if (!count($edges)) return [];
// dd($edges);

		$cursor = end($edges)['cursor'];
		for ($i = 0; $i < 5; $i++) {
			if (count($edges) >= $episodes['total']) break;

			$data = $this->imdb->graphqlData(file_get_contents(__DIR__ . '/title.graphql'), [
				'titleId' => $id,
				'episodesCursor' => $cursor,
			]);
			$title = $data['data']['title'];
			$episodes = $title['episodes']['episodes'] ?? [];
			$edges = array_merge($edges, $episodes['edges']);
			$cursor = end($edges)['cursor'];
		}

		return $this->unpackEpisodes($edges);
	}

	protected function unpackEpisodes(array $episodes) : array {
		$dates = [];
		foreach ($episodes as $episode) {
			$node = $episode['node'];
			if (
				!empty($node['canRate']['isRatable']) &&
				!empty($node['series']['episodeNumber']) &&
				$node['series']['episodeNumber']['seasonNumber'] > 0 &&
				$node['series']['episodeNumber']['episodeNumber'] > 0
			) {
				$season = $node['series']['episodeNumber']['seasonNumber'];
				$date = sprintf('%d-%02d-%02d', $node['releaseDate']['year'], $node['releaseDate']['month'], $node['releaseDate']['day']);
				$dates[$season] ??= [];
				$dates[$season][] = $date;
			}
		}
		ksort($dates, SORT_NUMERIC);
// dd(count($episodes), $dates);
		$seasons = array_map(function(array $dates) {
			return new Season([
				'episodes' => count($dates),
				'runs_from' => min($dates),
				'runs_to' => max($dates),
			]);
		}, $dates);
		return $seasons;
	}

}
