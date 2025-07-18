<?php

namespace WireGuardAdmin;

class Database {
    private $db;
    private $dbPath;

    public function __construct($dbPath = null) {
        $this->dbPath = $dbPath ?? DB_PATH;
        $this->connect();
    }

    private function connect() {
        try {
            $this->db = new \PDO('sqlite:' . $this->dbPath);
            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->createTables();
        } catch (\PDOException $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    private function createTables() {
        $sql = "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100),
                role VARCHAR(20) DEFAULT 'admin',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME,
                is_active INTEGER DEFAULT 1
            );

            CREATE TABLE IF NOT EXISTS peers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                public_key VARCHAR(255) UNIQUE NOT NULL,
                private_key VARCHAR(255) NOT NULL,
                allowed_ips VARCHAR(255) NOT NULL,
                endpoint VARCHAR(255),
                dns VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_active INTEGER DEFAULT 1,
                last_handshake DATETIME,
                transfer_rx INTEGER DEFAULT 0,
                transfer_tx INTEGER DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS port_forwards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                peer_id INTEGER,
                external_port INTEGER NOT NULL,
                internal_port INTEGER NOT NULL,
                protocol VARCHAR(10) DEFAULT 'tcp',
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_active INTEGER DEFAULT 1,
                FOREIGN KEY (peer_id) REFERENCES peers(id)
            );

            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key VARCHAR(100) UNIQUE NOT NULL,
                value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS installation_status (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                step VARCHAR(50) NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                message TEXT,
                completed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ";

        $this->db->exec($sql);
    }

    public function getConnection() {
        return $this->db;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            throw new \Exception('Query failed: ' . $e->getMessage());
        }
    }

    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function selectOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->db->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $setClause = implode(', ', array_map(fn($key) => "{$key} = :{$key}", array_keys($data)));
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        return $this->query($sql, array_merge($data, $whereParams));
    }

    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }

    public function beginTransaction() {
        return $this->db->beginTransaction();
    }

    public function commit() {
        return $this->db->commit();
    }

    public function rollback() {
        return $this->db->rollback();
    }
}
