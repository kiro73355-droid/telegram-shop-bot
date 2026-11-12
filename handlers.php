<?php

function handleMessage(array $message): void
{
    $chatId = $message['chat']['id'];
    $text   = trim($message['text'] ?? '');
    $userId = $message['from']['id'];
    $name   = $message['from']['first_name'] ?? 'there';

    $session = loadSession($userId);

    switch (strtolower($text)) {
        case '/start':
            sendMessage($chatId,
                "👋 Welcome to *ShopBot*, {$name}!

" .
                "Browse our catalog, add items to your cart, and checkout — all right here in Telegram. 🛒

" .
                "Use the buttons below to get started!",
                mainMenuKeyboard()
            );
            break;

        case '/shop':
        case '/catalog':
            showCategories($chatId);
            break;

        case '/cart':
            showCart($chatId, $session);
            break;

        case '/help':
            showHelp($chatId);
            break;

        case '/clear':
            cartClear($session);
            saveSession($userId, $session);
            sendMessage($chatId, "🗑️ Cart cleared!", mainMenuKeyboard());
            break;

        default:
            sendMessage($chatId,
                "I didn't understand that. Use the menu below or type:
" .
                "• /shop — Browse products
" .
                "• /cart — View your cart
" .
                "• /help — Help",
                mainMenuKeyboard()
            );
    }
}

function handleCallback(array $callbackQuery): void
{
    $callbackId = $callbackQuery['id'];
    $chatId     = $callbackQuery['message']['chat']['id'];
    $messageId  = $callbackQuery['message']['message_id'];
    $userId     = $callbackQuery['from']['id'];
    $data       = $callbackQuery['data'] ?? '';

    $session = loadSession($userId);

    if ($data === 'menu_main') {
        answerCallback($callbackId);
        editMessage($chatId, $messageId,
            "🏠 *Main Menu*

What would you like to do?",
            mainMenuKeyboard()
        );
        return;
    }

    if ($data === 'menu_shop') {
        answerCallback($callbackId);
        editMessage($chatId, $messageId,
            "🛍️ *Choose a Category*

What are you looking for?",
            categoryKeyboard()
        );
        return;
    }

    if (str_starts_with($data, 'cat_')) {
        $catKey    = substr($data, 4);
        $categories = getCategories();
        $catName   = $categories[$catKey] ?? 'Products';
        $products  = getProductsByCategory($catKey);

        answerCallback($callbackId);
        editMessage($chatId, $messageId,
            "🛍️ *{$catName}*

Select a product to see details:",
            productsKeyboard($products, $catKey)
        );
        return;
    }

    if (str_starts_with($data, 'product_')) {
        $productId = (int) substr($data, 8);
        $product   = getProductById($productId);

        if (!$product) {
            answerCallback($callbackId, '✗ Product not found.', true);
            return;
        }

        $inCart = $session['cart'][$productId] ?? 0;
        $cartNote = $inCart > 0 ? "

🛒 _{$inCart} already in your cart._" : '';

        answerCallback($callbackId);
        editMessage($chatId, $messageId,
            "{$product['emoji']} *{$product['name']}*

" .
            "📝 {$product['desc']}

" .
            "💰 Price: *" . CURRENCY . number_format($product['price'], 2) . "*" .
            $cartNote,
            productDetailKeyboard($productId)
        );
        return;
    }

    if (str_starts_with($data, 'add_')) {
        $productId = (int) substr($data, 4);
        $product   = getProductById($productId);

        if (!$product) {
            answerCallback($callbackId, '✗ Product not found.', true);
            return;
        }

        cartAdd($session, $productId);
        saveSession($userId, $session);

        $qty = $session['cart'][$productId];
        answerCallback($callbackId, "✓ {$product['name']} added! (×{$qty} in cart)");

        editMessage($chatId, $messageId,
            "{$product['emoji']} *{$product['name']}*

" .
            "📝 {$product['desc']}

" .
            "💰 Price: *" . CURRENCY . number_format($product['price'], 2) . "*

" .
            "✓ _Added to cart! You have {$qty} × this item._",
            productDetailKeyboard($productId)
        );
        return;
    }

    if ($data === 'menu_cart') {
        answerCallback($callbackId);
        editMessage($chatId, $messageId,
            cartSummaryText($session),
            cartKeyboard($session)
        );
        return;
    }

    if (str_starts_with($data, 'remove_')) {
        $productId = (int) substr($data, 7);
        $product   = getProductById($productId);
        $name      = $product ? $product['name'] : 'Item';

        cartRemove($session, $productId);
        saveSession($userId, $session);

        answerCallback($callbackId, "🗑️ {$name} removed.");
        editMessage($chatId, $messageId,
            cartSummaryText($session),
            cartKeyboard($session)
        );
        return;
    }

    if ($data === 'clear_cart') {
        cartClear($session);
        saveSession($userId, $session);

        answerCallback($callbackId, '🗑️ Cart cleared!');
        editMessage($chatId, $messageId,
            "🛒 *Your Cart*

Your cart is now empty.",
            cartKeyboard($session)
        );
        return;
    }

    if ($data === 'checkout') {
        if (empty($session['cart'])) {
            answerCallback($callbackId, '🛒 Your cart is empty!', true);
            return;
        }

        $orderNumber = strtoupper(substr(md5(uniqid($userId, true)), 0, 8));
        $total       = cartTotal($session);
        $summary     = cartSummaryText($session);

        saveOrder($userId, $orderNumber, $session['cart'], $total);
        cartClear($session);
        saveSession($userId, $session);

        answerCallback($callbackId, '🎉 Order placed!');
        editMessage($chatId, $messageId,
            "🎉 *Order Confirmed!*

" .
            "{$summary}

" .
            "▪ Order #: `{$orderNumber}`
" .
            "📦 Status: *Processing*

" .
            "_Thank you for your purchase! We'll notify you when your order ships._ 🚚",
            [
                [['text' => '🛍️ Continue Shopping', 'callback_data' => 'menu_shop']],
                [['text' => '📦 My Orders',          'callback_data' => 'menu_orders']],
                [['text' => '🏠 Main Menu',          'callback_data' => 'menu_main']],
            ]
        );
        return;
    }

    if ($data === 'menu_orders') {
        answerCallback($callbackId);
        $ordersText = getOrdersText($userId);
        editMessage($chatId, $messageId,
            $ordersText,
            [[['text' => '🏠 Main Menu', 'callback_data' => 'menu_main']]]
        );
        return;
    }

    if ($data === 'menu_help') {
        answerCallback($callbackId);
        editMessage($chatId, $messageId,
            getHelpText(),
            [[['text' => '🏠 Main Menu', 'callback_data' => 'menu_main']]]
        );
        return;
    }

    answerCallback($callbackId, '⚠️ Unknown action.');
}

