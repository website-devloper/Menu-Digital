<?php
declare(strict_types=1);

require __DIR__ . '/shared/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    $data = $_POST;
}

$tableNumber = trim((string)($data['table_number'] ?? ''));
$customerName = trim((string)($data['customer_name'] ?? ''));
$restaurantSlug = trim((string)($data['restaurant_slug'] ?? 'maison-sola'));
$items = $data['items'] ?? [];
$notes = trim((string)($data['notes'] ?? ''));

if (empty($tableNumber)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Numéro de table obligatoire']);
    exit;
}

if (!is_array($items) || empty($items)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Le panier est vide']);
    exit;
}

try {
    $pdo = db();

    // Find restaurant ID
    $stmt = $pdo->prepare('SELECT id, name, currency FROM restaurants WHERE slug = ? LIMIT 1');
    $stmt->execute([$restaurantSlug]);
    $rest = $stmt->fetch();

    if (!$rest) {
        // Fallback to first restaurant
        $rest = $pdo->query('SELECT id, name, currency FROM restaurants ORDER BY id ASC LIMIT 1')->fetch();
    }

    if (!$rest) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Restaurant introuvable']);
        exit;
    }

    $restId = (int)$rest['id'];

    // Calculate total & format items
    $cleanItems = [];
    $totalAmount = 0.0;

    foreach ($items as $it) {
        $dishId = (int)($it['id'] ?? 0);
        $dishName = trim((string)($it['name'] ?? 'Plat'));
        $price = (float)($it['price'] ?? 0);
        $qty = max(1, (int)($it['qty'] ?? 1));
        $customSummary = trim((string)($it['customSummary'] ?? ($it['options_summary'] ?? '')));

        $sub = $price * $qty;
        $totalAmount += $sub;

        $cleanItems[] = [
            'id' => $dishId,
            'name' => $dishName,
            'price' => $price,
            'qty' => $qty,
            'customSummary' => $customSummary,
            'subtotal' => $sub
        ];
    }

    $itemsJson = json_encode($cleanItems, JSON_UNESCAPED_UNICODE);

    // Ensure orders table exists
    $isPg = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
    $pk = $isPg ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $ts = $isPg ? 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' : 'TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP';

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id             $pk,
        restaurant_id  INTEGER NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
        table_number   TEXT NOT NULL,
        customer_name  TEXT DEFAULT '',
        items_json     TEXT NOT NULL,
        total_amount   REAL NOT NULL DEFAULT 0,
        status         TEXT NOT NULL DEFAULT 'pending',
        notes          TEXT DEFAULT '',
        created_at     $ts
    )");

    $ins = $pdo->prepare('INSERT INTO orders (restaurant_id, table_number, customer_name, items_json, total_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $ins->execute([
        $restId,
        $tableNumber,
        $customerName,
        $itemsJson,
        $totalAmount,
        'pending',
        $notes
    ]);

    $orderId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'table_number' => $tableNumber,
        'customer_name' => $customerName,
        'total_amount' => $totalAmount,
        'currency' => $rest['currency'] ?: 'DH',
        'message' => 'Commande transmise avec succès à la cuisine !'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
    ]);
}
