<?php

function apiRequest(string $method, array $params): array
{
    $url = BOT_URL . '/' . $method;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true) ?? [];
}

function sendMessage(int $chatId, string $text, array $keyboard = [], string $parseMode = 'Markdown'): void
{
    $params = [
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => $parseMode,
    ];

    if (!empty($keyboard)) {
        $params['reply_markup'] = json_encode([
            'inline_keyboard' => $keyboard,
        ]);
    }

    apiRequest('sendMessage', $params);
}

function editMessage(int $chatId, int $messageId, string $text, array $keyboard = []): void
{
    $params = [
        'chat_id'    => $chatId,
        'message_id' => $messageId,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];

    if (!empty($keyboard)) {
        $params['reply_markup'] = json_encode([
            'inline_keyboard' => $keyboard,
        ]);
    } else {
        $params['reply_markup'] = json_encode(['inline_keyboard' => []]);
    }

    apiRequest('editMessageText', $params);
}

function answerCallback(string $callbackId, string $text = '', bool $showAlert = false): void
{
    apiRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text'              => $text,
        'show_alert'        => $showAlert,
    ]);
}

function mainMenuKeyboard(): array
{
    return [
        [
            ['text' => '🛍️ Shop',      'callback_data' => 'menu_shop'],
            ['text' => '🛒 My Cart',   'callback_data' => 'menu_cart'],
        ],
        [
            ['text' => '📦 My Orders', 'callback_data' => 'menu_orders'],
            ['text' => 'ℹ️ Help',       'callback_data' => 'menu_help'],
        ],
    ];
}

function categoryKeyboard(): array
{
    $rows = [];
    $categories = getCategories();
    $chunk = array_chunk(array_keys($categories), 2);
    foreach ($chunk as $group) {
        $row = [];
        foreach ($group as $key) {
            $row[] = ['text' => $categories[$key], 'callback_data' => "cat_{$key}"];
        }
        $rows[] = $row;
    }
    $rows[] = [['text' => '🏠 Main Menu', 'callback_data' => 'menu_main']];
    return $rows;
}

function productsKeyboard(array $products, string $category): array
{
    $rows = [];
    foreach ($products as $p) {
        $rows[] = [['text' => "{$p['emoji']} {$p['name']} — " . CURRENCY . $p['price'], 'callback_data' => "product_{$p['id']}"]];
    }
    $rows[] = [
        ['text' => '⬅️ Categories', 'callback_data' => 'menu_shop'],
        ['text' => '🛒 Cart',       'callback_data' => 'menu_cart'],
    ];
    return $rows;
}

function productDetailKeyboard(int $productId): array
{
    return [
        [
            ['text' => '➕ Add to Cart', 'callback_data' => "add_{$productId}"],
            ['text' => '🛒 View Cart',   'callback_data' => 'menu_cart'],
        ],
        [
            ['text' => '⬅️ Back',        'callback_data' => 'menu_shop'],
            ['text' => '🏠 Main Menu',   'callback_data' => 'menu_main'],
        ],
    ];
}

function cartKeyboard(array $session): array
{
    $rows = [];

    if (!empty($session['cart'])) {
        foreach ($session['cart'] as $pid => $qty) {
            $p = getProductById((int)$pid);
            if ($p) {
                $rows[] = [['text' => "✗ Remove {$p['emoji']} {$p['name']}", 'callback_data' => "remove_{$pid}"]];
            }
        }
        $rows[] = [
            ['text' => '✓ Checkout',    'callback_data' => 'checkout'],
            ['text' => '🗑️ Clear Cart', 'callback_data' => 'clear_cart'],
        ];
    }

    $rows[] = [
        ['text' => '🛍️ Continue Shopping', 'callback_data' => 'menu_shop'],
        ['text' => '🏠 Main Menu',          'callback_data' => 'menu_main'],
    ];
    return $rows;
}
