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
        return is_array($data) ? $data : defaultSession();
    }
    return defaultSession();
}

function saveSession(int $userId, array $session): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    file_put_contents(sessionFile($userId), json_encode($session, JSON_PRETTY_PRINT));
}

function defaultSession(): array
{
    return [
        'state' => 'idle',
        'cart'  => [],
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
        return "🛒 Your cart is empty.";
    }

    $lines = ["🛒 *Your Cart*
"];
    $i = 1;
    foreach ($session['cart'] as $pid => $qty) {
        $p = getProductById((int)$pid);
        if ($p) {
            $subtotal = $p['price'] * $qty;
            $lines[] = "{$i}. {$p['emoji']} {$p['name']} × {$qty} = " . CURRENCY . number_format($subtotal, 2);
            $i++;
        }
    }
    $lines[] = "
💰 *Total: " . CURRENCY . number_format(cartTotal($session), 2) . "*";
    return implode("
", $lines);
}
