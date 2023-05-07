<?php

namespace rdx\series;

class SearchResult {

	public function __construct(
		public string $id,
		public string $name,
		public ?int $year = null,
		public ?string $url = null,
		public ?string $plot = null,
		public ?string $banner = null,
		public ?array $episodes = null,
	) {}

}
