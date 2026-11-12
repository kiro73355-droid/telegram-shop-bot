<?php

function handleMessage(array $message): void
{
    $chatId = $message['chat']['id'];
    $text   = trim($message['text'] ?? '');
    $userId = $message['from']['id'];
    $name   = escapeMd($message['from']['first_name'] ?? 'Foydalanuvchi');

    $session = loadSession($userId);

    // Admin ko'p bosqichli jarayonlarini tekshirish
    if ($userId === ADMIN_ID && ($session['state'] ?? 'idle') !== 'idle' && !str_starts_with($text, '/')) {
        handleAdminInput($chatId, $userId, $text, $session);
        return;
    }

    switch (strtolower($text)) {
        case '/start':
            $session['state'] = 'idle';
            $session['temp']  = [];
            saveSession($userId, $session);

            sendMessage($chatId,
                "👋 Assalomu alaykum, *{$name}*! Do‘kon botimizga xush kelibsiz!\n\n" .
                "Katalogimizni ko‘zdan kechiring, mahsulotlarni savatchaga qo‘shing va bir necha soniyada buyurtma bering. 🛒\n\n" .
                "Boshlash uchun quyidagi tugmalardan foydalaning:",
                mainMenuKeyboard($userId)
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
            sendMessage($chatId, "🗑️ Savatcha tozalandi!", mainMenuKeyboard($userId));
            break;

        case '/admin':
            if ($userId === ADMIN_ID) {
                $session['state'] = 'idle';
                $session['temp']  = [];
                saveSession($userId, $session);
                sendMessage($chatId,
                    "⚙️ *Admin Panel*\n\nKerakli bo‘limni tanlang:",
                    adminMenuKeyboard()
                );
            } else {
                sendMessage($chatId, "⛔ Sizda admin huquqi mavjud emas.", mainMenuKeyboard($userId));
            }
            break;

        default:
            sendMessage($chatId,
                "Tushunarsiz buyruq. Quyidagi tugmalardan foydalaning yoki buyruqlarni kiriting:\n" .
                "• /shop — Mahsulotlar katalogi\n" .
                "• /cart — Savatchani ko‘rish\n" .
                "• /help — Yordam",
                mainMenuKeyboard($userId)
            );
    }
}

function handleAdminInput(int $chatId, int $userId, string $text, array &$session): void
{
    $state = $session['state'];

    if ($state === 'admin_add_name') {
        if ($text === '') {
            sendMessage($chatId, "⚠️ Mahsulot nomini matn ko‘rinishida kiriting:", adminCancelKeyboard());
            return;
        }
        $session['temp']['name'] = $text;
        $session['state']        = 'admin_add_price';
        saveSession($userId, $session);

        sendMessage($chatId,
            "💰 Mahsulot narxini *UZS* da kiriting (faqat raqam, masalan: `150000`):",
            adminCancelKeyboard()
        );
        return;
    }

    if ($state === 'admin_add_price') {
        $cleanPrice = preg_replace('/\s+/', '', $text);
        if (!is_numeric($cleanPrice) || (float)$cleanPrice <= 0) {
            sendMessage($chatId, "⚠️ Noto‘g‘ri narx! Faqat musbat raqam kiriting (masalan: `150000`):", adminCancelKeyboard());
            return;
        }
        $session['temp']['price'] = (float)$cleanPrice;
        $session['state']         = 'admin_add_emoji';
        saveSession($userId, $session);

        sendMessage($chatId,
            "🎨 Mahsulot uchun emoji yuboring (masalan: `📱` yoki `👟`).\nO‘tkazib yuborish uchun `-` belgisini yuboring:",
            adminCancelKeyboard()
        );
        return;
    }

    if ($state === 'admin_add_emoji') {
        $session['temp']['emoji'] = ($text === '-' || $text === '') ? '📦' : $text;
        $session['state']         = 'admin_add_desc';
        saveSession($userId, $session);

        sendMessage($chatId,
            "📝 Mahsulot haqida qisqacha tavsif (ma’lumot) kiriting:",
            adminCancelKeyboard()
        );
        return;
    }

    if ($state === 'admin_add_desc') {
        $temp    = $session['temp'];
        $product = addProduct(
            $temp['category'],
            $temp['name'],
            $temp['price'],
            $temp['emoji'],
            $text
        );

        $session['state'] = 'idle';
        $session['temp']  = [];
        saveSession($userId, $session);

        sendMessage($chatId,
            "✅ *Yangi mahsulot qo‘shildi!*\n\n" .
            "{$product['emoji']} *" . escapeMd($product['name']) . "*\n" .
            "💰 Narxi: *" . formatPrice($product['price']) . "*\n" .
            "📝 Tavsif: " . escapeMd($product['desc']),
            adminMenuKeyboard()
        );
        return;
    }

    if ($state === 'admin_broadcast') {
        if ($text === '') {
            sendMessage($chatId, "⚠️ Yuborish uchun matn kiriting:", adminCancelKeyboard());
            return;
        }

        $files = glob(DATA_DIR . 'user_*.json') ?: [];
        $sent  = 0;

        foreach ($files as $file) {
            if (preg_match('/user_(\d+)\.json$/', $file, $matches)) {
                $targetId = (int)$matches[1];
                sendMessage($targetId, "📢 *Yangilik!*\n\n" . $text);
                $sent++;
            }
        }

        $session['state'] = 'idle';
        $session['temp']  = [];
        saveSession($userId, $session);

        sendMessage($chatId,
            "✅ Xabar jami *{$sent} ta* foydalanuvchiga muvaffaqiyatli yuborildi!",
            adminMenuKeyboard()
        );
        return;
    }
}

function handleCallback(array $callbackQuery): void
{
    $callbackId = $callbackQuery['id'];
    $chatId     = $callbackQuery['message']['chat']['id'];
    $messageId  = $callbackQuery['message']['message_id'];
    $userId     = $callbackQuery['from']['id'];
    $userName   = $callbackQuery['from']['first_name'] ?? 'Mijoz';
    $data       = $callbackQuery['data'] ?? '';

    $session = loadSession($userId);

    // --- ADMIN CALLBACKLAR ---
    if (str_starts_with($data, 'admin_')) {
        if ($userId !== ADMIN_ID) {
            answerCallback($callbackId, '⛔ Sizda ruxsat yo‘q!', true);
            return;
        }

        if ($data === 'admin_menu' || $data === 'admin_cancel') {
            $session['state'] = 'idle';
            $session['temp']  = [];
            saveSession($userId, $session);

            answerCallback($callbackId);
            editMessage($chatId, $messageId,
                "⚙️ *Admin Panel*\n\nKerakli bo‘limni tanlang:",
                adminMenuKeyboard()
            );
            return;
        }

        if ($data === 'admin_add_product') {
            answerCallback($callbackId);
            editMessage($chatId, $messageId,
                "➕ *Yangi mahsulot qo‘shish*\n\n1-qadam: Mahsulot kategoriyasini tanlang:",
                categoryKeyboard('admin_cat_', true)
            );
            return;
        }

        if (str_starts_with($data, 'admin_cat_')) {
            $catKey = substr($data, 10);
            $session['state'] = 'admin_add_name';
            $session['temp']  = ['category' => $catKey];
            saveSession($userId, $session);

            answerCallback($callbackId);
            editMessage($chatId, $messageId,
                "✏️ Mahsulot nomini kiriting:",
                adminCancelKeyboard()
            );
            return;
        }

        if ($data === 'admin_del_list') {
            $products = getProducts();
            answerCallback($callbackId);

            if (empty($products)) {
                editMessage($chatId, $messageId,
                    "📭 Hozircha mahsulotlar mavjud emas.",
                    adminMenuKeyboard()
                );
                return;
            }

            editMessage($chatId, $messageId,
                "🗑️ *Mahsulot o‘chirish*\n\nO‘chirmoqchi bo‘lgan mahsulotingiz ustiga bosing:",
                adminDeleteProductsKeyboard($products)
            );
            return;
        }

        if (str_starts_with($data, 'admin_del_')) {
            $pid = (int) substr($data, 10);
            deleteProduct($pid);

            answerCallback($callbackId, '🗑️ Mahsulot o‘chirildi!');
            $products = getProducts();

            if (empty($products)) {
                editMessage($chatId, $messageId,
                    "📭 Barcha mahsulotlar o‘chirildi.",
                    adminMenuKeyboard()
                );
                return;
            }

            editMessage($chatId, $messageId,
                "🗑️ *Mahsulot o‘chirish*\n\nO‘chirmoqchi bo‘lgan mahsulotingiz ustiga bosing:",
                adminDeleteProductsKeyboard($products)
            );
            return;
        }

        if ($data === 'admin_orders') {
            answerCallback($callbackId);
            editMessage($chatId, $messageId,
                getAllOrdersText(),
                [[['text' => '⬅️ Admin menyu', 'callback_data' => 'admin_menu']]]
            );
            return;
        }

        if ($data === 'admin_stats') {
            answerCallback($callbackId);
            editMessage($chatId, $messageId,
                getStatsText(),
                [[['text' => '⬅️ Admin menyu', 'callback_data' => 'admin_menu']]]
            );
            return;
        }

        if ($data === 'admin_broadcast') {
            $session['state'] = 'admin_broadcast';
            saveSession($userId, $session);

            answerCallback($callbackId);
            editMessage($chatId, $messageId,
                "📢 *Barcha foydalanuvchilarga xabar yuborish*\n\nYubormoqchi bo‘lgan xabar matnini yozing:",
                adminCancelKeyboard()
            );
            return;
        }
    }

    // --- ODDIY FOYDALANUVCHI CALLBACKLARI ---
    if ($data === 'menu_main') {
        answerCallback($callbackId);
        editMessage($chatId, $messageId,
            "🏠 *Bosh menyu*\n\nNima qilmoqchisiz?",
            mainMenuKeyboard($userId)
        );
        return;
    }

    if ($data === 'menu_shop') {
        answerCallback($callbackId);
        editMessage($chatId, $messageId,
            "🛍️ *Kategoriyani tanlang*\n\nQaysi bo‘limdagi mahsulotlarni ko‘rmoqchisiz?",
            categoryKeyboard()
        );
        return;
    }

    if (str_starts_with($data, 'cat_')) {
        $catKey     = substr($data, 4);
        $categories = getCategories();
        $catName    = $categories[$catKey] ?? 'Mahsulotlar';
        $products   = getProductsByCategory($catKey);

        answerCallback($callbackId);

        if (empty($products)) {
            editMessage($chatId, $messageId,
                "🛍️ *{$catName}*\n\nBu bo‘limda hozircha mahsulotlar yo‘q.",
                categoryKeyboard()
            );
            return;
        }

        editMessage($chatId, $messageId,
            "🛍️ *{$catName}*\n\nBatafsil ko‘rish uchun mahsulotni tanlang:",
            productsKeyboard($products, $catKey)
        );
        return;
    }

    if (str_starts_with($data, 'product_')) {
        $productId = (int) substr($data, 8);
        $product   = getProductById($productId);

        if (!$product) {
            answerCallback($callbackId, '❌ Mahsulot topilmadi.', true);
            return;
        }

        $inCart   = $session['cart'][$productId] ?? 0;
        $cartNote = $inCart > 0 ? "\n\n🛒 _Savatchangizda ushbu mahsulotdan {$inCart} dona bor._" : '';

        answerCallback($callbackId);
        editMessage($chatId, $messageId,
            "{$product['emoji']} *" . escapeMd($product['name']) . "*\n\n" .
            "📝 " . escapeMd($product['desc']) . "\n\n" .
            "💰 Narxi: *" . formatPrice($product['price']) . "*" .
            $cartNote,
            productDetailKeyboard($productId)
        );
        return;
    }

    if (str_starts_with($data, 'add_')) {
        $productId = (int) substr($data, 4);
        $product   = getProductById($productId);

        if (!$product) {
            answerCallback($callbackId, '❌ Mahsulot topilmadi.', true);
            return;
        }

        cartAdd($session, $productId);
        saveSession($userId, $session);

        $qty = $session['cart'][$productId];
        answerCallback($callbackId, "✅ {$product['name']} savatga qo‘shildi! ({$qty} dona)");

        editMessage($chatId, $messageId,
            "{$product['emoji']} *" . escapeMd($product['name']) . "*\n\n" .
            "📝 " . escapeMd($product['desc']) . "\n\n" .
            "💰 Narxi: *" . formatPrice($product['price']) . "*\n\n" .
            "✅ _Savatga qo‘shildi! Jami: {$qty} dona._",
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
        $name      = $product ? $product['name'] : 'Mahsulot';

        cartRemove($session, $productId);
        saveSession($userId, $session);

        answerCallback($callbackId, "🗑️ {$name} olib tashlandi.");
        editMessage($chatId, $messageId,
            cartSummaryText($session),
            cartKeyboard($session)
        );
        return;
    }

    if ($data === 'clear_cart') {
        cartClear($session);
        saveSession($userId, $session);

        answerCallback($callbackId, '🗑️ Savatcha tozalandi!');
        editMessage($chatId, $messageId,
            "🛒 *Savatchangiz*\n\nSavatchangiz hozir bo‘sh.",
            cartKeyboard($session)
        );
        return;
    }

    if ($data === 'checkout') {
        if (empty($session['cart'])) {
            answerCallback($callbackId, '🛒 Savatchangiz bo‘sh!', true);
            return;
        }

        $orderNumber = strtoupper(substr(md5(uniqid((string)$userId, true)), 0, 8));
        $total       = cartTotal($session);
        $summary     = cartSummaryText($session);

        saveOrder($userId, $userName, $orderNumber, $session['cart'], $total);
        cartClear($session);
        saveSession($userId, $session);

        // Adminga yangi buyurtma haqida xabar yuborish
        if ($userId !== ADMIN_ID) {
            sendMessage(ADMIN_ID,
                "🔔 *Yangi buyurtma tushdi!*\n\n" .
                "👤 Mijoz: " . escapeMd($userName) . " (`{$userId}`)\n" .
                "🧾 Buyurtma raqami: `#{$orderNumber}`\n\n" .
                "{$summary}"
            );
        }

        answerCallback($callbackId, '🎉 Buyurtma qabul qilindi!');
        editMessage($chatId, $messageId,
            "🎉 *Buyurtmangiz tasdiqlandi!*\n\n" .
            "{$summary}\n\n" .
            "🧾 Buyurtma raqami: `#{$orderNumber}`\n" .
            "📦 Holati: *Ko‘rib chiqilmoqda*\n\n" .
            "_Xaridingiz uchun rahmat! Tez orada siz bilan bog‘lanamiz._ 🚚",
            [
                [['text' => '🛍️ Xaridni davom ettirish', 'callback_data' => 'menu_shop']],
                [['text' => '📦 Buyurtmalarim',          'callback_data' => 'menu_orders']],
                [['text' => '🏠 Bosh menyu',             'callback_data' => 'menu_main']],
            ]
        );
        return;
    }

    if ($data === 'menu_orders') {
        answerCallback($callbackId);
        $ordersText = getOrdersText($userId);
        editMessage($chatId, $messageId,
            $ordersText,
            [[['text' => '🏠 Bosh menyu', 'callback_data' => 'menu_main']]]
        );
        return;
    }

    if ($data === 'menu_help') {
        answerCallback($callbackId);
        editMessage($chatId, $messageId,
            getHelpText(),
            [[['text' => '🏠 Bosh menyu', 'callback_data' => 'menu_main']]]
        );
        return;
    }

    answerCallback($callbackId, '⚠️ Noma’lum amal.');
}

function showCategories(int $chatId): void
{
    sendMessage($chatId,
        "🛍️ *Kategoriyani tanlang*\n\nQaysi bo‘limdagi mahsulotlarni ko‘rmoqchisiz?",
        categoryKeyboard()
    );
}

function showCart(int $chatId, array $session): void
{
    sendMessage($chatId, cartSummaryText($session), cartKeyboard($session));
}

function showHelp(int $chatId): void
{
    sendMessage($chatId, getHelpText(), [[['text' => '🏠 Bosh menyu', 'callback_data' => 'menu_main']]]);
}

function getHelpText(): string
{
    return
        "ℹ️ *Do‘kon boti bo‘yicha yordam*\n\n" .
        "Bot imkoniyatlari:\n\n" .
        "🛍️ *Do‘kon* — Kategoriyalar bo‘yicha mahsulotlarni ko‘rish\n" .
        "🛒 *Savatcham* — Tanlangan mahsulotlarni ko‘rish va buyurtma berish\n" .
        "📦 *Buyurtmalarim* — Oldingi buyurtmalar tarixini tekshirish\n\n" .
        "*Tezkor buyruqlar:*\n" .
        "/start — Bosh menyuni ochish\n" .
        "/shop — Mahsulotlar katalogi\n" .
        "/cart — Savatchani ochish\n" .
        "/clear — Savatchani tozalash\n" .
        "/help — Yordam oynasi\n\n" .
        "_Kerakli bo‘limni tanlash uchun tugmalarni bosing. Yoqimli xarid tilaymiz!_ 🎉";
}

function ordersFile(int $userId): string
{
    return DATA_DIR . "orders_{$userId}.json";
}

function saveOrder(int $userId, string $userName, string $orderNumber, array $cart, float $total): void
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
        'user_id'      => $userId,
        'user_name'    => $userName,
        'order_number' => $orderNumber,
        'date'         => date('Y-m-d H:i:s'),
        'items'        => $items,
        'total'        => $total,
        'status'       => 'Ko‘rib chiqilmoqda',
    ];

    file_put_contents($file, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getOrdersText(int $userId): string
{
    $file   = ordersFile($userId);
    $orders = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

    if (empty($orders)) {
        return "📦 *Buyurtmalarim*\n\nSiz hali birorta ham buyurtma bermagansiz.\nXaridni boshlang! 🛍️";
    }

    $text   = "📦 *Buyurtmalarim*\n\n";
    $recent = array_reverse($orders);
    $shown  = array_slice($recent, 0, 5);

    foreach ($shown as $order) {
        $text .= "🧾 Buyurtma `#{$order['order_number']}`\n";
        $text .= "📅 Sana: {$order['date']}\n";
        $text .= "📌 Holati: *{$order['status']}*\n";
        $text .= "💰 Jami: *" . formatPrice($order['total']) . "*\n";
        $text .= "─────────────\n";
    }

    if (count($orders) > 5) {
        $text .= "\n_Jami " . count($orders) . " ta buyurtmadan oxirgi 5 tasi ko‘rsatilmoqda._";
    }

    return $text;
}

function getAllOrdersText(): string
{
    $files     = glob(DATA_DIR . 'orders_*.json') ?: [];
    $allOrders = [];

    foreach ($files as $file) {
        $userOrders = json_decode(file_get_contents($file), true);
        if (is_array($userOrders)) {
            foreach ($userOrders as $ord) {
                $allOrders[] = $ord;
            }
        }
    }

    if (empty($allOrders)) {
        return "📦 *Barcha buyurtmalar*\n\nHozircha hech qanday buyurtma kelib tushmagan.";
    }

    usort($allOrders, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    $shown = array_slice($allOrders, 0, 8);

    $text = "📦 *Oxirgi buyurtmalar (jami: " . count($allOrders) . " ta)*\n\n";
    foreach ($shown as $order) {
        $client = escapeMd($order['user_name'] ?? 'Mijoz');
        $uid    = $order['user_id'] ?? '-';
        $text  .= "🧾 `#{$order['order_number']}` — *{$client}* (`{$uid}`)\n";
        $text  .= "📅 {$order['date']} | 💰 *" . formatPrice($order['total']) . "*\n";
        $text  .= "─────────────\n";
    }

    return $text;
}

function getStatsText(): string
{
    $userFiles  = glob(DATA_DIR . 'user_*.json') ?: [];
    $orderFiles = glob(DATA_DIR . 'orders_*.json') ?: [];
    $products   = getProducts();

    $totalOrders  = 0;
    $totalRevenue = 0;

    foreach ($orderFiles as $file) {
        $orders = json_decode(file_get_contents($file), true);
        if (is_array($orders)) {
            $totalOrders += count($orders);
            foreach ($orders as $ord) {
                $totalRevenue += (float)($ord['total'] ?? 0);
            }
        }
    }

    return
        "📊 *Bot statistikasi*\n\n" .
        "👥 Foydalanuvchilar soni: *" . count($userFiles) . " ta*\n" .
        "🛍️ Mahsulotlar soni: *" . count($products) . " ta*\n" .
        "📦 Jami buyurtmalar: *" . $totalOrders . " ta*\n" .
        "💰 Umumiy savdo hajmi: *" . formatPrice($totalRevenue) . "*";
}
