<?php

require_once __DIR__ . '/config.php';

$webhookUrl = 'https://yourdomain.com/telegram-shop-bot/webhook.php';

$action = $_GET['action'] ?? 'set';

if ($action === 'set') {
    $response = file_get_contents(BOT_URL . '/setWebhook?url=' . urlencode($webhookUrl));
    $result   = json_decode($response, true);
    echo '<h2>Set Webhook</h2>';
    echo '<pre>' . print_r($result, true) . '</pre>';

} elseif ($action === 'delete') {
    $response = file_get_contents(BOT_URL . '/deleteWebhook');
    $result   = json_decode($response, true);
    echo '<h2>Delete Webhook</h2>';
    echo '<pre>' . print_r($result, true) . '</pre>';

} elseif ($action === 'info') {
    $response = file_get_contents(BOT_URL . '/getWebhookInfo');
    $result   = json_decode($response, true);
    echo '<h2>Webhook Info</h2>';
    echo '<pre>' . print_r($result, true) . '</pre>';

} else {
    echo 'Unknown action. Use ?action=set | ?action=delete | ?action=info';
}
