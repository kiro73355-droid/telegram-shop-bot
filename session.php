<?php

function sessionFile(int $userId): string
{
    return DATA_DIR . "user_{$userId}.json";
}

function loadSession(int $userId): array
{
    $file = sessionFile($userId);
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? array_merge(defaultSession(), $data) : defaultSession();
    }
    return defaultSession();
}

function saveSession(int $userId, array $session): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    file_put_contents(sessionFile($userId), json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function defaultSession(): array
{
    return [
        'state' => 'idle',
        'cart'  => [],
        'temp'  => [],
    ];
}

function cartAdd(array &$session, int $productId, int $qty = 1): void
{
    $session['cart'][$productId] = ($session['cart'][$productId] ?? 0) + $qty;
}

function cartRemove(array &$session, int $productId): void
{
    unset($session['cart'][$productId]);
}

function cartClear(array &$session): void
{
    $session['cart'] = [];
}

function cartTotal(array $session): float
{
    $total = 0;
    foreach ($session['cart'] as $pid => $qty) {
        $product = getProductById((int)$pid);
        if ($product) {
            $total += $product['price'] * $qty;
        }
    }
    return $total;
}

function cartCount(array $session): int
{
    return array_sum($session['cart']);
}

function cartSummaryText(array $session): string
{
    if (empty($session['cart'])) {
        return "🛒 Savatchangiz hozircha bo‘sh.";
    }

    $lines = ["🛒 *Sizning savatchangiz:*\n"];
    $i = 1;
    foreach ($session['cart'] as $pid => $qty) {
        $p = getProductById((int)$pid);
        if ($p) {
            $subtotal = $p['price'] * $qty;
            $lines[] = "{$i}. {$p['emoji']} " . escapeMd($p['name']) . " × {$qty} = " . formatPrice($subtotal);
            $i++;
        }
    }
    $lines[] = "\n💰 *Jami summa: " . formatPrice(cartTotal($session)) . "*";
    return implode("\n", $lines);
}
