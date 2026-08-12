<?php
// ============================================================
// includes/db.php  — PDO database connection
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'barangay_db');
define('DB_USER', 'root');          // ← change to your MySQL user
define('DB_PASS', '');              // ← change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── Convenience wrappers ──────────────────────────────────────

/**
 * Run a query and return all matching rows.
 */
function dbFetchAll(string $sql, array $params = []): array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Run a query and return the first matching row (or null).
 */
function dbFetchOne(string $sql, array $params = []): ?array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Run an INSERT / UPDATE / DELETE and return affected row count.
 */
function dbExecute(string $sql, array $params = []): int {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Run an INSERT and return the new row's ID.
 */
function dbInsert(string $sql, array $params = []): int {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return (int) getDB()->lastInsertId();
}

// ── JSON response helpers ─────────────────────────────────────

function jsonOk(array $data = [], string $message = 'Success'): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
    exit;
}

function jsonError(string $message = 'An error occurred', int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// ── Session helper ────────────────────────────────────────────

function requireAdminLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_id'])) {
        header('Location: admin_login.php');
        exit;
    }
}

function requireResidentLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['resident_id'])) {
        header('Location: index.php');
        exit;
    }
}

function logActivity(string $action, string $module = '', string $userType = 'admin'): void {
    $userId = $userType === 'admin'
        ? ($_SESSION['admin_id'] ?? null)
        : ($_SESSION['resident_id'] ?? null);
    dbExecute(
        'INSERT INTO activity_log (user_type, user_id, action, module) VALUES (?,?,?,?)',
        [$userType, $userId, $action, $module]
    );
}

// ── Resident ID generator ─────────────────────────────────────

function generateResidentId(): string {
    $pdo = getDB();
    // Lock the row, increment sequence atomically
    $pdo->beginTransaction();
    try {
        $year = (int) dbFetchOne(
            "SELECT setting_value FROM barangay_settings WHERE setting_key='resident_id_year'"
        )['setting_value'];
        $seq  = (int) dbFetchOne(
            "SELECT setting_value FROM barangay_settings WHERE setting_key='resident_id_seq'"
        )['setting_value'];

        $currentYear = (int) date('Y');
        if ($year !== $currentYear) {
            $seq  = 0;
            $year = $currentYear;
            dbExecute(
                "UPDATE barangay_settings SET setting_value=? WHERE setting_key='resident_id_year'",
                [$year]
            );
        }
        $seq++;
        dbExecute(
            "UPDATE barangay_settings SET setting_value=? WHERE setting_key='resident_id_seq'",
            [$seq]
        );
        $pdo->commit();
        return sprintf('BRGY-%d-%04d', $year, $seq);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ── Request number generator ──────────────────────────────────

function generateRequestNumber(): string {
    $year  = date('Y');
    $count = (int) (dbFetchOne(
        "SELECT COUNT(*) AS cnt FROM document_requests WHERE YEAR(created_at)=?",
        [$year]
    )['cnt'] ?? 0);
    return sprintf('REQ-%s-%04d', $year, $count + 1);
}
// Auto-load site settings after DB is available
if (file_exists(__DIR__ . '/site_settings.php')) {
    require_once __DIR__ . '/site_settings.php';
    require_once __DIR__ . '/remember_me.php';
}
