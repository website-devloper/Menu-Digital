<?php
// shared/lib.php — domain helpers used by every public page.

/* === Flag + spice metadata === */
const DIETARY_FLAGS = [
  'vegan'        => ['Vegan',          '🌱'],
  'vegetarien'   => ['Végétarien',     '🥗'],
  'halal'        => ['Halal',          '☪'],
  'gluten-free'  => ['Sans gluten',    '🌾'],
  'gluten'       => ['Contient gluten','🌾'],
  'lactose-free' => ['Sans lactose',   '🥛'],
  'lactose'      => ['Contient lactose','🥛'],
  'nuts'         => ['Fruits à coque', '🥜'],
];

function flag_label(string $f): array { return DIETARY_FLAGS[$f] ?? [$f, '']; }

function flags_to_list(?string $csv): array {
    if (!$csv) return [];
    return array_values(array_filter(array_map('trim', explode(',', $csv))));
}

/* === Opening hours === */
function hours_decode(?string $json): array {
    $d = $json ? (json_decode($json, true) ?: []) : [];
    $keys = ['mon','tue','wed','thu','fri','sat','sun'];
    foreach ($keys as $k) {
        if (!isset($d[$k])) $d[$k] = ['closed' => 1, 'open' => '', 'close' => ''];
    }
    return $d;
}

function hours_day_key(?DateTimeInterface $when = null): string {
    $when = $when ?: new DateTimeImmutable('now');
    return strtolower(substr($when->format('D'), 0, 3));
}

/**
 * Returns [status, label] where status is 'open'|'closing_soon'|'closed'
 * and label is a short display string.
 */
function hours_status(array $hours, ?DateTimeInterface $when = null): array {
    $when = $when ?: new DateTimeImmutable('now');
    $key = hours_day_key($when);
    $today = $hours[$key] ?? ['closed' => 1];
    if (!empty($today['closed'])) {
        return ['closed', 'Fermé aujourd\'hui'];
    }
    $now  = (int)$when->format('Hi');
    $open = (int)str_replace(':', '', $today['open'] ?? '00:00');
    $closeStr = $today['close'] ?? '00:00';
    $close = (int)str_replace(':', '', $closeStr);
    if ($close === 0) $close = 2400; // midnight
    if ($now < $open || $now >= $close) {
        return ['closed', 'Ouvre à ' . substr($today['open'] ?? '', 0, 5)];
    }
    $closeH = substr($closeStr, 0, 5);
    if ($close - $now <= 60) {
        return ['closing_soon', 'Ferme à ' . $closeH];
    }
    return ['open', 'Nous servons jusqu\'à ' . $closeH];
}

/* === Promotions === */
function promotions_active(int $restaurantId, ?DateTimeInterface $when = null): array {
    $when = $when ?: new DateTimeImmutable('now');
    $date = $when->format('Y-m-d');
    $time = (int)$when->format('Hi');
    $dowIdx = ((int)$when->format('N')) - 1;   // 0=Mon ... 6=Sun
    $dowBit = 1 << $dowIdx;

    $st = db()->prepare('SELECT * FROM promotions WHERE restaurant_id = ? AND active = 1');
    $st->execute([$restaurantId]);
    $out = [];
    foreach ($st as $p) {
        if ($p['start_date'] && $date < $p['start_date']) continue;
        if ($p['end_date']   && $date > $p['end_date'])   continue;
        if (!((int)$p['days_mask'] & $dowBit)) continue;
        if ($p['start_time'] || $p['end_time']) {
            $s = $p['start_time'] ? (int)str_replace(':', '', $p['start_time']) : 0;
            $e = $p['end_time']   ? (int)str_replace(':', '', $p['end_time'])   : 2400;
            if ($time < $s || $time >= $e) continue;
        }
        $out[] = $p;
    }
    return $out;
}

/**
 * Apply active promotions to a dish. Returns:
 * ['final'=>float, 'original'=>float, 'pct'=>int, 'promo'=>?array]
 */
function dish_promo_price(array $dish, array $promos): array {
    $best = null;
    foreach ($promos as $p) {
        $applies = match ($p['scope_type']) {
            'all'      => true,
            'category' => (int)$p['scope_id'] === (int)$dish['category_id'],
            'dish'     => (int)$p['scope_id'] === (int)$dish['id'],
            default    => false,
        };
        if (!$applies) continue;
        if (!$best || (int)$p['discount_pct'] > (int)$best['discount_pct']) $best = $p;
    }
    if (!$best || (int)$best['discount_pct'] <= 0) {
        return ['final' => (float)$dish['price'], 'original' => (float)$dish['price'], 'pct' => 0, 'promo' => null];
    }
    $pct = min(100, max(0, (int)$best['discount_pct']));
    $final = round((float)$dish['price'] * (100 - $pct) / 100, 2);
    return ['final' => $final, 'original' => (float)$dish['price'], 'pct' => $pct, 'promo' => $best];
}

/* === WhatsApp === */
function wa_number(?string $raw): string {
    return preg_replace('/\D+/', '', (string)$raw);
}

function wa_url(string $number, string $message): string {
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
}
