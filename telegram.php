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
        CURLOPT_TIMEOUT        => 15,
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

function sendMessage(int $chatId, string $text, array $keyboard = [], string $parseMode = 'Markdown', bool $removeReplyKb = false): array
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
    } elseif ($removeReplyKb) {
        $params['reply_markup'] = json_encode([
            'remove_keyboard' => true,
        ]);
    }

    $res = apiRequest('sendMessage', $params);
    if (empty($res['ok']) && $parseMode !== '') {
        unset($params['parse_mode']);
        $res = apiRequest('sendMessage', $params);
    }
    return $res;
}

function sendReplyKeyboard(int $chatId, string $text, array $buttons, string $parseMode = 'Markdown'): array
{
    $params = [
        'chat_id'      => $chatId,
        'text'         => $text,
        'parse_mode'   => $parseMode,
        'reply_markup' => json_encode([
            'keyboard'          => $buttons,
            'resize_keyboard'   => true,
            'one_time_keyboard' => true,
        ]),
    ];

    $res = apiRequest('sendMessage', $params);
    if (empty($res['ok']) && $parseMode !== '') {
        unset($params['parse_mode']);
        $res = apiRequest('sendMessage', $params);
    }
    return $res;
}

function sendPhoto(int $chatId, string $photo, string $caption = '', array $keyboard = [], string $parseMode = 'Markdown'): array
{
    $params = [
        'chat_id'    => $chatId,
        'photo'      => $photo,
        'caption'    => $caption,
        'parse_mode' => $parseMode,
    ];

    if (!empty($keyboard)) {
        $params['reply_markup'] = json_encode([
            'inline_keyboard' => $keyboard,
        ]);
    }

    $res = apiRequest('sendPhoto', $params);
    if (empty($res['ok']) && $parseMode !== '') {
        unset($params['parse_mode']);
        $res = apiRequest('sendPhoto', $params);
    }
    return $res;
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

    $res = apiRequest('editMessageText', $params);
    if (empty($res['ok'])) {
        unset($params['parse_mode']);
        apiRequest('editMessageText', $params);
    }
}

function editMessageCaption(int $chatId, int $messageId, string $caption, array $keyboard = []): void
{
    $params = [
        'chat_id'    => $chatId,
        'message_id' => $messageId,
        'caption'    => $caption,
        'parse_mode' => 'Markdown',
    ];

    if (!empty($keyboard)) {
        $params['reply_markup'] = json_encode([
            'inline_keyboard' => $keyboard,
        ]);
    }

    $res = apiRequest('editMessageCaption', $params);
    if (empty($res['ok'])) {
        unset($params['parse_mode']);
        apiRequest('editMessageCaption', $params);
    }
}

