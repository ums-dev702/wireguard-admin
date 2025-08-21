<?php

namespace WireGuardAdmin;

/**
 * MySQL Database Class for WireGuard Administration System
 * 
 * Provides a secure, efficient interface for database operations including:
 * - Connection management with PDO
 * - Table creation and schema management
 * - CRUD operations with prepared statements
 * - Transaction support
 * - Error handling
 */
class Database {
    private $db;
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $port;
    private $tablePrefix;

    /**
     * Database constructor
     *
     * @param string|null $host Database host
     * @param string|null $dbname Database name
     * @param string|null $username Database username
     * @param string|null $password Database password
     * @param int $port Database port (default: 3306)
     * @param string $prefix Table prefix for multi-tenant environments
     */
    public function __construct($host = null, $dbname = null, $username = null, $password = null, $port = 3306, $prefix = '') {
        $this->host = $host ?? defined('DB_HOST') ? DB_HOST : 'localhost';
        $this->dbname = $dbname ?? defined('DB_NAME') ? DB_NAME : 'wireguard_admin';
        $this->username = $username ?? defined('DB_USER') ? DB_USER : 'root';
        $this->password = $password ?? defined('DB_PASS') ? DB_PASS : '';
        $this->port = $port ?? defined('DB_PORT') ? DB_PORT : 3306;
        $this->tablePrefix = $prefix;
        
        $this->connect();
    }

    /**
     * Establish database connection
     *
     * @throws \Exception If connection fails
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";
            $this->db = new \PDO($dsn, $this->username, $this->password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                \PDO::ATTR_PERSISTENT => true
            ]);
            
            $this->createTables();
        } catch (\PDOException $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Ensure database connection is active
     */
    public function ensureConnection() {
        try {
            $this->db->query('SELECT 1');
        } catch (\PDOException $e) {
            $this->connect();
        }
    }

