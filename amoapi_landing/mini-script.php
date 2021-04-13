<?php
require __DIR__ . '/vendor/autoload.php';
require_once("AmoHelper.php");
use Amolib\AmoHelper;

$phone = $_POST['tel'];
// $name = $_POST['name'];
// $description = $_POST['description'];
$settings_json = file_get_contents('settings.json');
$settings = json_decode($settings_json);

//echo $settings->client_id . " " . $settings->client_secret . " " . $settings->client_redirect_uri;
$client = new AmoHelper($settings->client_id, $settings->client_secret, $settings->client_redirect_uri);
$client->miniAdd($tel);
header('Location: http://www.umark.it/land');
?>
