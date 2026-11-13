<?php

function getGeminiApiKey(): string
{
    $settings = getStoreSettings();
    if (!empty($settings['gemini_api_key'])) {
        return trim($settings['gemini_api_key']);
    }
    if (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '' && GEMINI_API_KEY !== 'BU_YERGA_GEMINI_API_KALITINI_QOYING') {
        return trim(GEMINI_API_KEY);
    }
    return '';
}

function isAiEnabled(): bool
{
    $settings = getStoreSettings();
    $enabled  = $settings['ai_enabled'] ?? true;
    return (bool)$enabled && getGeminiApiKey() !== '';
}

function buildCatalogContext(): string
{
    $categories = getCategories();
    $products   = getProducts();
    $settings   = getStoreSettings();

    $lines = ["DO'KON MA'LUMOTLARI:"];
    $lines[] = "- Valyuta: " . CURRENCY;
    $lines[] = "- Dostavka narxi: " . formatPrice((float)($settings['delivery_fee'] ?? 0));
    if ((float)($settings['free_delivery_min'] ?? 0) > 0) {
        $lines[] = "- Bepul dostavka chegarasi: " . formatPrice((float)$settings['free_delivery_min']) . " dan oshsa bepul";
    }
    $lines[] = "- To'lov usullari: Karta orqali o'tkazma (chek yuborish) yoki Naqd pul";
    $lines[] = "- Aloqa: " . str_replace("\n", " | ", $settings['contact_info'] ?? '');
    $lines[] = "\nMAVJUD MAHSULOTLAR RO'YXATI:";

    if (empty($products)) {
        $lines[] = "Hozircha mahsulotlar yo'q.";
    } else {
        foreach ($products as $p) {
            $catName = $categories[$p['category']] ?? $p['category'];
            $lines[] = "- [ID:{$p['id']}] {$p['name']} | Kategoriya: {$catName} | Narx: " . formatPrice($p['price']) . " | Tavsif: {$p['desc']}";
        }
    }

    return implode("\n", $lines);
}

function askShopAi(string $userMessage, array &$session): array
{
    $apiKey = getGeminiApiKey();
    if ($apiKey === '') {
        return [
            'text'     => "⚠️ AI maslahatchi hozircha sozlanmagan.",
            'keyboard' => [[['text' => '🏠 Bosh menyu', 'callback_data' => 'menu_main']]],
        ];
    }

    $catalogContext = buildCatalogContext();
    $systemPrompt   =
        "Sen onlayn do'konning samimiy va professional AI sotuvchi-maslahatchisisan. " .
        "Faqat o'zbek tilida, qisqa, tushunarli va xushmuomala javob ber.\n\n" .
        "QOIDALAR:\n" .
        "1. Faqat quyidagi ro'yxatda bor mahsulotlarni tavsiya qil, o'zingdan yo'q mahsulot yoki narx to'qima.\n" .
        "2. Mijoz byudjet, sovg'a yoki ehtiyoj aytsa, ro'yxatdan eng mos 1-3 ta mahsulotni tanlab tushuntir.\n" .
        "3. Javobingda biror mahsulotni tavsiya qilsang yoki narxini aytsang, xabarning eng oxirida albatta uning ID tegini yoz: masalan [PRODUCT:101] [PRODUCT:202]. Boshqa matn ichida ID yozma.\n" .
        "4. Agar mijoz buyurtma bermoqchi bo'lsa, pastdagi mahsulot tugmasini bosib savatchaga qo'shishini ayt.\n\n" .
        $catalogContext;

    $history   = $session['ai_history'] ?? [];
    $history[] = [
        'role'  => 'user',
        'parts' => [['text' => $userMessage]],
    ];

    // Oxirgi 10 ta xabarni (5 ta savol-javob) saqlash
    if (count($history) > 10) {
        $history = array_slice($history, -10);
    }

    $payload = [
        'systemInstruction' => [
            'parts' => [['text' => $systemPrompt]],
        ],
        'contents' => $history,
        'generationConfig' => [
            'temperature'     => 0.6,
            'maxOutputTokens' => 600,
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($apiKey);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($raw, true);
    $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if ($code !== 200 || trim($reply) === '') {
        return [
            'text'     => "😔 Hozir AI javob bera olmadi. Birozdan so‘ng qayta urinib ko‘ring yoki katalogdan foydalaning.",
            'keyboard' => [
                [['text' => '🛍️ Do‘kon', 'callback_data' => 'menu_shop']],
                [['text' => '🏠 Bosh menyu', 'callback_data' => 'menu_main']],
            ],
        ];
    }

    // AI javobini tarixga qo'shish
    $history[] = [
        'role'  => 'model',
        'parts' => [['text' => $reply]],
    ];
    $session['ai_history'] = $history;

    // Tavsiya etilgan mahsulot IDlarini ajratib olish
    preg_match_all('/\[PRODUCT:(\d+)\]/i', $reply, $matches);
    $productIds = array_unique(array_map('intval', $matches[1] ?? []));

    // Matndan [PRODUCT:xxx] teglarini tozalash va Telegram Markdown ga moslash
    $cleanText = trim(preg_replace('/\[PRODUCT:\d+\]/i', '', $reply));
    $cleanText = str_replace('**', '*', $cleanText);

    $keyboard = [];
    $addedBtns = 0;
    foreach ($productIds as $pid) {
        if ($addedBtns >= 4) {
            break;
        }
        $p = getProductById($pid);
        if ($p) {
            $keyboard[] = [[
                'text'          => "{$p['emoji']} {$p['name']} — " . formatPrice($p['price']),
                'callback_data' => "product_{$p['id']}"
            ]];
            $addedBtns++;
        }
    }

    $keyboard[] = [
        ['text' => '🛒 Savatcham',  'callback_data' => 'menu_cart'],
        ['text' => '🏠 Bosh menyu', 'callback_data' => 'menu_main'],
    ];

    return [
        'text'     => "🤖 " . $cleanText,
        'keyboard' => $keyboard,
    ];
}