function showCategories(int $chatId): void
{
    sendMessage($chatId,
        "🛍️ *Choose a Category*

What are you looking for?",
        categoryKeyboard()
    );
}

function showCart(int $chatId, array $session): void
{
    sendMessage($chatId, cartSummaryText($session), cartKeyboard($session));
}

function showHelp(int $chatId): void
{
    sendMessage($chatId, getHelpText(), [[['text' => '🏠 Main Menu', 'callback_data' => 'menu_main']]]);
}

function getHelpText(): string
{
    return
        "ℹ️ *ShopBot Help*

" .
        "Here's what you can do:

" .
        "🛍️ *Shop* — Browse product categories
" .
        "🛒 *My Cart* — View, modify, or checkout
" .
        "📦 *My Orders* — View your order history

" .
        "*Commands:*
" .
        "/start — Show main menu
" .
        "/shop — Browse catalog
" .
        "/cart — View cart
" .
        "/clear — Empty your cart
" .
        "/help — Show this help

" .
        "_Tap any button to navigate. Happy shopping!_ 🎉";
}

function ordersFile(int $userId): string
{
    return DATA_DIR . "orders_{$userId}.json";
}

function saveOrder(int $userId, string $orderNumber, array $cart, float $total): void
{
    $file   = ordersFile($userId);
    $orders = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

    $items = [];
    foreach ($cart as $pid => $qty) {
        $p = getProductById((int)$pid);
        if ($p) {
            $items[] = [
                'name'     => $p['name'],
                'qty'      => $qty,
                'price'    => $p['price'],
                'subtotal' => $p['price'] * $qty,
            ];
        }
    }

    $orders[] = [
        'order_number' => $orderNumber,
        'date'         => date('Y-m-d H:i:s'),
        'items'        => $items,
        'total'        => $total,
        'status'       => 'Processing',
    ];

    file_put_contents($file, json_encode($orders, JSON_PRETTY_PRINT));
}

function getOrdersText(int $userId): string
{
    $file   = ordersFile($userId);
    $orders = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

    if (empty($orders)) {
        return "📦 *My Orders*

You haven't placed any orders yet.
Start shopping! 🛍️";
    }

    $text  = "📦 *My Orders*

";
    $recent = array_reverse($orders);
    $shown  = array_slice($recent, 0, 5);

    foreach ($shown as $order) {
        $text .= "🧾 Order #`{$order['order_number']}`
";
        $text .= "📅 {$order['date']}
";
        $text .= "▪ Status: *{$order['status']}*
";
        $text .= "💰 Total: *" . CURRENCY . number_format($order['total'], 2) . "*
";
        $text .= "─────────────
";
    }

    if (count($orders) > 5) {
        $text .= "
_Showing last 5 of " . count($orders) . " orders._";
    }

    return $text;
}
