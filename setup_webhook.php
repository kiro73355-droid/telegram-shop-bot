<?php

require_once __DIR__ . '/config.php';

$domain = getenv('RAILWAY_PUBLIC_DOMAIN') ?: ($_SERVER['HTTP_HOST'] ?? 'telegram-shop-bot-production-0037a9c7.up.railway.app');
$webhookUrl = 'https://' . $domain . '/webhook.php';

$action = $_GET['action'] ?? 'set';

if ($action === 'set') {
    $response = file_get_contents(BOT_URL . '/setWebhook?url=' . urlencode($webhookUrl));
    $result   = json_decode($response, true);
    echo '<h2>Set Webhook</h2>';
    echo '<p>URL: ' . htmlspecialchars($webhookUrl) . '</p>';
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
