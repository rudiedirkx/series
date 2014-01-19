<?php

function html($str) {
	return htmlspecialchars($str, ENT_COMPAT, 'UTF-8');
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


