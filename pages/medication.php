<?php
/**
 * api.php — AayuVault Medical Records API
 *
 * Handles AJAX requests from records.js.
 * Place this file in the same directory as records.html.
 *
 * Endpoints:
 *   GET  api.php?action=get_records          → list all records
 *   GET  api.php?action=get_record&id=N      → single record
 *   POST api.php?action=add_record           → add a new record (JSON body)
 *   POST api.php?action=delete_record&id=N   → delete a record
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

// Pre-flight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── DB CONNECTION ─────────────────────────────────────────────

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHAR
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// ── TABLE SETUP (runs once) ───────────────────────────────────

function ensureTable(): void {
    getDB()->exec("
        CREATE TABLE IF NOT EXISTS medical_records (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            date_label VARCHAR(30)  NOT NULL,
            title      VARCHAR(200) NOT NULL,
            subtitle   VARCHAR(300) DEFAULT '',
            type       VARCHAR(50)  DEFAULT 'lab',
            has_pdf    TINYINT(1)   DEFAULT 0,
            pdf_path   VARCHAR(500) DEFAULT '',
            created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ── RESPONSE HELPERS ──────────────────────────────────────────

function respond(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function error(string $msg, int $code = 400): void {
    respond(['error' => $msg], $code);
}

// ── ROUTE ─────────────────────────────────────────────────────

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    ensureTable();

    switch ($action) {

        // ── GET ALL RECORDS ────────────────────────────────────
        case 'get_records':
            $stmt = getDB()->query(
                "SELECT * FROM medical_records ORDER BY created_at DESC"
            );
            $records = $stmt->fetchAll();

            // Cast has_pdf to bool for JS
            foreach ($records as &$r) {
                $r['has_pdf'] = (bool) $r['has_pdf'];
            }

            respond(['records' => $records]);
            break;

        // ── GET SINGLE RECORD ──────────────────────────────────
        case 'get_record':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) error('Invalid id');

            $stmt = getDB()->prepare(
                "SELECT * FROM medical_records WHERE id = ?"
            );
            $stmt->execute([$id]);
            $record = $stmt->fetch();

            if (!$record) error('Record not found', 404);
            $record['has_pdf'] = (bool) $record['has_pdf'];
            respond(['record' => $record]);
            break;

        // ── ADD RECORD ────────────────────────────────────────
        case 'add_record':
            if ($method !== 'POST') error('POST required', 405);

            $body = json_decode(file_get_contents('php://input'), true);
            if (!$body) error('Invalid JSON body');

            $dateLabel = trim($body['date_label'] ?? '');
            $title     = trim($body['title']      ?? '');
            $subtitle  = trim($body['subtitle']   ?? '');
            $type      = trim($body['type']       ?? 'lab');
            $hasPdf    = !empty($body['has_pdf']) ? 1 : 0;
            $pdfPath   = trim($body['pdf_path']   ?? '');

            if (!$dateLabel || !$title) {
                error('date_label and title are required');
            }

            $allowed = ['lab', 'radiology', 'prescription', 'visit', 'vaccination', 'insurance'];
            if (!in_array($type, $allowed, true)) $type = 'lab';

            $stmt = getDB()->prepare("
                INSERT INTO medical_records
                    (date_label, title, subtitle, type, has_pdf, pdf_path)
                VALUES
                    (:date_label, :title, :subtitle, :type, :has_pdf, :pdf_path)
            ");
            $stmt->execute([
                ':date_label' => $dateLabel,
                ':title'      => $title,
                ':subtitle'   => $subtitle,
                ':type'       => $type,
                ':has_pdf'    => $hasPdf,
                ':pdf_path'   => $pdfPath,
            ]);

            $newId = (int) getDB()->lastInsertId();
            respond(['success' => true, 'id' => $newId], 201);
            break;

        // ── DELETE RECORD ─────────────────────────────────────
        case 'delete_record':
            if ($method !== 'POST') error('POST required', 405);

            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) error('Invalid id');

            $stmt = getDB()->prepare(
                "DELETE FROM medical_records WHERE id = ?"
            );
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) error('Record not found', 404);
            respond(['success' => true]);
            break;

        // ── 404 ───────────────────────────────────────────────
        default:
            error('Unknown action', 404);
    }

} catch (PDOException $e) {
    // Never expose raw DB errors to clients in production
    error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    error('Server error: ' . $e->getMessage(), 500);
}