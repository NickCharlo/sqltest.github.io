<?php
/**
 * TaskFlow — Categories API
 *
 * Endpoints:
 *   GET    /php/categories.php            List categories for current user
 *   POST   /php/categories.php            Create a category
 *   DELETE /php/categories.php?id=3       Delete a category
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();

    // ── LIST ─────────────────────────────────────────────────
    if ($method === 'GET') {
        $stmt = $db->prepare(
            'SELECT c.*, COUNT(t.id) AS task_count
             FROM categories c
             LEFT JOIN tasks t ON t.category_id = c.id
             WHERE c.user_id = ?
             GROUP BY c.id
             ORDER BY c.name'
        );
        $stmt->execute([$userId]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    // ── CREATE ───────────────────────────────────────────────
    if ($method === 'POST') {
        $data  = json_decode(file_get_contents('php://input'), true);
        $name  = trim($data['name']  ?? '');
        $color = $data['color']       ?? '#6366f1';

        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Category name is required.']);
            exit;
        }

        $stmt = $db->prepare('INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $name, $color]);

        http_response_code(201);
        echo json_encode([
            'id'         => (int) $db->lastInsertId(),
            'user_id'    => $userId,
            'name'       => $name,
            'color'      => $color,
            'task_count' => 0,
        ]);
        exit;
    }

    // ── DELETE ───────────────────────────────────────────────
    if ($method === 'DELETE') {
        $catId = (int) ($_GET['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?');
        $stmt->execute([$catId, $userId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Category not found.']);
            exit;
        }

        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
