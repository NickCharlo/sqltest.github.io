<?php
/**
 * TaskFlow — Authentication API
 *
 * Endpoints:
 *   POST /php/auth.php?action=register   { username, email, password }
 *   POST /php/auth.php?action=login      { email, password }
 *   GET  /php/auth.php?action=me         (requires session)
 *   GET  /php/auth.php?action=logout
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

try {
    $db = getDB();

    switch ($action) {

        // ─── REGISTER ────────────────────────────────────────
        case 'register':
            $data = json_decode(file_get_contents('php://input'), true);
            $username = trim($data['username'] ?? '');
            $email    = trim($data['email']    ?? '');
            $password = $data['password']       ?? '';

            if (!$username || !$email || strlen($password) < 6) {
                http_response_code(400);
                echo json_encode(['error' => 'All fields required. Password must be at least 6 characters.']);
                exit;
            }

            // Check uniqueness
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Username or email already taken.']);
                exit;
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $colors = ['#6366f1','#ef4444','#10b981','#f59e0b','#3b82f6','#ec4899','#8b5cf6'];
            $color = $colors[array_rand($colors)];

            $stmt = $db->prepare(
                'INSERT INTO users (username, email, password, avatar_color) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$username, $email, $hash, $color]);

            $userId = (int)$db->lastInsertId();

            // Create default categories
            $defaults = [
                ['Work',     '#6366f1'],
                ['Personal', '#10b981'],
                ['Learning', '#f59e0b'],
            ];
            $catStmt = $db->prepare('INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)');
            foreach ($defaults as [$name, $catColor]) {
                $catStmt->execute([$userId, $name, $catColor]);
            }

            $_SESSION['user_id'] = $userId;

            echo json_encode([
                'success' => true,
                'user'    => [
                    'id'           => $userId,
                    'username'     => $username,
                    'email'        => $email,
                    'avatar_color' => $color,
                ],
            ]);
            break;

        // ─── LOGIN ───────────────────────────────────────────
        case 'login':
            $data = json_decode(file_get_contents('php://input'), true);
            $email    = trim($data['email']    ?? '');
            $password = $data['password']       ?? '';

            $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid email or password.']);
                exit;
            }

            $_SESSION['user_id'] = (int)$user['id'];

            echo json_encode([
                'success' => true,
                'user'    => [
                    'id'           => (int)$user['id'],
                    'username'     => $user['username'],
                    'email'        => $user['email'],
                    'avatar_color' => $user['avatar_color'],
                ],
            ]);
            break;

        // ─── CURRENT USER ────────────────────────────────────
        case 'me':
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Not authenticated.']);
                exit;
            }

            $stmt = $db->prepare('SELECT id, username, email, avatar_color FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            echo json_encode(['user' => $user]);
            break;

        // ─── LOGOUT ──────────────────────────────────────────
        case 'logout':
            session_destroy();
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
