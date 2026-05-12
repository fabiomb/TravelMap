<?php
/**
 * Modelo: User
 * 
 * Gestiona las operaciones CRUD de usuarios
 */

class User {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Obtiene todos los usuarios
     */
    public function getAll($orderBy = 'username ASC') {
        $sql = "SELECT id, username, created_at FROM users ORDER BY {$orderBy}";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un usuario por ID
     */
    public function getById($id) {
        $sql = "SELECT id, username, created_at FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un usuario por nombre de usuario
     */
    public function getByUsername($username) {
        $sql = "SELECT id, username, password_hash, created_at FROM users WHERE username = :username";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['username' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta el total de usuarios
     */
    public function count() {
        $sql = "SELECT COUNT(*) as total FROM users";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['total'];
    }

    /**
     * Crea un nuevo usuario
     */
    public function create($data) {
        $sql = "INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)";
        
        $stmt = $this->db->prepare($sql);
        
        $success = $stmt->execute([
            'username' => $data['username'],
            'password_hash' => $data['password_hash']
        ]);

        return $success ? $this->db->lastInsertId() : false;
    }

    /**
     * Actualiza un usuario
     */
    public function update($id, $data) {
        // Si hay nueva contraseña, actualizar también
        if (!empty($data['password_hash'])) {
            $sql = "UPDATE users SET username = :username, password_hash = :password_hash WHERE id = :id";
            $params = [
                'id' => $id,
                'username' => $data['username'],
                'password_hash' => $data['password_hash']
            ];
        } else {
            // Solo actualizar username
            $sql = "UPDATE users SET username = :username WHERE id = :id";
            $params = [
                'id' => $id,
                'username' => $data['username']
            ];
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Elimina un usuario
     */
    public function delete($id) {
        $sql = "DELETE FROM users WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Verifica si un nombre de usuario ya existe
     */
    public function usernameExists($username, $excludeId = null) {
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as count FROM users WHERE username = :username AND id != :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['username' => $username, 'id' => $excludeId]);
        } else {
            $sql = "SELECT COUNT(*) as count FROM users WHERE username = :username";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['username' => $username]);
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Valida los datos de un usuario
     */
    public function validate($data, $isEdit = false) {
        $errors = [];

        // Validar username
        if (empty($data['username'])) {
            $errors['username'] = 'El nombre de usuario es obligatorio';
        } elseif (strlen($data['username']) < 3) {
            $errors['username'] = 'El nombre de usuario debe tener al menos 3 caracteres';
        } elseif (strlen($data['username']) > 50) {
            $errors['username'] = 'El nombre de usuario no puede exceder 50 caracteres';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $errors['username'] = 'El nombre de usuario solo puede contener letras, números y guiones bajos';
        } else {
            // Verificar si el username ya existe
            $excludeId = $isEdit ? ($data['id'] ?? null) : null;
            if ($this->usernameExists($data['username'], $excludeId)) {
                $errors['username'] = 'Este nombre de usuario ya está en uso';
            }
        }

        // Validar password (obligatorio solo al crear)
        if (!$isEdit) {
            // Crear nuevo usuario - password es obligatorio
            if (empty($data['password'])) {
                $errors['password'] = 'La contraseña es obligatoria';
            } elseif (strlen($data['password']) < 6) {
                $errors['password'] = 'La contraseña debe tener al menos 6 caracteres';
            }

            // Confirmar contraseña
            if (empty($data['password_confirm'])) {
                $errors['password_confirm'] = 'Debes confirmar la contraseña';
            } elseif ($data['password'] !== $data['password_confirm']) {
                $errors['password_confirm'] = 'Las contraseñas no coinciden';
            }
        } else {
            // Editar usuario - password es opcional
            if (!empty($data['password'])) {
                if (strlen($data['password']) < 6) {
                    $errors['password'] = 'La contraseña debe tener al menos 6 caracteres';
                }

                if (empty($data['password_confirm'])) {
                    $errors['password_confirm'] = 'Debes confirmar la nueva contraseña';
                } elseif ($data['password'] !== $data['password_confirm']) {
                    $errors['password_confirm'] = 'Las contraseñas no coinciden';
                }
            }
        }

        return $errors;
    }

    public function getMcpApiKey(int $userId): ?string
    {
        $stmt = $this->db->prepare('SELECT mcp_api_key FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['mcp_api_key'] ?? null;
    }

    public function setMcpApiKey(int $userId, ?string $key): void
    {
        $stmt = $this->db->prepare('UPDATE users SET mcp_api_key = :key WHERE id = :id');
        $stmt->execute(['key' => $key, 'id' => $userId]);
    }

    public function findByMcpApiKey(string $key): ?array
    {
        if (!preg_match('/^tmk_[0-9a-f]{64}$/', $key)) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT id, username FROM users WHERE mcp_api_key = :key');
        $stmt->execute(['key' => $key]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function generateApiKey(): string
    {
        return 'tmk_' . bin2hex(random_bytes(32));
    }

    /**
     * Verifica las credenciales de login
     */
    public function verifyLogin($username, $password) {
        $user = $this->getByUsername($username);
        
        if (!$user) {
            return false;
        }

        if (password_verify($password, $user['password_hash'])) {
            // Remover el hash antes de retornar
            unset($user['password_hash']);
            return $user;
        }

        return false;
    }
}
