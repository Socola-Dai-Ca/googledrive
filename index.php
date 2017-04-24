<?php
session_start();

require_once 'config.php';
require_once  'vendor/autoload.php';
$client = new Google_Client();
$client->setAuthConfig('client_secret.json');
$client->setAccessType("offline");
$client->setApprovalPrompt('force');
$client->setScopes(array('https://www.googleapis.com/auth/drive'));

if (file_exists('token.txt')) {
	$refreshToken = file_get_contents("token.txt");
	$client->refreshToken($refreshToken);
	$newToken = $client->getAccessToken();
	$_SESSION['access_token']  = $newToken;
}

if (!isset($_SESSION['access_token']) ) {
  header('Location: ' . filter_var(GOOGLECALLBACK, FILTER_SANITIZE_URL));
  exit;
}

//login success
require_once  'class.googledrive.php';
$google = new GoogleDriveManager();
$allFiles = $google->getAllFiles('root');
var_dump($allFiles[0]);