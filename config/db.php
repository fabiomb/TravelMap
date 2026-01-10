<?php
/**
 * Conexión a Base de Datos
 * 
 * Este archivo gestiona la conexión a MySQL usando PDO
 * con manejo de excepciones y configuración segura.
 */

class Database {
    private static $instance = null;
    private $connection;

    // Configuración de la base de datos
    private const DB_HOST = 'localhost';
    private const DB_NAME = 'travelmap';
    private const DB_USER = 'root';
    private const DB_PASS = '';
    private const DB_CHARSET = 'utf8mb4';

    /**
     * Constructor privado para implementar Singleton
     */
    private function __construct() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::DB_HOST,
                self::DB_NAME,
                self::DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->connection = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
        } catch (PDOException $e) {
            // En producción, esto debería loggearse en lugar de mostrarse
            die('Error de conexión a la base de datos: ' . $e->getMessage());
        }
    }

    /**
     * Obtener instancia única de la conexión (Singleton Pattern)
     * 
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtener la conexión PDO
     * 
     * @return PDO
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Prevenir clonación del objeto
     */
    private function __clone() {}

    /**
     * Prevenir deserialización del objeto
     */
    public function __wakeup() {
        throw new Exception("No se puede deserializar un singleton.");
    }
}

/**
 * Función auxiliar para obtener la conexión de forma rápida
 * 
 * @return PDO
 */
function getDB() {
    return Database::getInstance()->getConnection();
}
