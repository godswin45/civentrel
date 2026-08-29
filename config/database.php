<?php
// Function to load .env file into getenv/$_ENV
if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

loadEnv(__DIR__ . '/../.env');

class Database {
    private static $instance = null;
    private $pdo;

    public function __construct() {
        $host = getenv('DB_HOST') ?: (getenv('TREASURY_DB_HOST') ?: 'localhost');
        $port = getenv('DB_PORT') ?: (getenv('TREASURY_DB_PORT') ?: '3306');
        $db   = getenv('DB_NAME') ?: (getenv('TREASURY_DB_NAME') ?: 'treasury');
        $user = getenv('DB_USER') ?: (getenv('TREASURY_DB_USER') ?: 'root');
        $pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ((getenv('TREASURY_DB_PASSWORD') !== false) ? getenv('TREASURY_DB_PASSWORD') : '');
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            // Attempt to connect without dbname to create DB if needed
            try {
                $dsnNoDb = "mysql:host={$host};port={$port};charset={$charset}";
                $pdoTmp = new PDO($dsnNoDb, $user, $pass, $options);
                $pdoTmp->exec("CREATE DATABASE IF NOT EXISTS `{$db}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                $this->pdo = new PDO($dsn, $user, $pass, $options);
            } catch (\PDOException $e2) {
                throw new \PDOException("MySQL Connection Error: " . $e2->getMessage(), (int)$e2->getCode());
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getPdo() {
        return $this->pdo;
    }

    /**
     * Run a SQL query with parameter binding and return result array
     */
    public function query($sql, $params = [], $ignoredMethodParam = null) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a SQL statement (INSERT/UPDATE/DELETE) with parameters returning affected row count
     */
    public function exec($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Helper: Select rows from a table with filters
     */
    public function select($table, $filters = [], $columns = '*', $orderBy = '') {
        $sql = "SELECT {$columns} FROM `{$table}`";
        $where = [];
        $params = [];

        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                $where[] = "`{$key}` = :{$key}";
                $params[$key] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        if (!empty($orderBy)) {
            $sql .= " ORDER BY {$orderBy}";
        }

        return $this->query($sql, $params);
    }

    /**
     * Helper: Insert record into a table
     */
    public function insert($table, $data) {
        $keys = array_keys($data);
        $fields = implode('`, `', $keys);
        $placeholders = ':' . implode(', :', $keys);

        $sql = "INSERT INTO `{$table}` (`{$fields}`) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        $lastId = $this->pdo->lastInsertId();
        return $lastId ? (int)$lastId : true;
    }

    /**
     * Helper: Update record in a table
     */
    public function update($table, $data, $filters) {
        $set = [];
        $params = [];

        foreach ($data as $key => $value) {
            $set[] = "`{$key}` = :set_{$key}";
            $params["set_{$key}"] = $value;
        }

        $where = [];
        foreach ($filters as $key => $value) {
            $where[] = "`{$key}` = :where_{$key}";
            $params["where_{$key}"] = $value;
        }

        $sql = "UPDATE `{$table}` SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Helper: Delete record from a table
     */
    public function delete($table, $filters) {
        $where = [];
        $params = [];

        foreach ($filters as $key => $value) {
            $where[] = "`{$key}` = :{$key}";
            $params[$key] = $value;
        }

        $sql = "DELETE FROM `{$table}` WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}

class DatabaseDB extends Database {}

// Global instances for backwards compatibility
$db = Database::getInstance();
$treasuryDb = $db;