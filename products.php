<?php

function getCategories(): array
{
    return [
        'electronics' => '📱 Elektronika',
        'clothing'    => '👕 Kiyim-kechak',
        'food'        => '🍕 Oziq-ovqat',
        'books'       => '📚 Kitoblar',
    ];
}

function productsFile(): string
{
    return DATA_DIR . 'products.json';
}

function getDefaultProducts(): array
{
    return [
        101 => ['id' => 101, 'category' => 'electronics', 'name' => 'Simsiz quloqchin',      'price' => 350000,  'emoji' => '🎧', 'desc' => '20 soatgacha quvvat saqlaydigan sifatli simsiz quloqchin.'],
        102 => ['id' => 102, 'category' => 'electronics', 'name' => 'Aqlli soat (Smart)',    'price' => 650000,  'emoji' => '⌚', 'desc' => 'Yurak urishi va qadamlarni o‘lchovchi zamonaviy soat.'],
        103 => ['id' => 103, 'category' => 'electronics', 'name' => 'Bluetooth kalonka',     'price' => 280000,  'emoji' => '🔊', 'desc' => 'Suvga chidamli va kuchli ovozli portativ kalonka.'],
        104 => ['id' => 104, 'category' => 'electronics', 'name' => 'USB-C Hub adapter',     'price' => 190000,  'emoji' => '🔌', 'desc' => 'HDMI va flesh-karta o‘quvchi 7-in-1 ko‘p funksiyali adapter.'],

        201 => ['id' => 201, 'category' => 'clothing',    'name' => 'Klassik futbolka',      'price' => 120000,  'emoji' => '👕', 'desc' => '100% paxtadan tikilgan qulay erkaklar futbolkasi.'],
        202 => ['id' => 202, 'category' => 'clothing',    'name' => 'Jinsi shim',            'price' => 290000,  'emoji' => '👖', 'desc' => 'Zamonaviy bichimdagi sifatli jinsi shim (ko‘k/qora).'],
        203 => ['id' => 203, 'category' => 'clothing',    'name' => 'Xudi (Hoodie)',         'price' => 250000,  'emoji' => '🧥', 'desc' => 'Salqin ob-havo uchun issiq va yumshoq xudi.'],
        204 => ['id' => 204, 'category' => 'clothing',    'name' => 'Krossovka',             'price' => 420000,  'emoji' => '👟', 'desc' => 'Kundalik kiyish uchun yengil va qulay krossovka.'],

        301 => ['id' => 301, 'category' => 'food',        'name' => 'Qora shokolad to‘plami','price' => 85000,   'emoji' => '🍫', 'desc' => 'Premium navli qora shokoladlar assartisi (500g).'],
        302 => ['id' => 302, 'category' => 'food',        'name' => 'Ko‘k choy qadoq',       'price' => 45000,   'emoji' => '🍵', 'desc' => 'Tabiiy ko‘k choy, 50 ta paketli.'],
        303 => ['id' => 303, 'category' => 'food',        'name' => 'Aralash yong‘oqlar',    'price' => 110000,  'emoji' => '🥜', 'desc' => 'Qovurilgan bodom, pista va yong‘oq aralashmasi (1kg).'],
        304 => ['id' => 304, 'category' => 'food',        'name' => 'Achchiq souslar seti',  'price' => 95000,   'emoji' => '🌶️', 'desc' => '4 xil ta’mdagi maxsus achchiq souslar to‘plami.'],

        401 => ['id' => 401, 'category' => 'books',       'name' => 'Atom odatlar',          'price' => 65000,   'emoji' => '📖', 'desc' => 'Jeyms Klir — kichik o‘zgarishlar orqali katta natijalar.'],
        402 => ['id' => 402, 'category' => 'books',       'name' => 'Boy ota, kambag‘al ota','price' => 55000,   'emoji' => '📗', 'desc' => 'Robert Kiyosaki — moliyaviy savodxonlik asoslari.'],
        403 => ['id' => 403, 'category' => 'books',       'name' => 'Clean Code',            'price' => 140000,  'emoji' => '📘', 'desc' => 'Dasturchilar uchun toza va sifatli kod yozish qo‘llanmasi.'],
        404 => ['id' => 404, 'category' => 'books',       'name' => 'Sariq devni minib',     'price' => 45000,   'emoji' => '📙', 'desc' => 'Xudoyberdi To‘xtaboyevning mashhur sarguzasht romani.'],
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

function addProduct(string $category, string $name, float $price, string $emoji, string $desc): array
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
    ];

    $products[$nextId] = $product;
    saveProducts($products);
    return $product;
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
