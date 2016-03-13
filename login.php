<?php

require 'inc.bootstrap.php';

if ( is_logged_in(false) ) {
	do_redirect('index');
	echo "You're already logged in....";
	exit;
}

if ( isset($_POST['user'], $_POST['pass']) ) {
	setcookie('sr_user', $_POST['user']);

	// Both fields are required.
	if ( !trim($_POST['user']) || !trim($_POST['pass']) ) {
		echo '<em>E-mail</em> and <em>Password</em> must not be empty.';
		exit;
	}

	$args = array($_POST['user'], ':', $_POST['pass']);
	$user = $db
		->select('users', 'email = ? AND password = SHA1(CONCAT(id, ?, ?))', $args)
		->first();

	if ( $user ) {
		@session_start();

		$_SESSION['series']['uid'] = $user->id;
		$_SESSION['series']['salt'] = rand();

		do_redirect('index');
		exit;
	}

	echo 'Invalid credentials.';
	exit;
}

$error = @$_GET['error'];

?>
<!doctype html>
<html>

<head>
	<meta charset="utf-8" />
	<link rel="shortcut icon" type="image/x-icon" href="favicon.ico" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Series</title>
	<style><?php require 'series.css' ?></style>
</head>

<body>

<? if ($error): ?>
	<p class="error">Error: <?= html($error) ?></p>
<? endif ?>

<form method="post" action>
	<p class="form-item">E-mail: <input type="email" name="user" value="<?= @$_COOKIE['sr_user'] ?>" required autofocus /></p>
	<p class="form-item">Password: <input type="password" name="pass" required /></p>
	<p><button>Log in</button></p>
</form>
