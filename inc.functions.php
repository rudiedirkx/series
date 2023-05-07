<?php

function html_asset( $src ) {
	$buster = '?_' . filemtime($src);
	return $src . $buster;
}

function get_url( $path, $query = array() ) {
	$fragment = '';
	if ( is_int($p = strpos($path, '#')) ) {
		$fragment = substr($path, $p);
		$path = substr($path, 0, $p);
	}

	$query = $query ? '?' . http_build_query($query) : '';
	$path = $path ? $path . '.php' : basename($_SERVER['SCRIPT_NAME']);
	return $path . $query . $fragment;
}

function do_redirect( $path, $query = array() ) {
	$url = get_url($path, $query);
	header('Location: ' . $url);
}

function is_logged_in( $act = true ) {
	global $db;

	if ( defined('USER_ID') ) {
		return true;
	}

	session_start();
	if ( $uid = @$_SESSION['series']['uid'] ) {
		if ( $db->count('users', array('id' => $uid)) ) {
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

function html(?string $str) {
	return htmlspecialchars($str ?? '', ENT_COMPAT, 'UTF-8');
}

function attributes($attrs, $except = array()) {
	$html = '';
	foreach ( $attrs AS $name => $value ) {
		if ( !in_array($name, $except) ) {
			$html .= ' ' . $name . '="' . html($value) . '"';
		}
	}

	return $html;
}

function el_number_value_pre($value) {
	return ' value="' . html($value) . '"';
}

function el_checkbox_value_pre($value) {
	return $value ? ' checked' : '';
}

function el_number_value_post($value) {
	return (int)$value;
}

function el_checkbox_value_post($value) {
	return $value ? 1 : 0;
}


