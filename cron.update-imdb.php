<?php

use rdx\series\Show;

if (PHP_SAPI !== 'cli') exit;

chdir(__DIR__);
require 'inc.bootstrap.php';

$options = getopt('', ['test', 'wait:'], $argsIndex);
$percentage = (float) ($argv[$argsIndex] ?? 0);
$wait = (int) ($options['wait'] ?? 1000);
if ($percentage <= 0 || $percentage > 100 || $wait < 0) {
	fwrite(STDERR, "Usage: php cron.update-imdb.php [--test] [--wait=ms] <percentage>\n");
	exit(1);
}
$test = isset($options['test']);

$shows = Show::query("SELECT * FROM series WHERE imdb_id LIKE 'tt%' AND deleted = '0'");
$total = count($shows);
shuffle($shows);
$amount = (int) ceil($total * $percentage / 100);
$shows = array_slice($shows, 0, $amount);

echo "Checking $amount of $total shows\n\n";

$newData = 0;
$errors = 0;
foreach ($shows as $i => $show) {
	if ($i > 0) {
		usleep($wait * 1000);
	}

	echo "[$show->id] $show->name";

	try {
		$result = remote()->getInfo($show->imdb_id);
		$errors = 0;
		if (!$result) {
			echo " - Not found";
		}
		else {
			$preSeasons = array_keys($show->seasons);
			$postSeasons = array_keys($result->episodes);
			$newSeasons = array_diff($postSeasons, $preSeasons);
			if (count($newSeasons) > 0) {
				echo " - (nxt $show->next_episode) New seasons: " . implode(', ', $newSeasons);
				$newData++;
			}

			if (!$test) {
				$show->saveImdb($result);
			}
		}
	}
	catch (Throwable $ex) {
		echo " -\nERROR: " . $ex->getMessage();
		$errors++;
	}

	echo "\n";

	if ($errors >= 2) {
		echo "\nStopping after $errors consecutive errors\n";
		exit(1);
	}
}

echo "\nNew data for $newData shows\n";
echo "\nDONE.\n";
