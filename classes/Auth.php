<?php

namespace WireGuardAdmin;

class Auth
{
    private $db;
    private $sessionTimeout;

    public function __construct(Database $db, $sessionTimeout = 1800)
    {
        $this->db = $db;
        $this->sessionTimeout = $sessionTimeout;
        $this->startSession();
    }

    private function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login($username, $password, $rememberMe = false)
    {
        try {
            $user = $this->db->selectOne(
                "SELECT * FROM users WHERE username = ? AND status = 'active'",
                [$username]
            );

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['authenticated'] = true;
                $_SESSION['last_activity'] = time();
                $_SESSION['login_time'] = time();

                // Update last login
                $this->db->update(
                    'users',
                    ['last_login' => date('Y-m-d H:i:s')],
                    'id = ?',
                    [$user['id']]
                );

                // Log the login
                $this->logActivity($user['id'], 'login', 'User logged in successfully');

                // Handle remember me
                if ($rememberMe) {
                    $this->setRememberMeCookie($user['id']);
                }

                return true;
            }

            // Log failed login attempt
            $this->logActivity(null, 'failed_login', "Failed login attempt for username: {$username}");
            return false;
        } catch (\Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    public function logout()
    {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        }

        session_unset();
        session_destroy();

        // Clear remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }

    public function isAuthenticated()
    {
        // Check session authentication
        if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
            // Check session timeout
            if (time() - $_SESSION['last_activity'] > $this->sessionTimeout) {
                $this->logout();
                return false;
            }

            $_SESSION['last_activity'] = time();
            return true;
        }

        // Check remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            return $this->validateRememberToken($_COOKIE['remember_token']);
        }

        return false;
    }

    public function requireAuth($redirectTo = '/login.php')
    {
        if (!$this->isAuthenticated()) {
            header("Location: {$redirectTo}");
            exit;
        }
    }

    public function hasRole($role)
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }

    public function getCurrentUser()
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return $this->db->selectOne(
            "SELECT id, username, email, role, created_at, last_login FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
    }

    public function createUser($username, $password, $email = null, $role = 'admin')
    {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            return $this->db->insert('users', [
                'username' => $username,
                'password' => $hashedPassword,
                'email' => $email,
                'role' => $role
            ]);
        } catch (\Exception $e) {
            throw new \Exception("Failed to create user: " . $e->getMessage());
        }
    }

    public function changePassword($userId, $newPassword)
    {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $this->db->update(
                'users',
                ['password' => $hashedPassword],
                'id = ?',
                [$userId]
            );

            $this->logActivity($userId, 'password_change', 'Password changed successfully');
            return true;
        } catch (\Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            return false;
        }
    }

    private function setRememberMeCookie($userId)
    {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        // Store hashed token in database (you'd need to add this table)
        $this->db->insert('remember_tokens', [
            'user_id' => $userId,
            'token_hash' => $hashedToken,
            'expires_at' => date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)) // 30 days
        ]);

        // Set cookie with unhashed token
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
    }

    private function validateRememberToken($token)
    {
        $hashedToken = hash('sha256', $token);

        $result = $this->db->selectOne(
            "SELECT u.* FROM users u 
             JOIN remember_tokens rt ON u.id = rt.user_id 
             WHERE rt.token_hash = ? AND rt.expires_at > NOW() AND u.is_active = 1",
            [$hashedToken]
        );

        if ($result) {
            $_SESSION['user_id'] = $result['id'];
            $_SESSION['username'] = $result['username'];
            $_SESSION['role'] = $result['role'];
            $_SESSION['authenticated'] = true;
            $_SESSION['last_activity'] = time();

            return true;
        }

        return false;
    }

    public function logActivity($userId, $action, $description, $ipAddress = null, $userAgent = null)
    {
        try {
            $this->db->insert('audit_log', [
                'user_id' => $userId,
                'action' => $action,
                'description' => $description,
                'ip_address' => $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }

    public function getAuditLog($limit = 100, $offset = 0)
    {
        return $this->db->select(
            "SELECT al.*, u.username 
             FROM audit_log al 
             LEFT JOIN users u ON al.user_id = u.id 
             ORDER BY al.created_at DESC 
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    public function validateCSRFToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