function deleteMessage(int $chatId, int $messageId): void
{
    apiRequest('deleteMessage', [
        'chat_id'    => $chatId,
        'message_id' => $messageId,
    ]);
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
            ['text' => '🔍 Qidiruv',        'callback_data' => 'menu_search'],
        ],
        [
            ['text' => '🛒 Savatcham',       'callback_data' => 'menu_cart'],
            ['text' => '📦 Buyurtmalarim',   'callback_data' => 'menu_orders'],
        ],
        [
            ['text' => '☎️ Aloqa',           'callback_data' => 'menu_contact'],
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

function productsKeyboard(array $products, string $category = ''): array
{
    $rows = [];
    foreach ($products as $p) {
        $photoTag = !empty($p['photo']) ? ' 🖼️' : '';
        $rows[] = [[
            'text'          => "{$p['emoji']} {$p['name']}{$photoTag} — " . formatPrice($p['price']),
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
                $rows[] = [
                    ['text' => '➖', 'callback_data' => "cart_dec_{$pid}"],
                    ['text' => "{$p['emoji']} {$p['name']} ({$qty})", 'callback_data' => "product_{$pid}"],
                    ['text' => '➕', 'callback_data' => "cart_inc_{$pid}"],
                    ['text' => '🗑️', 'callback_data' => "remove_{$pid}"],
                ];
            }
        }

        if (!empty($session['promo']) && getPromoByCode($session['promo'])) {
            $rows[] = [[
                'text'          => "❌ Promo-kodni o‘chirish ({$session['promo']})",
                'callback_data' => 'cart_promo_remove'
            ]];
        } else {
            $rows[] = [[
                'text'          => '🎟️ Promo-kod kiritish',
                'callback_data' => 'cart_promo'
            ]];
        }

        $rows[] = [
            ['text' => '✅ Buyurtma berish', 'callback_data' => 'checkout'],
            ['text' => '🗑️ Tozalash',        'callback_data' => 'clear_cart'],
        ];
    }

    $rows[] = [
        ['text' => '🛍️ Do‘kon',      'callback_data' => 'menu_shop'],
        ['text' => '🏠 Bosh menyu', 'callback_data' => 'menu_main'],
    ];
    return $rows;
}

function paymentMethodKeyboard(): array
{
    return [
        [
            ['text' => '💳 Karta orqali o‘tkazma', 'callback_data' => 'pay_method_card'],
        ],
        [
            ['text' => '💵 Naqd pul (Yetkazilganda)', 'callback_data' => 'pay_method_cash'],
        ],
        [
            ['text' => '❌ Bekor qilish', 'callback_data' => 'checkout_cancel'],
        ],
    ];
}

function adminMenuKeyboard(): array
{
    return [
        [
            ['text' => '➕ Mahsulot qo‘shish',    'callback_data' => 'admin_add_product'],
            ['text' => '✏️ Mahsulot tahrirlash', 'callback_data' => 'admin_edit_list'],
        ],
        [
            ['text' => '🗑️ Mahsulot o‘chirish',   'callback_data' => 'admin_del_list'],
            ['text' => '📂 Kategoriyalar',        'callback_data' => 'admin_categories'],
        ],
        [
            ['text' => '🎟️ Promo-kodlar',         'callback_data' => 'admin_promos'],
            ['text' => '🚚 Dostavka narxi',       'callback_data' => 'admin_delivery'],
        ],
        [
            ['text' => '💳 Karta sozlamalari',    'callback_data' => 'admin_card'],
            ['text' => '☎️ Aloqa ma’lumotlari',   'callback_data' => 'admin_contact'],
        ],
        [
            ['text' => '📦 Buyurtmalar',          'callback_data' => 'admin_orders'],
            ['text' => '📊 Statistika',           'callback_data' => 'admin_stats'],
        ],
        [
            ['text' => '📢 Xabar yuborish',       'callback_data' => 'admin_broadcast'],
            ['text' => '🏠 Bosh menyu',           'callback_data' => 'menu_main'],
        ],
    ];
}

function adminCancelKeyboard(): array
{
    return [
        [['text' => '❌ Bekor qilish', 'callback_data' => 'admin_cancel']],
    ];
}

function adminProductsSelectKeyboard(array $products, string $prefix): array
{
    $rows = [];
    foreach ($products as $p) {
        $photoTag = !empty($p['photo']) ? ' 🖼️' : '';
        $rows[] = [[
            'text'          => "{$p['emoji']} {$p['name']}{$photoTag} (" . formatPrice($p['price']) . ")",
            'callback_data' => "{$prefix}{$p['id']}"
        ]];
    }
    $rows[] = [['text' => '⬅️ Admin menyu', 'callback_data' => 'admin_menu']];
    return $rows;
}

function adminEditProductFieldsKeyboard(int $pid): array
{
    return [
        [
            ['text' => '✏️ Nomini o‘zgartirish',    'callback_data' => "aedit_name_{$pid}"],
            ['text' => '💰 Narxini o‘zgartirish',   'callback_data' => "aedit_price_{$pid}"],
        ],
        [
            ['text' => '📝 Tavsifini o‘zgartirish', 'callback_data' => "aedit_desc_{$pid}"],
            ['text' => '🖼️ Rasmini o‘zgartirish',   'callback_data' => "aedit_photo_{$pid}"],
        ],
        [
            ['text' => '⬅️ Mahsulotlar ro‘yxati',    'callback_data' => 'admin_edit_list'],
        ],
    ];
}

function orderStatusKeyboard(string $orderNumber, bool $hasReceipt = false, bool $fromList = false): array
{
    $rows = [
        [
            ['text' => '✅ Qabul qilish', 'callback_data' => "ord_st_{$orderNumber}_accepted"],
            ['text' => '🚚 Yo‘lda',       'callback_data' => "ord_st_{$orderNumber}_shipping"],
        ],
        [
            ['text' => '🎉 Yetkazildi',   'callback_data' => "ord_st_{$orderNumber}_delivered"],
            ['text' => '❌ Bekor qilish', 'callback_data' => "ord_st_{$orderNumber}_cancelled"],
        ],
    ];

    if ($hasReceipt && $fromList) {
        $rows[] = [['text' => '🧾 To‘lov chekini ko‘rish', 'callback_data' => "admin_receipt_{$orderNumber}"]];
    }

    if ($fromList) {
        $rows[] = [['text' => '⬅️ Buyurtmalar ro‘yxati', 'callback_data' => 'admin_orders']];
    }

    return $rows;
}
