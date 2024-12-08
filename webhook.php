<?php
$config = include('config.php');
$botToken = $config['bot_token'];
$webhookUrl = 'https://modsmatrix.serv00.net/TelegramBots/bot.php';

$response = file_get_contents("https://api.telegram.org/bot{$botToken}/setWebhook?url={$webhookUrl}");

echo $response;
