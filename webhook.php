<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/handlers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$input  = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    http_response_code(400);
    exit('Bad Request');
}

if (isset($update['message'])) {
    handleMessage($update['message']);
} elseif (isset($update['callback_query'])) {
    handleCallback($update['callback_query']);
}

http_response_code(200);
echo 'OK';
