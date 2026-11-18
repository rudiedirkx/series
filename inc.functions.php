<?php

use rdx\imdb\Client;
use rdx\series\RemoteImdb;

function db() : db_generic {
	return $GLOBALS['db'];
}

function imdb() : Client {
	return $GLOBALS['imdb'];
}

function remote() : RemoteImdb {
	return $GLOBALS['remote'];
}

function html_asset( string $src ) : string {
	$buster = '?_' . filemtime($src);
	return $src . $buster;
}

/**
 * @param AssocArray $query
 */
function get_url( string $path, array $query = array() ) : string {
	$fragment = '';
	if ( is_int($p = strpos($path, '#')) ) {
		$fragment = substr($path, $p);
		$path = substr($path, 0, $p);
	}

	$query = $query ? '?' . http_build_query($query) : '';
	$path = $path ? $path . '.php' : basename($_SERVER['SCRIPT_NAME']);
	return $path . $query . $fragment;
}

/**
 * @param AssocArray $query
 */
function do_redirect( string $path, array $query = array() ) : never {
	$url = get_url($path, $query);
	header('Location: ' . $url);
	exit;
}

function is_logged_in( bool $act = true ) : bool {
	if ( defined('USER_ID') ) {
		return true;
	}

	session_start();
	if ( $uid = @$_SESSION['series']['uid'] ) {
		if ( db()->count('users', array('id' => $uid)) ) {
			define('USER_ID', $_SESSION['series']['uid']);
			return true;
		}
	}

	if ( $act ) {
		header('Location: login.php');
		exit;
	}

	return false;
}

function html( ?string $str ) : string {
	return htmlspecialchars($str ?? '', ENT_COMPAT, 'UTF-8');
}

