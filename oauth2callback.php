<?php 
require_once 'config.php';
require_once 'vendor/autoload.php';
session_start();
$client = new Google_Client();
$client->setAuthConfig(SECRET);
$client->setRedirectUri(GOOGLECALLBACK);
$client->setScopes(array('https://www.googleapis.com/auth/drive'));
$client->setAccessType("offline");
$client->setApprovalPrompt('force');
if (!isset($_GET['code'])) {
  $auth_url = $client->createAuthUrl();
  header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
} else {
  $client->authenticate($_GET['code']);
   $token = $client->getAccessToken();
  file_put_contents("token.txt",$token['refresh_token']);
  header('Location: ' . filter_var(HOMEURL, FILTER_SANITIZE_URL));
}