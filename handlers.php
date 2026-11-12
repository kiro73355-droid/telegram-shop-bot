<?php

function handleMessage(array $message): void
{
    $chatId   = $message['chat']['id'];
    $text     = trim($message['text'] ?? '');
    $userId   = $message['from']['id'];
    $rawName  = $message['from']['first_name'] ?? 'Foydalanuvchi';
    $safeName = escapeMd($rawName);

    $session = loadSession($userId);
    $state   = $session['state'] ?? 'idle';

    // /start har qanday holatni bekor qilib bosh menyuni ochadi
    if (strtolower($text) === '/start') {
        $session['state'] = 'idle';
        $session['temp']  = [];
        saveSession($userId, $session);

        sendMessage($chatId,
            "👋 Assalomu alaykum, *{$safeName}*! Do‘kon botimizga xush kelibsiz!\n\n" .
            "Katalogimizni ko‘zdan kechiring, mahsulotlarni savatchaga qo‘shing va bir necha soniyada buyurtma bering. 🛒\n\n" .
            "Boshlash uchun quyidagi tugmalardan foydalaning:",
            mainMenuKeyboard($userId),
            'Markdown',
            true
        );
        return;
    }

    // Admin ko'p bosqichli jarayonlari
    if ($userId === ADMIN_ID && (str_starts_with($state, 'admin_') || str_starts_with($state, 'aedit_')) && !str_starts_with($text, '/')) {
        handleAdminInput($chatId, $userId, $message, $session);
        return;
    }

    // Mijoz qidiruv yoki promo-kod kiritish jarayoni
    if ($state === 'user_search' && !str_starts_with($text, '/')) {
        if ($text === '') {
            sendMessage($chatId, "🔍 Qidirish uchun mahsulot nomini yozing:", [[['text' => '❌ Bekor qilish', 'callback_data' => 'menu_main']]]);
            return;
        }
        $results = searchProducts($text);
        if (empty($results)) {
            sendMessage($chatId,
                "😔 «*" . escapeMd($text) . "*» bo‘yicha hech narsa topilmadi.\nBoshqa so‘z yozib ko‘ring yoki menyuga qayting:",
                [[['text' => '🏠 Bosh menyu', 'callback_data' => 'menu_main']]]
            );
            return;
        }
        $session['state'] = 'idle';
        saveSession($userId, $session);

        sendMessage($chatId,
            "🔍 *Qidiruv natijalari («" . escapeMd($text) . "»):*\n\nTopilgan mahsulotlar (" . count($results) . " ta):",
            productsKeyboard($results)
        );
        return;
    }

    if ($state === 'user_promo' && !str_starts_with($text, '/')) {
        $promo = getPromoByCode($text);
        if (!$promo) {
            sendMessage($chatId,
                "❌ Bunday promo-kod topilmadi yoki muddati tugagan.\nQaytadan kiriting yoki savatchaga qayting:",
                [[['text' => '⬅️ Savatchaga qaytish', 'callback_data' => 'menu_cart']]]
            );
            return;
        }

        $session['promo'] = $promo['code'];
        $session['state'] = 'idle';
        saveSession($userId, $session);

        sendMessage($chatId,
            "🎉 *«{$promo['code']}»* promo-kodi qo‘llandi! (*-{$promo['percent']}%* chegirma)\n\n" .
            cartSummaryText($session),
            cartKeyboard($session)
        );
        return;
    }

    // Mijoz buyurtma berish jarayoni (telefon, manzil, chek)
    if (str_starts_with($state, 'checkout_') && !str_starts_with($text, '/')) {
        handleCheckoutInput($chatId, $userId, $rawName, $message, $session);
        return;
    }

    switch (strtolower($text)) {
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

function handleCheckoutInput(int $chatId, int $userId, string $userName, array $message, array &$session): void
{
    $state = $session['state'];
    $text  = trim($message['text'] ?? '');

    if ($text === '❌ Bekor qilish') {
        $session['state'] = 'idle';
        $session['temp']  = [];
        saveSession($userId, $session);

        sendMessage($chatId, "❌ Buyurtma berish bekor qilindi.", [], 'Markdown', true);
        sendMessage($chatId, "🏠 *Bosh menyu*\n\nNima qilmoqchisiz?", mainMenuKeyboard($userId));
        return;
    }

    // 1-qadam: Telefon raqamni qabul qilish
    if ($state === 'checkout_phone') {
        $phone = '';
        if (!empty($message['contact']['phone_number'])) {
            $phone = $message['contact']['phone_number'];
            if (!str_starts_with($phone, '+')) {
                $phone = '+' . $phone;
            }
        } elseif ($text !== '') {
            $digits = preg_replace('/[^\d+]/', '', $text);
            if (strlen(preg_replace('/\D/', '', $digits)) >= 7) {
                $phone = $digits;
            }
        }

        if ($phone === '') {
            sendReplyKeyboard($chatId,
                "⚠️ Noto‘g‘ri raqam! Pastdagi *«📱 Telefon raqamni yuborish»* tugmasini bosing yoki raqamingizni to‘g‘ri kiriting (masalan: `+998901234567`):",
                [
                    [['text' => '📱 Telefon raqamni yuborish', 'request_contact' => true]],
                    [['text' => '❌ Bekor qilish']],
                ]
            );
            return;
        }

        $session['temp']['phone'] = $phone;
        $session['state']         = 'checkout_address';
        saveSession($userId, $session);

        sendReplyKeyboard($chatId,
            "📍 *Yetkazib berish manzilini kiriting:*\n\n" .
            "Tuman, ko‘cha, uy raqamingizni yozib yuboring yoki pastdagi *«📍 Lokatsiyani yuborish»* tugmasini bosing:",
            [
                [['text' => '📍 Lokatsiyani yuborish', 'request_location' => true]],
                [['text' => '❌ Bekor qilish']],
            ]
        );
        return;
    }

    // 2-qadam: Manzil yoki lokatsiyani qabul qilish
    if ($state === 'checkout_address') {
        $address = '';
        $mapLink = '';

        if (!empty($message['location'])) {
            $lat     = $message['location']['latitude'];
            $lon     = $message['location']['longitude'];
            $address = "GPS Lokatsiya ({$lat}, {$lon})";
            $mapLink = "https://maps.google.com/?q={$lat},{$lon}";
        } elseif ($text !== '') {
            $address = $text;
        }

        if ($address === '') {
            sendReplyKeyboard($chatId,
                "⚠️ Iltimos, yetkazib berish manzilini matn ko‘rinishida yozing yoki lokatsiya yuboring:",
                [
                    [['text' => '📍 Lokatsiyani yuborish', 'request_location' => true]],
                    [['text' => '❌ Bekor qilish']],
                ]
            );
            return;
        }

        $session['temp']['address']  = $address;
        $session['temp']['map_link'] = $mapLink;
        $session['state']            = 'checkout_payment';
        saveSession($userId, $session);

        sendMessage($chatId, "✅ Manzil saqlandi.", [], 'Markdown', true);

        $total = cartTotal($session);
        sendMessage($chatId,
            "💳 *To‘lov usulini tanlang:*\n\n" .
            "💰 Jami to‘lov summasi: *" . formatPrice($total) . "*",
            paymentMethodKeyboard()
        );
        return;
    }

    // 3-qadam: Karta orqali to'lov chekini qabul qilish
    if ($state === 'checkout_receipt') {
        $receiptFileId = '';
        if (!empty($message['photo'])) {
            $lastPhoto     = end($message['photo']);
            $receiptFileId = $lastPhoto['file_id'] ?? '';
        } elseif (!empty($message['document']['file_id'])) {
            $receiptFileId = $message['document']['file_id'];
        }

        if ($receiptFileId === '') {
            sendMessage($chatId,
                "📸 Iltimos, to‘lov amalga oshirilganligi haqidagi *chek rasmini (skrinshotini)* yuboring:",
                [[['text' => '❌ Bekor qilish', 'callback_data' => 'checkout_cancel']]]
            );
            return;
        }

        finalizeOrder($chatId, $userId, $userName, $session, 'Karta orqali', 'Chek yuborilgan', $receiptFileId);
        return;
    }
}

function finalizeOrder(
    int $chatId,
    int $userId,
    string $userName,
    array &$session,
    string $paymentMethod,
    string $paymentStatus,
    string $receiptPhoto = '',
    int $editMessageId = 0
): void {
    if (empty($session['cart'])) {
        sendMessage($chatId, "🛒 Savatchangiz bo‘sh!", mainMenuKeyboard($userId));
        return;
    }

    $orderNumber = strtoupper(substr(md5(uniqid((string)$userId, true)), 0, 8));
    $calc        = cartBreakdown($session);
    $total       = $calc['total'];
    $summary     = cartSummaryText($session);
    $phone       = $session['temp']['phone'] ?? 'Ko‘rsatilmagan';
    $address     = $session['temp']['address'] ?? 'Ko‘rsatilmagan';
    $mapLink     = $session['temp']['map_link'] ?? '';

    saveOrder(
        $userId,
        $userName,
        $orderNumber,
        $session['cart'],
        $calc,
        $phone,
        $address,
        $mapLink,
        $paymentMethod,
        $paymentStatus,
        $receiptPhoto
    );

    cartClear($session);
    $session['state'] = 'idle';
    $session['temp']  = [];
    saveSession($userId, $session);

    // Adminga yangi buyurtma haqida xabar yuborish
    $adminText =
        "🔔 *Yangi buyurtma tushdi!*\n\n" .
        "🧾 Buyurtma: `#{$orderNumber}`\n" .
        "👤 Mijoz: " . escapeMd($userName) . " (`{$userId}`)\n" .
        "📞 Telefon: `" . escapeMd($phone) . "`\n" .
        "📍 Manzil: " . escapeMd($address) . ($mapLink ? "\n🗺️ Xarita: {$mapLink}" : '') . "\n" .
        "💳 To‘lov: *{$paymentMethod}* ({$paymentStatus})\n\n" .
        "{$summary}";

    $adminKb = orderStatusKeyboard($orderNumber, $receiptPhoto !== '', false);

    if ($receiptPhoto !== '') {
        $res = sendPhoto(ADMIN_ID, $receiptPhoto, $adminText, $adminKb);
        if (empty($res['ok'])) {
            sendMessage(ADMIN_ID, $adminText, $adminKb);
        }
    } else {
        sendMessage(ADMIN_ID, $adminText, $adminKb);
    }

    // Mijozga tasdiqlash xabari
    $clientText =
        "🎉 *Buyurtmangiz qabul qilindi!*\n\n" .
        "{$summary}\n\n" .
        "🧾 Buyurtma raqami: `#{$orderNumber}`\n" .
        "📞 Telefon: `" . escapeMd($phone) . "`\n" .
        "📍 Manzil: " . escapeMd($address) . "\n" .
        "💳 To‘lov usuli: *{$paymentMethod}*\n" .
        "📦 Holati: *Ko‘rib chiqilmoqda*\n\n" .
        "_Xaridingiz uchun rahmat! Buyurtma holati o‘zgarganda sizga xabar beramiz._ 🚚";

    $clientKb = [
        [['text' => '🛍️ Xaridni davom ettirish', 'callback_data' => 'menu_shop']],
        [['text' => '📦 Buyurtmalarim',          'callback_data' => 'menu_orders']],
        [['text' => '🏠 Bosh menyu',             'callback_data' => 'menu_main']],
    ];

    if ($editMessageId > 0) {
        editMessage($chatId, $editMessageId, $clientText, $clientKb);
    } else {
        sendMessage($chatId, $clientText, $clientKb);
    }
}

function handleAdminInput(int $chatId, int $userId, array $message, array &$session): void
{
    $state = $session['state'];
    $text  = trim($message['text'] ?? '');

    // Yangi mahsulot qo'shish bosqichlari
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
        if ($text === '') {
            sendMessage($chatId, "⚠️ Mahsulot tavsifini matn ko‘rinishida kiriting:", adminCancelKeyboard());
            return;
        }
        $session['temp']['desc'] = $text;
        $session['state']        = 'admin_add_photo';
        saveSession($userId, $session);

        sendMessage($chatId,
            "🖼️ Mahsulot rasmini yuboring.\nRasmsiz saqlash uchun `-` belgisini yuboring:",
            adminCancelKeyboard()
        );
        return;
    }

    if ($state === 'admin_add_photo') {
        $photo = '';
        if (!empty($message['photo'])) {
            $lastPhoto = end($message['photo']);
            $photo     = $lastPhoto['file_id'] ?? '';
        } elseif ($text !== '-' && str_starts_with($text, 'http')) {
            $photo = $text;
        } elseif ($text !== '-') {
            sendMessage($chatId,
                "⚠️ Iltimos, mahsulot rasmini yuboring yoki rasmsiz saqlash uchun `-` belgisini yozing:",
                adminCancelKeyboard()
            );
            return;
        }

        $temp    = $session['temp'];
        $product = addProduct(
            $temp['category'],
            $temp['name'],
            $temp['price'],
            $temp['emoji'],
            $temp['desc'],
            $photo
        );

        $session['state'] = 'idle';
        $session['temp']  = [];
        saveSession($userId, $session);

        $confirmText =
            "✅ *Yangi mahsulot qo‘shildi!*\n\n" .
            "{$product['emoji']} *" . escapeMd($product['name']) . "*\n" .
            "💰 Narxi: *" . formatPrice($product['price']) . "*\n" .
            "📝 Tavsif: " . escapeMd($product['desc']);

        if ($photo !== '') {
            sendPhoto($chatId, $photo, $confirmText, adminMenuKeyboard());
        } else {
            sendMessage($chatId, $confirmText, adminMenuKeyboard());
        }
        return;
    }

    // Mavjud mahsulot maydonlarini tahrirlash (name, price, desc, photo)
    if (str_starts_with($state, 'aedit_')) {
        $field = substr($state, 6); // name, price, desc, photo
        $pid   = (int)($session['temp']['product_id'] ?? 0);

        if ($field === 'price') {
            $cleanPrice = preg_replace('/\s+/', '', $text);
            if (!is_numeric($cleanPrice) || (float)$cleanPrice <= 0) {
                sendMessage($chatId, "⚠️ Faqat musbat raqam kiriting (masalan: `180000`):", adminCancelKeyboard());
                return;
            }
            updateProductField($pid, 'price', (float)$cleanPrice);
        } elseif ($field === 'photo') {
            $photo = '';
            if (!empty($message['photo'])) {
                $lastPhoto = end($message['photo']);
                $photo     = $lastPhoto['file_id'] ?? '';
            } elseif ($text === '-') {
                $photo = '';
            } elseif (str_starts_with($text, 'http')) {
                $photo = $text;
            } else {
                sendMessage($chatId, "⚠️ Rasm yuboring yoki o‘chirish uchun `-` yozing:", adminCancelKeyboard());
                return;
            }
            updateProductField($pid, 'photo', $photo);
        } else {
            if ($text === '') {
                sendMessage($chatId, "⚠️ Yangi qiymatni matn ko‘rinishida yuboring:", adminCancelKeyboard());
                return;
            }
            updateProductField($pid, $field, $text);
        }

        $session['state'] = 'idle';
        $session['temp']  = [];
        saveSession($userId, $session);

        $p = getProductById($pid);
        sendMessage($chatId,
            "✅ *Mahsulot yangilandi!*\n\n" .
            "{$p['emoji']} *" . escapeMd($p['name']) . "*\n" .
            "💰 Narxi: *" . formatPrice($p['price']) . "*\n" .
            "📝 Tavsif: " . escapeMd($p['desc']) . "\n" .
            "🖼️ Rasm: " . (!empty($p['photo']) ? 'Biriktirilgan ✅' : 'Yo‘q'),
            adminEditProductFieldsKeyboard($pid)
        );
        return;
    }

    // Yangi kategoriya qo'shish
    if ($state === 'admin_add_cat_name') {
        if ($text === '') {
            sendMessage($chatId, "⚠️ Kategoriya nomini kiriting (masalan: `⌚ Aksessuarlar`):", adminCancelKeyboard());
            return;
        }
        addCategory($text);
        $session['state'] = 'idle';
        $session['temp']  = [];
        saveSession($userId, $session);

        sendMessage($chatId,
            "✅ Yangi kategoriya qo‘shildi: *" . escapeMd($text) . "*",
            adminMenuKeyboard()
        );
        return;
    }

    // Promo-kod qo'shish
    if ($state === 'admin_promo_code') {
        $code = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($text));
        if (strlen($code) < 2 || strlen($code) > 15) {
            sendMessage($chatId, "⚠️ Promo-kod 2–15 ta lotin harfi yoki raqamdan iborat bo‘lsin (masalan: `SALE10`):", adminCancelKeyboard());
            return;
        }
        $session['temp']['code'] = $code;
        $session['state']        = 'admin_promo_percent';
        saveSession($userId, $session);

        sendMessage($chatId,
            "💯 *{$code}* promo-kodi uchun chegirma foizini kiriting (`1` dan `99` gacha):",
            adminCancelKeyboard()
        );
        return;
    }

    if ($state === 'admin_promo_percent') {
        $pct = (int)preg_replace('/\D/', '', $text);
        if ($pct < 1 || $pct > 99) {
            sendMessage($chatId, "⚠️ `1` dan `99` gacha raqam kiriting (masalan: `15`):", adminCancelKeyboard());
            return;
        }
        $code = $session['temp']['code'] ?? 'PROMO';
        addPromoCode($code, $pct);

        $session['state'] = 'idle';
        $session['temp']  = [];
        saveSession($userId, $session);

        sendMessage($chatId,
            "✅ Promo-kod yaratildi: *{$code}* (*-{$pct}%* chegirma)",
            adminMenuKeyboard()
        );
        return;
    }

    // Dostavka sozlamalari
    if ($state === 'admin_set_delfee') {
        $clean = preg_replace('/\s+/', '', $text);
        if (!is_numeric($clean) || (float)$clean < 0) {
            sendMessage($chatId, "⚠️ Musbat raqam yoki bepul qilish uchun `0` kiriting:", adminCancelKeyboard());
            return;
        }
        saveStoreSettings(['delivery_fee' => (float)$clean]);
        $session['state'] = 'idle';
        saveSession($userId, $session);

        sendMessage($chatId,
            "✅ Yetkazib berish narxi yangilandi: *" . formatPrice((float)$clean) . "*",
            adminMenuKeyboard()
        );
        return;
    }

    if ($state === 'admin_set_freedel') {
        $clean = preg_replace('/\s+/', '', $text);
        if (!is_numeric($clean) || (float)$clean < 0) {
            sendMessage($chatId, "⚠️ Summani raqamda kiriting (o‘chirish uchun `0`):", adminCancelKeyboard());
            return;
        }
        saveStoreSettings(['free_delivery_min' => (float)$clean]);
        $session['state'] = 'idle';
        saveSession($userId, $session);

        $msg = (float)$clean > 0
            ? "✅ *" . formatPrice((float)$clean) . "* dan yuqori buyurtmalar uchun dostavka bepul qilindi!"
            : "✅ Bepul dostavka chegarasi o‘chirildi.";
        sendMessage($chatId, $msg, adminMenuKeyboard());
        return;
    }

    // Karta sozlamalari
    if ($state === 'admin_set_card') {
        if ($text === '') {
            sendMessage($chatId, "⚠️ Karta raqamini kiriting:", adminCancelKeyboard());
            return;
        }
        $session['temp']['card'] = $text;
        $session['state']        = 'admin_set_holder';
        saveSession($userId, $session);

        sendMessage($chatId,
            "👤 Karta egasining ism-familiyasini kiriting (masalan: `BEXRUZ XURSANDOV`):",
            adminCancelKeyboard()
        );
        return;
    }

    if ($state === 'admin_set_holder') {
        if ($text === '') {
            sendMessage($chatId, "⚠️ Karta egasi ismini kiriting:", adminCancelKeyboard());
            return;
        }
        $card = $session['temp']['card'] ?? '';
        saveStoreSettings([
            'card'   => $card,
            'holder' => $text,
        ]);

        $session['state'] = 'idle';
        $session['temp']  = [];
        saveSession($userId, $session);

        sendMessage($chatId,
            "✅ *To‘lov kartasi yangilandi!*\n\n" .
            "💳 Karta: `" . escapeMd($card) . "`\n" .
            "👤 Egasi: *" . escapeMd($text) . "*",
            adminMenuKeyboard()
        );
        return;
    }

    // Aloqa ma'lumotlarini yangilash
    if ($state === 'admin_set_contact') {
        if ($text === '') {
            sendMessage($chatId, "⚠️ Aloqa ma’lumotlarini kiriting:", adminCancelKeyboard());
            return;
        }
        saveStoreSettings(['contact_info' => $text]);
        $session['state'] = 'idle';
        saveSession($userId, $session);

        sendMessage($chatId, "✅ Aloqa ma’lumotlari muvaffaqiyatli yangilandi!", adminMenuKeyboard());
        return;
    }

    // Barcha foydalanuvchilarga xabar yuborish
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
    $hasPhoto   = !empty($callbackQuery['message']['photo']);
    $userId     = $callbackQuery['from']['id'];
    $userName   = $callbackQuery['from']['first_name'] ?? 'Mijoz';
    $data       = $callbackQuery['data'] ?? '';

    $session = loadSession($userId);

    $renderText = function (string $text, array $kb = []) use ($chatId, $messageId, $hasPhoto): void {
        if ($hasPhoto) {
            deleteMessage($chatId, $messageId);
            sendMessage($chatId, $text, $kb);
        } else {
            editMessage($chatId, $messageId, $text, $kb);
        }
    };

    // --- ADMIN CALLBACKLAR ---
    if (str_starts_with($data, 'admin_') || str_starts_with($data, 'ord_st_') || str_starts_with($data, 'aedit_')) {
        if ($userId !== ADMIN_ID) {
            answerCallback($callbackId, '⛔ Sizda ruxsat yo‘q!', true);
            return;
        }

        if ($data === 'admin_menu' || $data === 'admin_cancel') {
            $session['state'] = 'idle';
            $session['temp']  = [];
            saveSession($userId, $session);

            answerCallback($callbackId);
            $renderText("⚙️ *Admin Panel*\n\nKerakli bo‘limni tanlang:", adminMenuKeyboard());
            return;
        }

        if ($data === 'admin_add_product') {
            $categories = getCategories();
            if (empty($categories)) {
                answerCallback($callbackId, '⚠️ Avval kategoriya yarating!', true);
                return;
            }
            answerCallback($callbackId);
            $renderText(
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
            $renderText("✏️ Mahsulot nomini kiriting:", adminCancelKeyboard());
            return;
        }

        // Mahsulotni tahrirlash ro'yxati va maydonlari
        if ($data === 'admin_edit_list') {
            $products = getProducts();
            answerCallback($callbackId);

            if (empty($products)) {
                $renderText("📭 Hozircha mahsulotlar mavjud emas.", adminMenuKeyboard());
                return;
            }

            $renderText(
                "✏️ *Mahsulotni tahrirlash*\n\nTahrirlamoqchi bo‘lgan mahsulotni tanlang:",
                adminProductsSelectKeyboard($products, 'admin_edit_')
            );
            return;
        }

        if (str_starts_with($data, 'admin_edit_')) {
            $pid     = (int) substr($data, 11);
            $product = getProductById($pid);
            if (!$product) {
                answerCallback($callbackId, '❌ Mahsulot topilmadi.', true);
                return;
            }

            answerCallback($callbackId);
            $renderText(
                "✏️ *Tahrirlash: {$product['emoji']} " . escapeMd($product['name']) . "*\n\n" .
                "💰 Narxi: *" . formatPrice($product['price']) . "*\n" .
                "📝 Tavsif: " . escapeMd($product['desc']) . "\n" .
                "🖼️ Rasm: " . (!empty($product['photo']) ? 'Biriktirilgan ✅' : 'Yo‘q') . "\n\n" .
                "Nimani o‘zgartirmoqchisiz?",
                adminEditProductFieldsKeyboard($pid)
            );
            return;
        }

        if (str_starts_with($data, 'aedit_')) {
            $parts = explode('_', $data); // aedit, {field}, {pid}
            $field = $parts[1] ?? '';
            $pid   = (int)($parts[2] ?? 0);

            $prompts = [
                'name'  => "✏️ Yangi mahsulot nomini yuboring:",
                'price' => "💰 Yangi narxni *UZS* da yuboring (masalan: `180000`):",
                'desc'  => "📝 Yangi tavsif matnini yuboring:",
                'photo' => "🖼️ Yangi rasm yuboring (yoki rasmni o‘chirish uchun `-` yozing):",
            ];

            if (!isset($prompts[$field])) {
                answerCallback($callbackId, '⚠️ Noto‘g‘ri tanlov.', true);
                return;
            }

            $session['state'] = "aedit_{$field}";
            $session['temp']  = ['product_id' => $pid];
            saveSession($userId, $session);

            answerCallback($callbackId);
            $renderText($prompts[$field], adminCancelKeyboard());
            return;
        }

        // Kategoriyalar boshqaruvi
        if ($data === 'admin_categories') {
            $categories = getCategories();
            $list = '';
            foreach ($categories as $cName) {
                $list .= "• " . escapeMd($cName) . "\n";
            }
            if ($list === '') {
                $list = "_Kategoriyalar yo‘q_\n";
            }

            answerCallback($callbackId);
            $renderText(
                "📂 *Kategoriyalarni boshqarish*\n\nMavjud kategoriyalar:\n{$list}",
                [
                    [
                        ['text' => '➕ Kategoriya qo‘shish',  'callback_data' => 'admin_add_cat'],
                        ['text' => '🗑️ Kategoriya o‘chirish', 'callback_data' => 'admin_del_cat_list'],
                    ],
                    [
                        ['text' => '⬅️ Admin menyu', 'callback_data' => 'admin_menu'],
                    ],
                ]
            );
            return;
        }

        if ($data === 'admin_add_cat') {
            $session['state'] = 'admin_add_cat_name';
            saveSession($userId, $session);

            answerCallback($callbackId);
            $renderText(
                "➕ *Yangi kategoriya qo‘shish*\n\nKategoriya nomini emoji bilan kiriting (masalan: `⌚ Aksessuarlar`):",
                adminCancelKeyboard()
            );
            return;
        }

        if ($data === 'admin_del_cat_list') {
            $categories = getCategories();
            answerCallback($callbackId);

            if (empty($categories)) {
                $renderText("📭 O‘chirish uchun kategoriya yo‘q.", adminMenuKeyboard());
                return;
            }

            $rows = [];
            foreach ($categories as $key => $cName) {
                $rows[] = [['text' => "🗑️ {$cName}", 'callback_data' => "admin_delcat_{$key}"]];
            }
            $rows[] = [['text' => '⬅️ Orqaga', 'callback_data' => 'admin_categories']];

            $renderText("🗑️ *Kategoriya o‘chirish*\n\nO‘chirmoqchi bo‘lgan kategoriyani tanlang:", $rows);
            return;
        }

        if (str_starts_with($data, 'admin_delcat_')) {
            $key = substr($data, 13);
            deleteCategory($key);
            answerCallback($callbackId, '🗑️ Kategoriya o‘chirildi!');

            $categories = getCategories();
            if (empty($categories)) {
                $renderText("📭 Barcha kategoriyalar o‘chirildi.", adminMenuKeyboard());
                return;
            }

            $rows = [];
            foreach ($categories as $k => $cName) {
                $rows[] = [['text' => "🗑️ {$cName}", 'callback_data' => "admin_delcat_{$k}"]];
            }
            $rows[] = [['text' => '⬅️ Orqaga', 'callback_data' => 'admin_categories']];

            $renderText("🗑️ *Kategoriya o‘chirish*\n\nO‘chirmoqchi bo‘lgan kategoriyani tanlang:", $rows);
            return;
        }

        // Promo-kodlar boshqaruvi
        if ($data === 'admin_promos') {
            $promos = getPromoCodes();
            $list   = '';
            $rows   = [
                [['text' => '➕ Yangi promo-kod qo‘shish', 'callback_data' => 'admin_add_promo']],
            ];

            foreach ($promos as $p) {
                $list .= "• `{$p['code']}` — *{$p['percent']}%* chegirma\n";
                $rows[] = [['text' => "🗑️ O‘chirish: {$p['code']} (-{$p['percent']}%)", 'callback_data' => "admin_delpromo_{$p['code']}"]];
            }
            if ($list === '') {
                $list = "_Hozircha faol promo-kodlar yo‘q._\n";
            }
            $rows[] = [['text' => '⬅️ Admin menyu', 'callback_data' => 'admin_menu']];

            answerCallback($callbackId);
            $renderText("🎟️ *Promo-kodlar boshqaruvi*\n\n{$list}", $rows);
            return;
        }

        if ($data === 'admin_add_promo') {
            $session['state'] = 'admin_promo_code';
            saveSession($userId, $session);

            answerCallback($callbackId);
            $renderText(
                "🎟️ *Yangi promo-kod yaratish*\n\nPromo-kod so‘zini kiriting (masalan: `SALE10` yoki `YANGI20`):",
                adminCancelKeyboard()
            );
            return;
        }

        if (str_starts_with($data, 'admin_delpromo_')) {
            $code = substr($data, 15);
            deletePromoCode($code);
            answerCallback($callbackId, "🗑️ {$code} o‘chirildi!");

            $promos = getPromoCodes();
            $list   = '';
            $rows   = [
                [['text' => '➕ Yangi promo-kod qo‘shish', 'callback_data' => 'admin_add_promo']],
            ];
            foreach ($promos as $p) {
                $list .= "• `{$p['code']}` — *{$p['percent']}%* chegirma\n";
                $rows[] = [['text' => "🗑️ O‘chirish: {$p['code']} (-{$p['percent']}%)", 'callback_data' => "admin_delpromo_{$p['code']}"]];
            }
            if ($list === '') {
                $list = "_Hozircha faol promo-kodlar yo‘q._\n";
            }
            $rows[] = [['text' => '⬅️ Admin menyu', 'callback_data' => 'admin_menu']];

            $renderText("🎟️ *Promo-kodlar boshqaruvi*\n\n{$list}", $rows);
            return;
        }

        // Dostavka sozlamalari
        if ($data === 'admin_delivery') {
            $settings = getStoreSettings();
            $fee      = formatPrice((float)$settings['delivery_fee']);
            $freeMin  = (float)$settings['free_delivery_min'] > 0
                ? formatPrice((float)$settings['free_delivery_min'])
                : 'O‘chirilgan';

            answerCallback($callbackId);
            $renderText(
                "🚚 *Yetkazib berish (Dostavka) sozlamalari*\n\n" .
                "💵 Dostavka narxi: *{$fee}*\n" .
                "🎁 Bepul dostavka chegarasi: *{$freeMin}*",
                [
                    [['text' => '✏️ Dostavka narxini o‘zgartirish', 'callback_data' => 'admin_set_delfee']],
                    [['text' => '🎁 Bepul dostavka chegarasi',     'callback_data' => 'admin_set_freedel']],
                    [['text' => '⬅️ Admin menyu',                  'callback_data' => 'admin_menu']],
                ]
            );
            return;
        }

        if ($data === 'admin_set_delfee') {
            $session['state'] = 'admin_set_delfee';
            saveSession($userId, $session);

            answerCallback($callbackId);
            $renderText(
                "💵 Yetkazib berish narxini *UZS* da kiriting (bepul qilish uchun `0` yozing):",
                adminCancelKeyboard()
            );
            return;
        }

        if ($data === 'admin_set_freedel') {
            $session['state'] = 'admin_set_freedel';
            saveSession($userId, $session);

            answerCallback($callbackId);
            $renderText(
                "🎁 Buyurtma summasi necha *UZS* dan oshsa dostavka bepul bo‘lsin? (masalan: `300000`, o‘chirish uchun `0`):",
                adminCancelKeyboard()
            );
            return;
        }

        // Mahsulot o'chirish
        if ($data === 'admin_del_list') {
            $products = getProducts();
            answerCallback($callbackId);

            if (empty($products)) {
                $renderText("📭 Hozircha mahsulotlar mavjud emas.", adminMenuKeyboard());
                return;
            }

            $renderText(
                "🗑️ *Mahsulot o‘chirish*\n\nO‘chirmoqchi bo‘lgan mahsulotingiz ustiga bosing:",
                adminProductsSelectKeyboard($products, 'admin_del_')
            );
            return;
        }

        if (str_starts_with($data, 'admin_del_')) {
            $pid = (int) substr($data, 10);
            deleteProduct($pid);

            answerCallback($callbackId, '🗑️ Mahsulot o‘chirildi!');
            $products = getProducts();

            if (empty($products)) {
                $renderText("📭 Barcha mahsulotlar o‘chirildi.", adminMenuKeyboard());
                return;
            }

            $renderText(
                "🗑️ *Mahsulot o‘chirish*\n\nO‘chirmoqchi bo‘lgan mahsulotingiz ustiga bosing:",
                adminProductsSelectKeyboard($products, 'admin_del_')
            );
            return;
        }

        // Buyurtmalar ro'yxati va holati
        if ($data === 'admin_orders') {
            answerCallback($callbackId);
            $orders = getAllOrders();

            if (empty($orders)) {
                $renderText(
                    "📦 *Barcha buyurtmalar*\n\nHozircha hech qanday buyurtma kelib tushmagan.",
                    [[['text' => '⬅️ Admin menyu', 'callback_data' => 'admin_menu']]]
                );
                return;
            }

            $shown = array_slice($orders, 0, 8);
            $text  = "📦 *Oxirgi buyurtmalar (jami: " . count($orders) . " ta)*\n\nHolatini o‘zgartirish uchun buyurtmani tanlang:\n\n";
            $rows  = [];

            foreach ($shown as $ord) {
                $client = escapeMd($ord['user_name'] ?? 'Mijoz');
                $num    = $ord['order_number'];
                $st     = $ord['status'] ?? 'Ko‘rib chiqilmoqda';
                $text  .= "🧾 `#{$num}` — *{$client}* | *{$st}*\n";
                $text  .= "💰 " . formatPrice((float)$ord['total']) . " | 📅 {$ord['date']}\n";
                $text  .= "─────────────\n";

                $rows[] = [[
                    'text'          => "🧾 #{$num} — {$ord['user_name']} ({$st})",
                    'callback_data' => "admin_ord_{$num}"
                ]];
            }

            $rows[] = [['text' => '⬅️ Admin menyu', 'callback_data' => 'admin_menu']];
            $renderText($text, $rows);
            return;
        }

        if (str_starts_with($data, 'admin_ord_')) {
            $orderNumber = substr($data, 10);
            $order       = findOrderByNumber($orderNumber);

            if (!$order) {
                answerCallback($callbackId, '❌ Buyurtma topilmadi.', true);
                return;
            }

            answerCallback($callbackId);
            $renderText(
                formatOrderDetailForAdmin($order),
                orderStatusKeyboard($orderNumber, !empty($order['receipt_photo']), true)
            );
            return;
        }

        if (str_starts_with($data, 'admin_receipt_')) {
            $orderNumber = substr($data, 14);
            $order       = findOrderByNumber($orderNumber);

            if (!$order || empty($order['receipt_photo'])) {
                answerCallback($callbackId, '❌ Chek rasmi topilmadi.', true);
                return;
            }

            answerCallback($callbackId);
            sendPhoto(
                $chatId,
                $order['receipt_photo'],
                "🧾 Buyurtma `#{$orderNumber}` to‘lov cheki\n👤 Mijoz: " . escapeMd($order['user_name'] ?? 'Mijoz')
            );
            return;
        }

        if (str_starts_with($data, 'ord_st_')) {
            $parts       = explode('_', $data);
            $orderNumber = $parts[2] ?? '';
            $statusCode  = $parts[3] ?? '';

            $statusMap = [
                'accepted'  => ['label' => 'Qabul qilindi ✅',   'notify' => "✅ Buyurtmangiz (`#{$orderNumber}`) qabul qilindi va tayyorlanmoqda!"],
                'shipping'  => ['label' => 'Yo‘lda 🚚',          'notify' => "🚚 Buyurtmangiz (`#{$orderNumber}`) yo‘lga chiqdi! Tez orada yetkazib beriladi."],
                'delivered' => ['label' => 'Yetkazildi 🎉',      'notify' => "🎉 Buyurtmangiz (`#{$orderNumber}`) muvaffaqiyatli yetkazildi! Xaridingiz uchun rahmat."],
                'cancelled' => ['label' => 'Bekor qilindi ❌',   'notify' => "❌ Buyurtmangiz (`#{$orderNumber}`) bekor qilindi. Batafsil ma’lumot uchun admin bilan bog‘laning."],
            ];

            if (!isset($statusMap[$statusCode])) {
                answerCallback($callbackId, '⚠️ Noto‘g‘ri holat.', true);
                return;
            }

            $newLabel = $statusMap[$statusCode]['label'];
            $updated  = updateOrderStatus($orderNumber, $newLabel);

            if (!$updated) {
                answerCallback($callbackId, '❌ Buyurtma topilmadi.', true);
                return;
            }

            $clientId = (int)($updated['user_id'] ?? 0);
            if ($clientId > 0) {
                sendMessage($clientId, $statusMap[$statusCode]['notify']);
            }

            answerCallback($callbackId, "Holat yangilandi: {$newLabel}");

            $updatedText = formatOrderDetailForAdmin($updated);
            $updatedKb   = orderStatusKeyboard($orderNumber, !empty($updated['receipt_photo']), true);

            if ($hasPhoto) {
                editMessageCaption($chatId, $messageId, $updatedText, $updatedKb);
            } else {
                editMessage($chatId, $messageId, $updatedText, $updatedKb);
            }
            return;
        }

        if ($data === 'admin_card') {
            $settings = getStoreSettings();
            $session['state'] = 'admin_set_card';
            saveSession($userId, $session);

            answerCallback($callbackId);
            $renderText(
                "💳 *To‘lov kartasi sozlamalari*\n\n" .
                "Hozirgi karta: `" . escapeMd($settings['card']) . "`\n" .
                "Karta egasi: *" . escapeMd($settings['holder']) . "*\n\n" .
                "Yangilash uchun yangi karta raqamini yuboring (masalan: `8600 1234 5678 9012`):",
                adminCancelKeyboard()
            );
            return;
        }

        if ($data === 'admin_contact') {
            $settings = getStoreSettings();
            $session['state'] = 'admin_set_contact';
            saveSession($userId, $session);

            answerCallback($callbackId);
            $renderText(
                "☎️ *Aloqa ma’lumotlarini tahrirlash*\n\n" .
                "Hozirgi matn:\n" . escapeMd($settings['contact_info']) . "\n\n" .
                "Yangi telefon raqam, Telegram manzil va ish vaqtini yozib yuboring:",
                adminCancelKeyboard()
            );
            return;
        }

        if ($data === 'admin_stats') {
            answerCallback($callbackId);
            $renderText(
                getStatsText(),
                [[['text' => '⬅️ Admin menyu', 'callback_data' => 'admin_menu']]]
            );
            return;
        }

        if ($data === 'admin_broadcast') {
            $session['state'] = 'admin_broadcast';
            saveSession($userId, $session);

            answerCallback($callbackId);
            $renderText(
                "📢 *Barcha foydalanuvchilarga xabar yuborish*\n\nYubormoqchi bo‘lgan xabar matnini yozing:",
                adminCancelKeyboard()
            );
            return;
        }
    }

    // --- ODDIY FOYDALANUVCHI CALLBACKLARI ---
    if ($data === 'menu_main') {
        $session['state'] = 'idle';
        saveSession($userId, $session);

        answerCallback($callbackId);
        $renderText("🏠 *Bosh menyu*\n\nNima qilmoqchisiz?", mainMenuKeyboard($userId));
        return;
    }

    if ($data === 'menu_shop') {
        answerCallback($callbackId);
        $renderText(
            "🛍️ *Kategoriyani tanlang*\n\nQaysi bo‘limdagi mahsulotlarni ko‘rmoqchisiz?",
            categoryKeyboard()
        );
        return;
    }

    if ($data === 'menu_search') {
        $session['state'] = 'user_search';
        saveSession($userId, $session);

        answerCallback($callbackId);
        $renderText(
            "🔍 *Mahsulot qidirish*\n\nQidirayotgan mahsulotingiz nomini yozib yuboring:",
            [[['text' => '❌ Bekor qilish', 'callback_data' => 'menu_main']]]
        );
        return;
    }

    if ($data === 'menu_contact') {
        $settings = getStoreSettings();
        answerCallback($callbackId);
        $renderText(
            "☎️ *Biz bilan bog‘lanish*\n\n" . escapeMd($settings['contact_info']),
            [[['text' => '🏠 Bosh menyu', 'callback_data' => 'menu_main']]]
        );
        return;
    }

    if (str_starts_with($data, 'cat_')) {
        $catKey     = substr($data, 4);
        $categories = getCategories();
        $catName    = escapeMd($categories[$catKey] ?? 'Mahsulotlar');
        $products   = getProductsByCategory($catKey);

        answerCallback($callbackId);

        if (empty($products)) {
            $renderText(
                "🛍️ *{$catName}*\n\nBu bo‘limda hozircha mahsulotlar yo‘q.",
                categoryKeyboard()
            );
            return;
        }

        $renderText(
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

        $caption =
            "{$product['emoji']} *" . escapeMd($product['name']) . "*\n\n" .
            "📝 " . escapeMd($product['desc']) . "\n\n" .
            "💰 Narxi: *" . formatPrice($product['price']) . "*" .
            $cartNote;

        answerCallback($callbackId);

        if (!empty($product['photo'])) {
            deleteMessage($chatId, $messageId);
            $res = sendPhoto($chatId, $product['photo'], $caption, productDetailKeyboard($productId));
            if (empty($res['ok'])) {
                sendMessage($chatId, $caption, productDetailKeyboard($productId));
            }
        } else {
            $renderText($caption, productDetailKeyboard($productId));
        }
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

        $caption =
            "{$product['emoji']} *" . escapeMd($product['name']) . "*\n\n" .
            "📝 " . escapeMd($product['desc']) . "\n\n" .
            "💰 Narxi: *" . formatPrice($product['price']) . "*\n\n" .
            "✅ _Savatga qo‘shildi! Jami: {$qty} dona._";

        if ($hasPhoto) {
            editMessageCaption($chatId, $messageId, $caption, productDetailKeyboard($productId));
        } else {
            editMessage($chatId, $messageId, $caption, productDetailKeyboard($productId));
        }
        return;
    }

    if ($data === 'menu_cart') {
        $session['state'] = 'idle';
        saveSession($userId, $session);

        answerCallback($callbackId);
        $renderText(cartSummaryText($session), cartKeyboard($session));
        return;
    }

    if (str_starts_with($data, 'cart_inc_')) {
        $productId = (int) substr($data, 9);
        cartAdd($session, $productId);
        saveSession($userId, $session);

        answerCallback($callbackId, '➕ 1 dona qo‘shildi');
        $renderText(cartSummaryText($session), cartKeyboard($session));
        return;
    }

    if (str_starts_with($data, 'cart_dec_')) {
        $productId = (int) substr($data, 9);
        cartDecrease($session, $productId);
        saveSession($userId, $session);

        answerCallback($callbackId, '➖ Kamaytirildi');
        $renderText(cartSummaryText($session), cartKeyboard($session));
        return;
    }

    if (str_starts_with($data, 'remove_')) {
        $productId = (int) substr($data, 7);
        $product   = getProductById($productId);
        $name      = $product ? $product['name'] : 'Mahsulot';

        cartRemove($session, $productId);
        saveSession($userId, $session);

        answerCallback($callbackId, "🗑️ {$name} olib tashlandi.");
        $renderText(cartSummaryText($session), cartKeyboard($session));
        return;
    }

    if ($data === 'cart_promo') {
        $session['state'] = 'user_promo';
        saveSession($userId, $session);

        answerCallback($callbackId);
        $renderText(
            "🎟️ *Promo-kodni kiriting:*\n\nChegirma kodini yozib yuboring:",
            [[['text' => '⬅️ Savatchaga qaytish', 'callback_data' => 'menu_cart']]]
        );
        return;
    }

    if ($data === 'cart_promo_remove') {
        $session['promo'] = '';
        saveSession($userId, $session);

        answerCallback($callbackId, '❌ Promo-kod o‘chirildi');
        $renderText(cartSummaryText($session), cartKeyboard($session));
        return;
    }

    if ($data === 'clear_cart') {
        cartClear($session);
        saveSession($userId, $session);

        answerCallback($callbackId, '🗑️ Savatcha tozalandi!');
        $renderText("🛒 *Savatchangiz*\n\nSavatchangiz hozir bo‘sh.", cartKeyboard($session));
        return;
    }

    // Buyurtma berishni boshlash
    if ($data === 'checkout') {
        if (empty($session['cart'])) {
            answerCallback($callbackId, '🛒 Savatchangiz bo‘sh!', true);
            return;
        }

        $session['state'] = 'checkout_phone';
        $session['temp']  = [];
        saveSession($userId, $session);

        answerCallback($callbackId);
        sendReplyKeyboard($chatId,
            "📞 *Buyurtmani rasmiylashtirish (1/3)*\n\n" .
            "Siz bilan bog‘lanishimiz uchun telefon raqamingizni yuboring.\n" .
            "Pastdagi *«📱 Telefon raqamni yuborish»* tugmasini bosing yoki raqamingizni yozib yuboring:",
            [
                [['text' => '📱 Telefon raqamni yuborish', 'request_contact' => true]],
                [['text' => '❌ Bekor qilish']],
            ]
        );
        return;
    }

    if ($data === 'checkout_cancel') {
        $session['state'] = 'idle';
        $session['temp']  = [];
        saveSession($userId, $session);

        answerCallback($callbackId, '❌ Bekor qilindi');
        $renderText("🏠 *Bosh menyu*\n\nNima qilmoqchisiz?", mainMenuKeyboard($userId));
        return;
    }

    if ($data === 'pay_method_cash') {
        if (empty($session['cart'])) {
            answerCallback($callbackId, '🛒 Savatchangiz bo‘sh!', true);
            return;
        }
        answerCallback($callbackId, '🎉 Buyurtma rasmiylashtirildi!');
        finalizeOrder($chatId, $userId, $userName, $session, 'Naqd pul', 'Yetkazilganda to‘lanadi', '', $messageId);
        return;
    }

    if ($data === 'pay_method_card') {
        if (empty($session['cart'])) {
            answerCallback($callbackId, '🛒 Savatchangiz bo‘sh!', true);
            return;
        }

        $settings = getStoreSettings();
        $total    = cartTotal($session);

        $session['state'] = 'checkout_receipt';
        saveSession($userId, $session);

        answerCallback($callbackId);
        editMessage($chatId, $messageId,
            "💳 *Karta orqali to‘lov*\n\n" .
            "Quyidagi karta raqamiga *" . formatPrice($total) . "* o‘tkazing:\n\n" .
            "💳 Karta raqami: `" . escapeMd($settings['card']) . "`\n" .
            "👤 Karta egasi: *" . escapeMd($settings['holder']) . "*\n\n" .
            "📸 To‘lovni amalga oshirgach, *to‘lov chekining rasmini (skrinshotini)* shu yerga yuboring:",
            [[['text' => '❌ Bekor qilish', 'callback_data' => 'checkout_cancel']]]
        );
        return;
    }

    if ($data === 'menu_orders') {
        answerCallback($callbackId);
        $renderText(getOrdersText($userId), [[['text' => '🏠 Bosh menyu', 'callback_data' => 'menu_main']]]);
        return;
    }

    if ($data === 'menu_help') {
        answerCallback($callbackId);
        $renderText(getHelpText(), [[['text' => '🏠 Bosh menyu', 'callback_data' => 'menu_main']]]);
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
        "🔍 *Qidiruv* — Kerakli mahsulotni nomi bo‘yicha tez topish\n" .
        "🛒 *Savatcham* — Miqdorni o‘zgartirish, promo-kod kiritish va buyurtma berish\n" .
        "📦 *Buyurtmalarim* — Oldingi buyurtmalar tarixi va holatini tekshirish\n" .
        "☎️ *Aloqa* — Do‘kon ma’muriyati bilan bog‘lanish\n\n" .
        "*Tezkor buyruqlar:*\n" .
        "/start — Bosh menyuni ochish\n" .
        "/shop — Mahsulotlar katalogi\n" .
        "/cart — Savatchani ochish\n" .
        "/clear — Savatchani tozalash\n" .
        "/help — Yordam oynasi\n\n" .
        "_Yoqimli xarid tilaymiz!_ 🎉";
}

function ordersFile(int $userId): string
{
    return DATA_DIR . "orders_{$userId}.json";
}

function saveOrder(
    int $userId,
    string $userName,
    string $orderNumber,
    array $cart,
    array $calc,
    string $phone = '',
    string $address = '',
    string $mapLink = '',
    string $paymentMethod = 'Naqd pul',
    string $paymentStatus = 'Kutilmoqda',
    string $receiptPhoto = ''
): void {
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
        'user_id'        => $userId,
        'user_name'      => $userName,
        'order_number'   => $orderNumber,
        'date'           => date('Y-m-d H:i:s'),
        'phone'          => $phone,
        'address'        => $address,
        'map_link'       => $mapLink,
        'payment_method' => $paymentMethod,
        'payment_status' => $paymentStatus,
        'receipt_photo'  => $receiptPhoto,
        'items'          => $items,
        'subtotal'       => $calc['subtotal'],
        'promo_code'     => $calc['promo_code'],
        'discount'       => $calc['discount'],
        'delivery_fee'   => $calc['delivery_fee'],
        'total'          => $calc['total'],
        'status'         => 'Ko‘rib chiqilmoqda',
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
        if (!empty($order['payment_method'])) {
            $text .= "💳 To‘lov: {$order['payment_method']}\n";
        }
        $text .= "📌 Holati: *{$order['status']}*\n";
        $text .= "💰 Jami: *" . formatPrice($order['total']) . "*\n";
        $text .= "─────────────\n";
    }

    if (count($orders) > 5) {
        $text .= "\n_Jami " . count($orders) . " ta buyurtmadan oxirgi 5 tasi ko‘rsatilmoqda._";
    }

    return $text;
}

function getAllOrders(): array
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

    usort($allOrders, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    return $allOrders;
}

function findOrderByNumber(string $orderNumber): ?array
{
    $files = glob(DATA_DIR . 'orders_*.json') ?: [];
    foreach ($files as $file) {
        $orders = json_decode(file_get_contents($file), true);
        if (is_array($orders)) {
            foreach ($orders as $ord) {
                if (($ord['order_number'] ?? '') === $orderNumber) {
                    return $ord;
                }
            }
        }
    }
    return null;
}

function updateOrderStatus(string $orderNumber, string $newStatus): ?array
{
    $files = glob(DATA_DIR . 'orders_*.json') ?: [];
    foreach ($files as $file) {
        $orders = json_decode(file_get_contents($file), true);
        if (is_array($orders)) {
            foreach ($orders as $idx => $ord) {
                if (($ord['order_number'] ?? '') === $orderNumber) {
                    $orders[$idx]['status'] = $newStatus;
                    file_put_contents($file, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    return $orders[$idx];
                }
            }
        }
    }
    return null;
}

function formatOrderDetailForAdmin(array $order): string
{
    $itemsText = '';
    foreach ($order['items'] ?? [] as $i => $item) {
        $n = $i + 1;
        $itemsText .= "{$n}. " . escapeMd($item['name']) . " × {$item['qty']} = " . formatPrice($item['subtotal']) . "\n";
    }

    $mapLine   = !empty($order['map_link']) ? "\n🗺️ Xarita: {$order['map_link']}" : '';
    $promoLine = !empty($order['promo_code']) ? "\n🎟️ Promo-kod (`{$order['promo_code']}`): -" . formatPrice((float)($order['discount'] ?? 0)) : '';
    $delLine   = isset($order['delivery_fee']) ? "\n🚚 Dostavka: " . ((float)$order['delivery_fee'] > 0 ? formatPrice((float)$order['delivery_fee']) : 'Bepul') : '';

    return
        "🧾 *Buyurtma `#{$order['order_number']}`*\n\n" .
        "👤 Mijoz: *" . escapeMd($order['user_name'] ?? 'Mijoz') . "* (`" . ($order['user_id'] ?? '-') . "`)\n" .
        "📞 Telefon: `" . escapeMd($order['phone'] ?? '-') . "`\n" .
        "📍 Manzil: " . escapeMd($order['address'] ?? '-') . $mapLine . "\n" .
        "💳 To‘lov: *" . ($order['payment_method'] ?? '-') . "* (" . ($order['payment_status'] ?? '-') . ")\n" .
        "📅 Sana: {$order['date']}\n" .
        "📌 Holati: *{$order['status']}*\n\n" .
        "🛒 *Mahsulotlar:*\n{$itemsText}" .
        $promoLine . $delLine . "\n" .
        "💰 *Jami: " . formatPrice((float)$order['total']) . "*";
}

function getStatsText(): string
{
    $userFiles  = glob(DATA_DIR . 'user_*.json') ?: [];
    $orderFiles = glob(DATA_DIR . 'orders_*.json') ?: [];
    $products   = getProducts();
    $categories = getCategories();

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
        "📂 Kategoriyalar soni: *" . count($categories) . " ta*\n" .
        "🛍️ Mahsulotlar soni: *" . count($products) . " ta*\n" .
        "📦 Jami buyurtmalar: *" . $totalOrders . " ta*\n" .
        "💰 Umumiy savdo hajmi: *" . formatPrice($totalRevenue) . "*";
}
