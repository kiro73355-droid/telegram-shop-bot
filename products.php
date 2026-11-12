<?php

function categoriesFile(): string
{
    return DATA_DIR . 'categories.json';
}

function getDefaultCategories(): array
{
    return [
        'electronics' => '📱 Elektronika',
        'clothing'    => '👕 Kiyim-kechak',
        'food'        => '🍕 Oziq-ovqat',
        'books'       => '📚 Kitoblar',
    ];
}

function getCategories(): array
{
    $file = categoriesFile();
    if (!file_exists($file)) {
        if (!is_dir(DATA_DIR)) {
            mkdir(DATA_DIR, 0755, true);
        }
        $defaults = getDefaultCategories();
        saveCategories($defaults);
        return $defaults;
    }

    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveCategories(array $categories): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    file_put_contents(categoriesFile(), json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function addCategory(string $name): string
{
    $categories = getCategories();
    $key        = 'cat' . time() . rand(10, 99);
    $categories[$key] = $name;
    saveCategories($categories);
    return $key;
}

function deleteCategory(string $key): bool
{
    $categories = getCategories();
    if (!isset($categories[$key])) {
        return false;
    }
    unset($categories[$key]);
    saveCategories($categories);
    return true;
}

function productsFile(): string
{
    return DATA_DIR . 'products.json';
}

function getDefaultProducts(): array
{
    return [
        101 => ['id' => 101, 'category' => 'electronics', 'name' => 'Simsiz quloqchin',      'price' => 350000, 'emoji' => '🎧', 'desc' => '20 soatgacha quvvat saqlaydigan sifatli simsiz quloqchin.', 'photo' => ''],
        102 => ['id' => 102, 'category' => 'electronics', 'name' => 'Aqlli soat (Smart)',    'price' => 650000, 'emoji' => '⌚', 'desc' => 'Yurak urishi va qadamlarni o‘lchovchi zamonaviy soat.',    'photo' => ''],
        103 => ['id' => 103, 'category' => 'electronics', 'name' => 'Bluetooth kalonka',     'price' => 280000, 'emoji' => '🔊', 'desc' => 'Suvga chidamli va kuchli ovozli portativ kalonka.',        'photo' => ''],
        104 => ['id' => 104, 'category' => 'electronics', 'name' => 'USB-C Hub adapter',     'price' => 190000, 'emoji' => '🔌', 'desc' => 'HDMI va flesh-karta o‘quvchi 7-in-1 adapter.',             'photo' => ''],

        201 => ['id' => 201, 'category' => 'clothing',    'name' => 'Klassik futbolka',      'price' => 120000, 'emoji' => '👕', 'desc' => '100% paxtadan tikilgan qulay erkaklar futbolkasi.',        'photo' => ''],
        202 => ['id' => 202, 'category' => 'clothing',    'name' => 'Jinsi shim',            'price' => 290000, 'emoji' => '👖', 'desc' => 'Zamonaviy bichimdagi sifatli jinsi shim (ko‘k/qora).',     'photo' => ''],
        203 => ['id' => 203, 'category' => 'clothing',    'name' => 'Xudi (Hoodie)',         'price' => 250000, 'emoji' => '🧥', 'desc' => 'Salqin ob-havo uchun issiq va yumshoq xudi.',              'photo' => ''],
        204 => ['id' => 204, 'category' => 'clothing',    'name' => 'Krossovka',             'price' => 420000, 'emoji' => '👟', 'desc' => 'Kundalik kiyish uchun yengil va qulay krossovka.',         'photo' => ''],

        301 => ['id' => 301, 'category' => 'food',        'name' => 'Qora shokolad to‘plami','price' => 85000,  'emoji' => '🍫', 'desc' => 'Premium navli qora shokoladlar assartisi (500g).',         'photo' => ''],
        302 => ['id' => 302, 'category' => 'food',        'name' => 'Ko‘k choy qadoq',       'price' => 45000,  'emoji' => '🍵', 'desc' => 'Tabiiy ko‘k choy, 50 ta paketli.',                         'photo' => ''],
        303 => ['id' => 303, 'category' => 'food',        'name' => 'Aralash yong‘oqlar',    'price' => 110000, 'emoji' => '🥜', 'desc' => 'Qovurilgan bodom, pista va yong‘oq aralashmasi (1kg).',    'photo' => ''],
        304 => ['id' => 304, 'category' => 'food',        'name' => 'Achchiq souslar seti',  'price' => 95000,  'emoji' => '🌶️', 'desc' => '4 xil ta’mdagi maxsus achchiq souslar to‘plami.',          'photo' => ''],

        401 => ['id' => 401, 'category' => 'books',       'name' => 'Atom odatlar',          'price' => 65000,  'emoji' => '📖', 'desc' => 'Jeyms Klir — kichik o‘zgarishlar orqali katta natijalar.', 'photo' => ''],
        402 => ['id' => 402, 'category' => 'books',       'name' => 'Boy ota, kambag‘al ota','price' => 55000,  'emoji' => '📗', 'desc' => 'Robert Kiyosaki — moliyaviy savodxonlik asoslari.',        'photo' => ''],
        403 => ['id' => 403, 'category' => 'books',       'name' => 'Clean Code',            'price' => 140000, 'emoji' => '📘', 'desc' => 'Dasturchilar uchun toza va sifatli kod yozish qo‘llanmasi.','photo' => ''],
        404 => ['id' => 404, 'category' => 'books',       'name' => 'Sariq devni minib',     'price' => 45000,  'emoji' => '📙', 'desc' => 'Xudoyberdi To‘xtaboyevning mashhur sarguzasht romani.',    'photo' => ''],
    ];
}

function getProducts(): array
{
    $file = productsFile();
    if (!file_exists($file)) {
        if (!is_dir(DATA_DIR)) {
            mkdir(DATA_DIR, 0755, true);
        }
        $defaults = getDefaultProducts();
        saveProducts($defaults);
        return $defaults;
    }

    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveProducts(array $products): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    file_put_contents(productsFile(), json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function addProduct(string $category, string $name, float $price, string $emoji, string $desc, string $photo = ''): array
{
    $products = getProducts();
    $nextId   = empty($products) ? 101 : (max(array_keys($products)) + 1);

    $product = [
        'id'       => $nextId,
        'category' => $category,
        'name'     => $name,
        'price'    => $price,
        'emoji'    => $emoji,
        'desc'     => $desc,
        'photo'    => $photo,
    ];

    $products[$nextId] = $product;
    saveProducts($products);
    return $product;
}

function updateProductField(int $id, string $field, mixed $value): bool
{
    $products = getProducts();
    if (!isset($products[$id])) {
        return false;
    }
    $products[$id][$field] = $value;
    saveProducts($products);
    return true;
}

function deleteProduct(int $id): bool
{
    $products = getProducts();
    if (!isset($products[$id])) {
        return false;
    }
    unset($products[$id]);
    saveProducts($products);
    return true;
}

function getProductsByCategory(string $category): array
{
    return array_filter(getProducts(), fn($p) => $p['category'] === $category);
}

function getProductById(int $id): ?array
{
    return getProducts()[$id] ?? null;
}

function searchProducts(string $query): array
{
    $q = mb_strtolower(trim($query), 'UTF-8');
    if ($q === '') {
        return [];
    }
    return array_filter(getProducts(), function ($p) use ($q) {
        return mb_stripos($p['name'], $q, 0, 'UTF-8') !== false
            || mb_stripos($p['desc'], $q, 0, 'UTF-8') !== false;
    });
}

// --- DO'KON SOZLAMALARI (Karta, Dostavka, Aloqa) ---
function settingsFile(): string
{
    return DATA_DIR . 'settings.json';
}

function getStoreSettings(): array
{
    $defaults = [
        'card'              => defined('PAYMENT_CARD') ? PAYMENT_CARD : '8600 0000 0000 0000',
        'holder'            => defined('PAYMENT_HOLDER') ? PAYMENT_HOLDER : 'ADMIN',
        'delivery_fee'      => 20000,
        'free_delivery_min' => 300000,
        'contact_info'      => "📞 Telefon: +998 90 123 45 67\n💬 Telegram: @admin\n🕒 Ish vaqti: 09:00 – 21:00",
    ];

    $file = settingsFile();
    if (!file_exists($file)) {
        return $defaults;
    }

    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? array_merge($defaults, $data) : $defaults;
}

function saveStoreSettings(array $updates): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    $current = getStoreSettings();
    $merged  = array_merge($current, $updates);
    file_put_contents(settingsFile(), json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// --- PROMO-KODLAR ---
function promosFile(): string
{
    return DATA_DIR . 'promos.json';
}

function getPromoCodes(): array
{
    $file = promosFile();
    if (!file_exists($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function savePromoCodes(array $promos): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    file_put_contents(promosFile(), json_encode($promos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function addPromoCode(string $code, int $percent): void
{
    $code   = strtoupper(trim($code));
    $promos = getPromoCodes();
    $promos[$code] = [
        'code'    => $code,
        'percent' => $percent,
    ];
    savePromoCodes($promos);
}

function deletePromoCode(string $code): bool
{
    $code   = strtoupper(trim($code));
    $promos = getPromoCodes();
    if (!isset($promos[$code])) {
        return false;
    }
    unset($promos[$code]);
    savePromoCodes($promos);
    return true;
}

function getPromoByCode(string $code): ?array
{
    $code = strtoupper(trim($code));
    return getPromoCodes()[$code] ?? null;
}
