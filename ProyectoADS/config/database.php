<?php
// config/database.php

class Database {
    private $host = "localhost";
    private $db_name = "hospitalnet_db";
    private $username = "root";
    private $password = ""; // Déjalo vacío si usas XAMPP por defecto
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            // Conexión segura mediante PDO en UTF-8 (RT-03)
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $exception) {
            // Manejo de error de conexión interna (ERR-12)
            error_log("Error de conexión: " . $exception->getMessage());
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                "status" => "error", 
                "code" => "ERR-12", 
                "message" => "Ocurrió un error interno. Por favor intente más tarde o contacte al administrador."
            ]);
            exit;
        }
        return $this->conn;
    }
}