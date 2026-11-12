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

function formatPrice(float|int $price): string
{
    return number_format($price, 0, '.', ' ') . ' ' . CURRENCY;
}

function escapeMd(string $text): string
{
    return str_replace(['*', '_', '`', '['], ['\*', '\_', '\`', '\['], $text);
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

function mainMenuKeyboard(int $userId = 0): array
{
    $rows = [
        [
            ['text' => '🛍️ Do‘kon',          'callback_data' => 'menu_shop'],
            ['text' => '🛒 Savatcham',       'callback_data' => 'menu_cart'],
        ],
        [
            ['text' => '📦 Buyurtmalarim',   'callback_data' => 'menu_orders'],
            ['text' => 'ℹ️ Yordam',          'callback_data' => 'menu_help'],
        ],
    ];

    if ($userId === ADMIN_ID) {
        $rows[] = [
            ['text' => '⚙️ Admin Panel', 'callback_data' => 'admin_menu'],
        ];
    }

    return $rows;
}

function categoryKeyboard(string $prefix = 'cat_', bool $isAdmin = false): array
{
    $rows = [];
    $categories = getCategories();
    $chunk = array_chunk(array_keys($categories), 2);
    foreach ($chunk as $group) {
        $row = [];
        foreach ($group as $key) {
            $row[] = ['text' => $categories[$key], 'callback_data' => "{$prefix}{$key}"];
        }
        $rows[] = $row;
    }

    if ($isAdmin) {
        $rows[] = [['text' => '❌ Bekor qilish', 'callback_data' => 'admin_cancel']];
    } else {
        $rows[] = [['text' => '🏠 Bosh menyu', 'callback_data' => 'menu_main']];
    }

    return $rows;
}

function productsKeyboard(array $products, string $category): array
{
    $rows = [];
    foreach ($products as $p) {
        $rows[] = [[
            'text'          => "{$p['emoji']} {$p['name']} — " . formatPrice($p['price']),
            'callback_data' => "product_{$p['id']}"
        ]];
    }
    $rows[] = [
        ['text' => '⬅️ Kategoriyalar', 'callback_data' => 'menu_shop'],
        ['text' => '🛒 Savatcha',      'callback_data' => 'menu_cart'],
    ];
    return $rows;
}

function productDetailKeyboard(int $productId): array
{
    return [
        [
            ['text' => '➕ Savatga qo‘shish', 'callback_data' => "add_{$productId}"],
            ['text' => '🛒 Savatni ko‘rish',  'callback_data' => 'menu_cart'],
        ],
        [
            ['text' => '⬅️ Orqaga',           'callback_data' => 'menu_shop'],
            ['text' => '🏠 Bosh menyu',       'callback_data' => 'menu_main'],
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
                $rows[] = [['text' => "❌ O‘chirish: {$p['emoji']} {$p['name']}", 'callback_data' => "remove_{$pid}"]];
            }
        }
        $rows[] = [
            ['text' => '✅ Buyurtma berish', 'callback_data' => 'checkout'],
            ['text' => '🗑️ Tozalash',        'callback_data' => 'clear_cart'],
        ];
    }

    $rows[] = [
        ['text' => '🛍️ Xaridni davom ettirish', 'callback_data' => 'menu_shop'],
        ['text' => '🏠 Bosh menyu',             'callback_data' => 'menu_main'],
    ];
    return $rows;
}

function adminMenuKeyboard(): array
{
    return [
        [
            ['text' => '➕ Mahsulot qo‘shish',  'callback_data' => 'admin_add_product'],
            ['text' => '🗑️ Mahsulot o‘chirish', 'callback_data' => 'admin_del_list'],
        ],
        [
            ['text' => '📦 Barcha buyurtmalar', 'callback_data' => 'admin_orders'],
            ['text' => '📊 Statistika',         'callback_data' => 'admin_stats'],
        ],
        [
            ['text' => '📢 Xabar yuborish',     'callback_data' => 'admin_broadcast'],
        ],
        [
            ['text' => '🏠 Bosh menyu',         'callback_data' => 'menu_main'],
        ],
    ];
}

function adminCancelKeyboard(): array
{
    return [
        [['text' => '❌ Bekor qilish', 'callback_data' => 'admin_cancel']],
    ];
}

function adminDeleteProductsKeyboard(array $products): array
{
    $rows = [];
    foreach ($products as $p) {
        $rows[] = [[
            'text'          => "🗑️ {$p['emoji']} {$p['name']} (" . formatPrice($p['price']) . ")",
            'callback_data' => "admin_del_{$p['id']}"
        ]];
    }
    $rows[] = [['text' => '⬅️ Admin menyu', 'callback_data' => 'admin_menu']];
    return $rows;
}
