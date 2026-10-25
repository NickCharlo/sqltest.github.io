<?php
/**
 * TaskFlow — Tasks API
 *
 * Endpoints:
 *   GET    /php/tasks.php                        List all tasks for current user
 *   GET    /php/tasks.php?id=5                   Get a single task
 *   POST   /php/tasks.php                        Create a task
 *   PUT    /php/tasks.php?id=5                   Update a task
 *   DELETE /php/tasks.php?id=5                   Delete a task
 *   GET    /php/tasks.php?action=stats           Dashboard stats
 *
 * Filters (on list):
 *   ?status=todo|in_progress|done
 *   ?priority=low|medium|high
 *   ?category_id=3
 *   ?search=keyword
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// ── Auth guard ───────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$taskId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$action = $_GET['action'] ?? null;

try {
    $db = getDB();

    // ── DASHBOARD STATS ─────────────────────────────────────
    if ($action === 'stats' && $method === 'GET') {
        $stats = [];

        // Counts by status
        $stmt = $db->prepare('SELECT status, COUNT(*) as count FROM tasks WHERE user_id = ? GROUP BY status');
        $stmt->execute([$userId]);
        $byStatus = ['todo' => 0, 'in_progress' => 0, 'done' => 0];
        while ($row = $stmt->fetch()) {
            $byStatus[$row['status']] = (int) $row['count'];
        }
        $stats['by_status'] = $byStatus;
        $stats['total'] = array_sum($byStatus);

        // Counts by priority
        $stmt = $db->prepare('SELECT priority, COUNT(*) as count FROM tasks WHERE user_id = ? AND status != "done" GROUP BY priority');
        $stmt->execute([$userId]);
        $byPriority = ['low' => 0, 'medium' => 0, 'high' => 0];
        while ($row = $stmt->fetch()) {
            $byPriority[$row['priority']] = (int) $row['count'];
        }
        $stats['by_priority'] = $byPriority;

        // Overdue count
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND status != "done" AND due_date < CURDATE()');
        $stmt->execute([$userId]);
        $stats['overdue'] = (int) $stmt->fetch()['count'];

        echo json_encode($stats);
        exit;
    }

    // ── LIST TASKS ───────────────────────────────────────────
    if ($method === 'GET' && $taskId === null) {
        $sql    = 'SELECT t.*, c.name AS category_name, c.color AS category_color
                   FROM tasks t
                   LEFT JOIN categories c ON t.category_id = c.id
                   WHERE t.user_id = ?';
        $params = [$userId];

        if (!empty($_GET['status'])) {
            $sql .= ' AND t.status = ?';
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['priority'])) {
            $sql .= ' AND t.priority = ?';
            $params[] = $_GET['priority'];
        }
        if (!empty($_GET['category_id'])) {
            $sql .= ' AND t.category_id = ?';
            $params[] = (int) $_GET['category_id'];
        }
        if (!empty($_GET['search'])) {
            $sql .= ' AND (t.title LIKE ? OR t.description LIKE ?)';
            $like = '%' . $_GET['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY
                    FIELD(t.priority, "high", "medium", "low"),
                    t.due_date IS NULL,
                    t.due_date ASC,
                    t.created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    // ── GET SINGLE TASK ──────────────────────────────────────
    if ($method === 'GET' && $taskId !== null) {
        $stmt = $db->prepare(
            'SELECT t.*, c.name AS category_name, c.color AS category_color
             FROM tasks t
             LEFT JOIN categories c ON t.category_id = c.id
             WHERE t.id = ? AND t.user_id = ?'
        );
        $stmt->execute([$taskId, $userId]);
        $task = $stmt->fetch();

        if (!$task) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found.']);
            exit;
        }

        echo json_encode($task);
        exit;
    }

    // ── CREATE TASK ──────────────────────────────────────────
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        $title       = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $priority    = $data['priority']    ?? 'medium';
        $status      = $data['status']      ?? 'todo';
        $categoryId  = !empty($data['category_id']) ? (int) $data['category_id'] : null;
        $dueDate     = !empty($data['due_date'])     ? $data['due_date']          : null;

        if (!$title) {
            http_response_code(400);
            echo json_encode(['error' => 'Title is required.']);
            exit;
        }

        $stmt = $db->prepare(
            'INSERT INTO tasks (user_id, category_id, title, description, priority, status, due_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $categoryId, $title, $description, $priority, $status, $dueDate]);

        $newId = (int) $db->lastInsertId();

        // Return the created task with category info
        $stmt = $db->prepare(
            'SELECT t.*, c.name AS category_name, c.color AS category_color
             FROM tasks t
             LEFT JOIN categories c ON t.category_id = c.id
             WHERE t.id = ?'
        );
        $stmt->execute([$newId]);

        http_response_code(201);
        echo json_encode($stmt->fetch());
        exit;
    }

    // ── UPDATE TASK ──────────────────────────────────────────
    if ($method === 'PUT' && $taskId !== null) {
        // Verify ownership
        $stmt = $db->prepare('SELECT id FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$taskId, $userId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found.']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        $fields = [];
        $params = [];
        $allowed = ['title', 'description', 'priority', 'status', 'category_id', 'due_date'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field] === '' ? null : $data[$field];
            }
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update.']);
            exit;
        }

        $params[] = $taskId;
        $params[] = $userId;

        $sql = 'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?';
        $db->prepare($sql)->execute($params);

        // Return updated task
        $stmt = $db->prepare(
            'SELECT t.*, c.name AS category_name, c.color AS category_color
             FROM tasks t
             LEFT JOIN categories c ON t.category_id = c.id
             WHERE t.id = ?'
        );
        $stmt->execute([$taskId]);
        echo json_encode($stmt->fetch());
        exit;
    }

    // ── DELETE TASK ──────────────────────────────────────────
    if ($method === 'DELETE' && $taskId !== null) {
        $stmt = $db->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$taskId, $userId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found.']);
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
