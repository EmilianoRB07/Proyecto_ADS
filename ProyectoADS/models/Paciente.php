<?php
// models/Paciente.php

class Paciente {
    private $conn;
    private $table_name = "pacientes";

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    /**
     * Inserta un nuevo paciente validando indirectamente las reglas del negocio (RF-05)
     */
    public function registrarPaciente($nombre, $fecha_nacimiento, $sexo, $curp, $telefono, $email) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (nombre_completo, fecha_nacimiento, sexo, curp, telefono, email) 
                  VALUES (:nombre, :fecha, :sexo, :curp, :telefono, :email)";

        $stmt = $this->conn->prepare($query);

        // Sanitizar strings de entrada
        $nombreClean = htmlspecialchars(strip_tags(trim($nombre)));
        $curpClean = strtoupper(htmlspecialchars(strip_tags(trim($curp))));
        $emailClean = filter_var(trim($email), FILTER_SANITIZE_EMAIL);

        $stmt->bindParam(":nombre", $nombreClean);
        $stmt->bindParam(":fecha", $fecha_nacimiento);
        $stmt->bindParam(":sexo", $sexo);
        $stmt->bindParam(":curp", $curpClean);
        $stmt->bindParam(":telefono", $telefono);
        $stmt->bindParam(":email", $emailClean);

        if ($stmt->execute()) {
            // Retorna el ID generado, que equivale al Número de Expediente Clínico (TN-02)
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Abstrae la búsqueda de pacientes combinando múltiples criterios (RF-06)
     */
    public function buscarPorCriterio($criterio) {
        $criterioClean = htmlspecialchars(strip_tags(trim($criterio)));
        $paramLike = "%" . $criterioClean . "%";

        $query = "SELECT id_paciente AS expediente, nombre_completo, curp, fecha_nacimiento, sexo 
                  FROM " . $this->table_name . " 
                  WHERE nombre_completo LIKE :param 
                     OR curp LIKE :param 
                     OR id_paciente = :id_exp";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":param", $paramLike);
        
        // Convertir a entero para evaluar la coincidencia exacta por ID de expediente
        $id_exp = is_numeric($criterioClean) ? intval($criterioClean) : 0;
        $stmt->bindParam(":id_exp", $id_exp);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}