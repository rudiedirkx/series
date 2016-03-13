<?php

require 'inc.bootstrap.php';

if ( is_logged_in(false) && isset($_SESSION['series']) ) {
	unset($_SESSION['series']);
}

do_redirect('login');
