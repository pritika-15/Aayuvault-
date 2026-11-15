<?php
/**
 * reminders_api.php — AayuVault Reminders API
 *
 * Handles AJAX calls from reminders.js.
 * Place alongside reminders.html on a PHP-capable server.
 *
 * Endpoints:
 *   GET  reminders_api.php?action=get_reminders       → list all reminders
 *   GET  reminders_api.php?action=get_reminder&id=N   → single reminder
 *   POST reminders_api.php?action=add_reminder        → create (JSON body)
 *   POST reminders_api.php?action=update_reminder&id=N → update (JSON body)
 *   POST reminders_api.php?action=delete_reminder&id=N → delete
 */

// ── CONFIGURATION ─────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'clinic_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

// ── HEADERS ───────────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── DB CONNECTION ─────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHAR);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// ── TABLE SETUP ───────────────────────────────────────────────
function ensureTable(): void {
    getDB()->exec("
        CREATE TABLE IF NOT EXISTS reminders (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            date_label  VARCHAR(50)   NOT NULL,
            time_label  VARCHAR(20)   DEFAULT '',
            type        VARCHAR(50)   DEFAULT 'custom',
            title       VARCHAR(300)  NOT NULL,
            description TEXT          DEFAULT '',
            is_active   TINYINT(1)    DEFAULT 1,
            created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ── HELPERS ───────────────────────────────────────────────────
function respond(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function error(string $msg, int $code = 400): void {
    respond(['error' => $msg], $code);
}

function parseBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) error('Invalid JSON body');
    return $data;
}

function sanitizeType(string $type): string {
    $allowed = ['pill', 'appointment', 'record', 'custom'];
    return in_array($type, $allowed, true) ? $type : 'custom';
}

function formatRow(array $row): array {
    $row['is_active'] = (bool) $row['is_active'];
    return $row;
}

// ── ROUTER ────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    ensureTable();

    switch ($action) {

        // ── GET ALL REMINDERS ──────────────────────────────────
        case 'get_reminders':
            $stmt = getDB()->query(
                "SELECT * FROM reminders WHERE is_active = 1 ORDER BY created_at DESC"
            );
            $rows = array_map('formatRow', $stmt->fetchAll());
            respond(['reminders' => $rows]);
            break;

        // ── GET SINGLE REMINDER ────────────────────────────────
        case 'get_reminder':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) error('Invalid id');

            $stmt = getDB()->prepare("SELECT * FROM reminders WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) error('Reminder not found', 404);
            respond(['reminder' => formatRow($row)]);
            break;

        // ── ADD REMINDER ───────────────────────────────────────
        case 'add_reminder':
            if ($method !== 'POST') error('POST required', 405);
            $body = parseBody();

            $dateLabel = trim($body['date']  ?? $body['date_label'] ?? '');
            $timeLabel = trim($body['time']  ?? $body['time_label'] ?? '');
            $type      = sanitizeType(trim($body['type'] ?? 'custom'));
            $title     = trim($body['title'] ?? '');
            $desc      = trim($body['desc']  ?? $body['description'] ?? '');

            if (!$title) error('title is required');

            $stmt = getDB()->prepare("
                INSERT INTO reminders (date_label, time_label, type, title, description)
                VALUES (:date_label, :time_label, :type, :title, :description)
            ");
            $stmt->execute([
                ':date_label'   => $dateLabel,
                ':time_label'   => $timeLabel,
                ':type'         => $type,
                ':title'        => $title,
                ':description'  => $desc,
            ]);
            respond(['success' => true, 'id' => (int) getDB()->lastInsertId()], 201);
            break;

        // ── UPDATE REMINDER ────────────────────────────────────
        case 'update_reminder':
            if ($method !== 'POST') error('POST required', 405);
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) error('Invalid id');
            $body = parseBody();

            $fields = [];
            $params = [];

            if (isset($body['title'])) {
                $fields[] = 'title = :title';
                $params[':title'] = trim($body['title']);
            }
            if (isset($body['description']) || isset($body['desc'])) {
                $fields[] = 'description = :description';
                $params[':description'] = trim($body['description'] ?? $body['desc'] ?? '');
            }
            if (isset($body['type'])) {
                $fields[] = 'type = :type';
                $params[':type'] = sanitizeType(trim($body['type']));
            }
            if (isset($body['date_label']) || isset($body['date'])) {
                $fields[] = 'date_label = :date_label';
                $params[':date_label'] = trim($body['date_label'] ?? $body['date'] ?? '');
            }
            if (isset($body['time_label']) || isset($body['time'])) {
                $fields[] = 'time_label = :time_label';
                $params[':time_label'] = trim($body['time_label'] ?? $body['time'] ?? '');
            }

            if (empty($fields)) error('No fields to update');

            $params[':id'] = $id;
            $stmt = getDB()->prepare(
                "UPDATE reminders SET " . implode(', ', $fields) . " WHERE id = :id"
            );
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) error('Reminder not found or unchanged', 404);
            respond(['success' => true]);
            break;

        // ── DELETE / DISMISS REMINDER ──────────────────────────
        case 'delete_reminder':
            if ($method !== 'POST') error('POST required', 405);
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) error('Invalid id');

            // Soft-delete: mark inactive
            $stmt = getDB()->prepare(
                "UPDATE reminders SET is_active = 0 WHERE id = ?"
            );
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) error('Reminder not found', 404);
            respond(['success' => true]);
            break;

        // ── UNKNOWN ────────────────────────────────────────────
        default:
            error('Unknown action', 404);
    }

} catch (PDOException $e) {
    error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    error('Server error: ' . $e->getMessage(), 500);
}