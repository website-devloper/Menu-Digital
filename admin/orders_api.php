<?php
declare(strict_types=1);

require __DIR__ . '/../shared/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = require_auth();
    $rest = current_restaurant($user);
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

if ($action === 'list' || $action === 'poll') {
    $status = trim((string)($_GET['status'] ?? ''));
    $sinceId = (int)($_GET['since_id'] ?? 0);

    $sql = 'SELECT * FROM orders WHERE restaurant_id = ?';
    $params = [$rest['id']];

    if ($sinceId > 0) {
        $sql .= ' AND id > ?';
        $params[] = $sinceId;
    }

    if ($status && $status !== 'all') {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }

    $sql .= ' ORDER BY id DESC LIMIT 50';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['items'] = json_decode($r['items_json'] ?? '[]', true) ?: [];
    }
    unset($r);

    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE restaurant_id = {$rest['id']} AND status = 'pending'")->fetchColumn();

    echo json_encode([
        'success' => true,
        'orders' => $rows,
        'pending_count' => $pendingCount,
        'server_time' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'update_status') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) $data = $_POST;

    $orderId = (int)($data['order_id'] ?? 0);
    $newStatus = trim((string)($data['status'] ?? ''));

    $validStatuses = ['pending', 'preparing', 'served', 'completed', 'cancelled'];
    if (!$orderId || !in_array($newStatus, $validStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND restaurant_id = ?');
    $stmt->execute([$newStatus, $orderId, $rest['id']]);

    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE restaurant_id = {$rest['id']} AND status = 'pending'")->fetchColumn();

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'new_status' => $newStatus,
        'pending_count' => $pendingCount
    ]);
    exit;
}

if ($action === 'delete') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    if ($orderId) {
        $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ? AND restaurant_id = ?');
        $stmt->execute([$orderId, $rest['id']]);
    }
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Action inconnue']);
