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
        'promo' => '',
        'temp'  => [],
    ];
}

function cartAdd(array &$session, int $productId, int $qty = 1): void
{
    $session['cart'][$productId] = ($session['cart'][$productId] ?? 0) + $qty;
}

function cartDecrease(array &$session, int $productId): void
{
    if (!isset($session['cart'][$productId])) {
        return;
    }
    if ($session['cart'][$productId] > 1) {
        $session['cart'][$productId]--;
    } else {
        unset($session['cart'][$productId]);
    }
}

function cartRemove(array &$session, int $productId): void
{
    unset($session['cart'][$productId]);
}

function cartClear(array &$session): void
{
    $session['cart']  = [];
    $session['promo'] = '';
}

function cartBreakdown(array $session): array
{
    $subtotal = 0;
    foreach ($session['cart'] as $pid => $qty) {
        $product = getProductById((int)$pid);
        if ($product) {
            $subtotal += $product['price'] * $qty;
        }
    }

    if ($subtotal <= 0) {
        return [
            'subtotal'       => 0,
            'promo_code'     => '',
            'promo_percent'  => 0,
            'discount'       => 0,
            'after_discount' => 0,
            'delivery_fee'   => 0,
            'free_min'       => 0,
            'total'          => 0,
        ];
    }

    $promoCode    = '';
    $promoPercent = 0;
    $discount     = 0;

    if (!empty($session['promo'])) {
        $promo = getPromoByCode($session['promo']);
        if ($promo) {
            $promoCode    = $promo['code'];
            $promoPercent = (int)$promo['percent'];
            $discount     = round($subtotal * $promoPercent / 100);
        }
    }

    $afterDiscount = max(0, $subtotal - $discount);
    $settings      = getStoreSettings();
    $baseDelivery  = (float)($settings['delivery_fee'] ?? 0);
    $freeMin       = (float)($settings['free_delivery_min'] ?? 0);

    $deliveryFee = $baseDelivery;
    if ($freeMin > 0 && $afterDiscount >= $freeMin) {
        $deliveryFee = 0;
    }

    return [
        'subtotal'       => $subtotal,
        'promo_code'     => $promoCode,
        'promo_percent'  => $promoPercent,
        'discount'       => $discount,
        'after_discount' => $afterDiscount,
        'delivery_fee'   => $deliveryFee,
        'free_min'       => $freeMin,
        'total'          => $afterDiscount + $deliveryFee,
    ];
}

function cartTotal(array $session): float
{
    return cartBreakdown($session)['total'];
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
            $itemTotal = $p['price'] * $qty;
            $lines[] = "{$i}. {$p['emoji']} " . escapeMd($p['name']) . " × {$qty} = " . formatPrice($itemTotal);
            $i++;
        }
    }

    $calc = cartBreakdown($session);
    $lines[] = "\n📦 Mahsulotlar: *" . formatPrice($calc['subtotal']) . "*";

    if ($calc['discount'] > 0) {
        $lines[] = "🎟️ Chegirma (`{$calc['promo_code']}` -{$calc['promo_percent']}%): *-" . formatPrice($calc['discount']) . "*";
    }

    if ($calc['delivery_fee'] > 0) {
        $hint = $calc['free_min'] > 0 ? " _(" . formatPrice($calc['free_min']) . " dan oshsa bepul)_" : '';
        $lines[] = "🚚 Yetkazib berish: *" . formatPrice($calc['delivery_fee']) . "*{$hint}";
    } else {
        $lines[] = "🚚 Yetkazib berish: *Bepul ✅*";
    }

    $lines[] = "\n💰 *Jami to‘lov: " . formatPrice($calc['total']) . "*";
    return implode("\n", $lines);
}