    /**
     * Create database tables if they don't exist
     */
    public function createTables() {
        $tables = [
            "users" => "
                CREATE TABLE IF NOT EXISTS {$this->tablePrefix}users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    email VARCHAR(100),
                    role VARCHAR(20) DEFAULT 'admin',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_login TIMESTAMP NULL DEFAULT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    INDEX idx_username (username),
                    INDEX idx_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ",
            "peers" => "
                CREATE TABLE IF NOT EXISTS {$this->tablePrefix}peers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    public_key VARCHAR(255) UNIQUE NOT NULL,
                    private_key VARCHAR(255) NOT NULL,
                    allowed_ips VARCHAR(255) NOT NULL,
                    endpoint VARCHAR(255),
                    dns VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    is_active TINYINT(1) DEFAULT 1,
                    last_handshake TIMESTAMP NULL DEFAULT NULL,
                    transfer_rx BIGINT DEFAULT 0,
                    transfer_tx BIGINT DEFAULT 0,
                    INDEX idx_public_key (public_key),
                    INDEX idx_name (name),
                    INDEX idx_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ",
            "port_forwards" => "
                CREATE TABLE IF NOT EXISTS {$this->tablePrefix}port_forwards (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    peer_id INT,
                    external_port INT NOT NULL,
                    internal_port INT NOT NULL,
                    protocol VARCHAR(10) DEFAULT 'tcp',
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    is_active TINYINT(1) DEFAULT 1,
                    FOREIGN KEY (peer_id) REFERENCES {$this->tablePrefix}peers(id) ON DELETE CASCADE,
                    INDEX idx_peer_id (peer_id),
                    INDEX idx_external_port (external_port),
                    INDEX idx_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ",
            "settings" => "
                CREATE TABLE IF NOT EXISTS {$this->tablePrefix}settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_key (setting_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ",
            "audit_log" => "
                CREATE TABLE IF NOT EXISTS {$this->tablePrefix}audit_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    action VARCHAR(100) NOT NULL,
                    description TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES {$this->tablePrefix}users(id) ON DELETE SET NULL,
                    INDEX idx_user_id (user_id),
                    INDEX idx_action (action),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ",
            "installation_status" => "
                CREATE TABLE IF NOT EXISTS {$this->tablePrefix}installation_status (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    step VARCHAR(50) NOT NULL,
                    status VARCHAR(20) DEFAULT 'pending',
                    message TEXT,
                    completed_at TIMESTAMP NULL DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_step (step),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ",
            "remember_tokens" => "
                CREATE TABLE IF NOT EXISTS {$this->tablePrefix}remember_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token_hash VARCHAR(255) NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES {$this->tablePrefix}users(id) ON DELETE CASCADE,
                    INDEX idx_user_id (user_id),
                    INDEX idx_token_hash (token_hash),
                    INDEX idx_expires_at (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            "
        ];

        foreach ($tables as $tableName => $sql) {
            try {
                $this->db->exec($sql);
            } catch (\PDOException $e) {
                throw new \Exception("Failed to create table {$tableName}: " . $e->getMessage());
            }
        }
    }

    /**
     * Get PDO connection instance
     *
     * @return \PDO
     */
    public function getConnection() {
        $this->ensureConnection();
        return $this->db;
    }

    /**
     * Execute a database query
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return \PDOStatement
     * @throws \Exception
     */
    public function query($sql, $params = []) {
        $this->ensureConnection();
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            throw new \Exception('Query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
        }
    }

    /**
     * Select multiple rows from database
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array
     */
    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Select a single row from database
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array|null
     */
    public function selectOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Insert a new record
     *
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return int Last insert ID
     */
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$this->tablePrefix}{$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->db->lastInsertId();
    }

    /**
     * Update existing records
     *
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @param string $where WHERE clause
     * @param array $whereParams Parameters for WHERE clause
     * @return int Number of affected rows
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = implode(', ', array_map(fn($key) => "{$key} = :{$key}", array_keys($data)));
        $sql = "UPDATE {$this->tablePrefix}{$table} SET {$setClause} WHERE {$where}";
        
        $stmt = $this->query($sql, array_merge($data, $whereParams));
        return $stmt->rowCount();
    }

    /**
     * Delete records
     *
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $params Parameters for WHERE clause
     * @return int Number of affected rows
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$this->tablePrefix}{$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Start a transaction
     *
     * @return bool
     */
    public function beginTransaction() {
        $this->ensureConnection();
        return $this->db->beginTransaction();
    }

    /**
     * Commit a transaction
     *
     * @return bool
     */
    public function commit() {
        return $this->db->commit();
    }

    /**
     * Rollback a transaction
     *
     * @return bool
     */
    public function rollback() {
        return $this->db->rollback();
    }

    /**
     * Create a database backup
     *
     * @param string $filePath Path to save backup file
     * @return bool
     * @throws \Exception
     */
    public function backup($filePath) {
        try {
            $tables = $this->select("SHOW TABLES LIKE '{$this->tablePrefix}%'");
            $backupSql = "-- WireGuard Admin Database Backup\n";
            $backupSql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($tables as $table) {
                $tableName = current($table);
                $backupSql .= "--\n-- Table: $tableName\n--\n";
                
                // Get table structure
                $createTable = $this->selectOne("SHOW CREATE TABLE $tableName");
                $backupSql .= $createTable['Create Table'] . ";\n\n";
                
                // Get table data
                $rows = $this->select("SELECT * FROM $tableName");
                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $columns = implode('`, `', array_keys($row));
                        $values = implode("', '", array_map(function($value) {
                            return str_replace("'", "''", $value);
                        }, $row));
                        
                        $backupSql .= "INSERT INTO `$tableName` (`$columns`) VALUES ('$values');\n";
                    }
                    $backupSql .= "\n";
                }
            }
            
            return file_put_contents($filePath, $backupSql) !== false;
        } catch (\Exception $e) {
            throw new \Exception('Backup failed: ' . $e->getMessage());
        }
    }
}