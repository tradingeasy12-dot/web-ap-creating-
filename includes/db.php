<?php
/**
 * Single shared PDO connection. Included by every page that touches the database.
 */

if (!file_exists(__DIR__ . '/../config.php')) {
    http_response_code(500);
    die('config.php not found. Run install.php first, or copy config.sample.php to config.php and fill in your database details.');
}

require_once __DIR__ . '/../config.php';

class SQLitePDOWrapper extends PDO {
    private function rewriteQuery($sql) {
        // Rewrite MySQL INTERVAL 7 DAY to SQLite datetime('now', '-7 days')
        $sql = preg_replace('/NOW\(\)\s*-\s*INTERVAL\s*(\d+)\s*DAY/i', "datetime('now', '-$1 days')", $sql);
        // Rewrite CURDATE() to date('now')
        $sql = str_ireplace('CURDATE()', "date('now')", $sql);
        // Rewrite GROUP_CONCAT(t.name SEPARATOR ', ') to GROUP_CONCAT(t.name, ', ')
        $sql = preg_replace('/GROUP_CONCAT\(([^S]+)\s+SEPARATOR\s+([^)]+)\)/i', 'GROUP_CONCAT($1, $2)', $sql);
        return $sql;
    }

    public function query($query, $fetchMode = null, ...$params) {
        $query = $this->rewriteQuery($query);
        if ($fetchMode === null) {
            return parent::query($query);
        }
        return parent::query($query, $fetchMode, ...$params);
    }

    public function prepare($query, $options = []) {
        $query = $this->rewriteQuery($query);
        return parent::prepare($query, $options);
    }

    public function exec($statement): int|false {
        $statement = $this->rewriteQuery($statement);
        return parent::exec($statement);
    }
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        if (strpos(DB_HOST, 'sqlite:') === 0 || DB_HOST === 'sqlite') {
            $dbPath = DB_NAME;
            // Handle absolute or relative sqlite path
            if (strpos($dbPath, '/') !== 0 && strpos($dbPath, ':') === false) {
                $dbPath = __DIR__ . '/../' . $dbPath;
            }
            try {
                $pdo = new SQLitePDOWrapper('sqlite:' . $dbPath, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $pdo->exec('PRAGMA foreign_keys = ON;');
                
                // Emulate MySQL functions in SQLite using PHP-defined SQLite functions!
                $pdo->sqliteCreateFunction('CURDATE', function() {
                    return date('Y-m-d');
                });
                $pdo->sqliteCreateFunction('NOW', function() {
                    return date('Y-m-d H:i:s');
                });
                $pdo->sqliteCreateFunction('FIELD', function() {
                    $args = func_get_args();
                    if (count($args) < 2) return 0;
                    $val = $args[0];
                    for ($i = 1; $i < count($args); $i++) {
                        if ($args[$i] === $val) return $i;
                    }
                    return 0;
                });
            } catch (PDOException $e) {
                die('SQLite connection failed: ' . htmlspecialchars($e->getMessage()) . PHP_EOL);
            }
            return $pdo;
        }

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (PHP_SAPI !== 'cli') {
                http_response_code(500);
            }
            die('Database connection failed: ' . htmlspecialchars($e->getMessage()) . PHP_EOL);
        }
    }

    return $pdo;
}

/** Simple reversible encryption for storage API secrets — not for passwords (those use hashing). */
function encrypt_secret(string $plain): string
{
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', APP_SECRET_KEY, 0, $iv);
    return base64_encode($iv . $cipher);
}

function decrypt_secret(string $encoded): string
{
    $data = base64_decode($encoded);
    $iv = substr($data, 0, 16);
    $cipher = substr($data, 16);
    return (string) openssl_decrypt($cipher, 'AES-256-CBC', APP_SECRET_KEY, 0, $iv);
}