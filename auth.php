<?php

/*
// check cookie for authentication, otherwise redirect to login.php
preg_match('%/([^/]+)$%', $_SERVER['SCRIPT_NAME'], $matches);
if ($matches[1] != 'login.php') {
	if (isset($_COOKIE['token'])) {
		if ($_COOKIE['token'] != hash('sha256', $_ENV['APP_ADMIN_PWD'])) {
			error('Cannot validate cookie token.');
		}
	} else {
		header('Location: /login.php');
	}
}
*/

function cookie_auth()
{
	if (isset($_COOKIE['token'])) {
		return true;
	}

	return false;
}