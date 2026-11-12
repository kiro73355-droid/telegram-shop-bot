<?php

function getCategories(): array
{
    return [
        'electronics' => '📱 Electronics',
        'clothing'    => '👕 Clothing',
        'food'        => '🍕 Food & Drinks',
        'books'       => '▪ Books',
    ];
}

function getProducts(): array
{
    return [
        101 => ['id' => 101, 'category' => 'electronics', 'name' => 'Wireless Headphones',  'price' => 49.99,  'emoji' => '🎧', 'desc' => 'High-quality wireless headphones with 20h battery life.'],
        102 => ['id' => 102, 'category' => 'electronics', 'name' => 'Smart Watch',           'price' => 129.99, 'emoji' => '⌚', 'desc' => 'Feature-packed smartwatch with health tracking.'],
        103 => ['id' => 103, 'category' => 'electronics', 'name' => 'Bluetooth Speaker',     'price' => 34.99,  'emoji' => '🔊', 'desc' => 'Portable waterproof Bluetooth speaker.'],
        104 => ['id' => 104, 'category' => 'electronics', 'name' => 'USB-C Hub',             'price' => 24.99,  'emoji' => '🔌', 'desc' => '7-in-1 USB-C hub with HDMI and card reader.'],

        201 => ['id' => 201, 'category' => 'clothing', 'name' => 'Classic T-Shirt',          'price' => 14.99,  'emoji' => '👕', 'desc' => '100% cotton classic fit T-shirt.'],
        202 => ['id' => 202, 'category' => 'clothing', 'name' => 'Denim Jeans',              'price' => 39.99,  'emoji' => '👖', 'desc' => 'Slim fit denim jeans, available in blue/black.'],
        203 => ['id' => 203, 'category' => 'clothing', 'name' => 'Hoodie',                   'price' => 29.99,  'emoji' => '🧥', 'desc' => 'Cozy pullover hoodie for all seasons.'],
        204 => ['id' => 204, 'category' => 'clothing', 'name' => 'Sneakers',                 'price' => 59.99,  'emoji' => '👟', 'desc' => 'Lightweight everyday sneakers.'],

        301 => ['id' => 301, 'category' => 'food', 'name' => 'Dark Chocolate Box',           'price' => 9.99,   'emoji' => '🍫', 'desc' => 'Premium dark chocolate assortment (500g).'],
        302 => ['id' => 302, 'category' => 'food', 'name' => 'Green Tea Pack',               'price' => 7.99,   'emoji' => '🍵', 'desc' => 'Organic green tea, 50 bags.'],
        303 => ['id' => 303, 'category' => 'food', 'name' => 'Mixed Nuts',                   'price' => 12.99,  'emoji' => '🥜', 'desc' => 'Roasted mixed nuts (1kg).'],
        304 => ['id' => 304, 'category' => 'food', 'name' => 'Hot Sauce Set',                'price' => 15.99,  'emoji' => '🌶️', 'desc' => 'Set of 4 artisan hot sauces.'],

        401 => ['id' => 401, 'category' => 'books', 'name' => 'PHP 8 Cookbook',             'price' => 29.99,  'emoji' => '📖', 'desc' => 'Modern PHP 8 techniques and recipes.'],
        402 => ['id' => 402, 'category' => 'books', 'name' => 'Clean Code',                 'price' => 24.99,  'emoji' => '📗', 'desc' => 'A handbook of agile software craftsmanship.'],
        403 => ['id' => 403, 'category' => 'books', 'name' => 'The Pragmatic Programmer',   'price' => 22.99,  'emoji' => '📘', 'desc' => 'Your journey to mastery — 20th anniversary edition.'],
        404 => ['id' => 404, 'category' => 'books', 'name' => 'Design Patterns',            'price' => 27.99,  'emoji' => '📙', 'desc' => 'Elements of reusable object-oriented software.'],
    ];
}

function getProductsByCategory(string $category): array
{
    return array_filter(getProducts(), fn($p) => $p['category'] === $category);
}

function getProductById(int $id): ?array
{
    return getProducts()[$id] ?? null;
}
