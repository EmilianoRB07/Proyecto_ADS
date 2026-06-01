<?php
// models/Consulta.php

class Consulta {
    private $conn;
    private $table_name = "consultas";

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    /**
     * Registra una nota de consulta médica completa vinculada al catálogo CIE-10 (RF-08, TN-14)
     */
    public function crearConsulta($id_paciente, $id_medico, $motivo, $signos, $exploracion, $cie10, $diagnostico, $observaciones) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (id_paciente, id_medico, motivo_consulta, signos_vitales, exploracion_fisica, codigo_cie10, diagnostico_descripcion, observaciones) 
                  VALUES (:id_paciente, :id_medico, :motivo, :signos, :exploracion, :cie10, :diagnostico, :observaciones)";

        $stmt = $this->conn->prepare($query);

        // Mapear y limpiar variables clínicas
        $stmt->bindParam(":id_paciente", $id_paciente);
        $stmt->bindParam(":id_medico", $id_medico);
        $stmt->bindParam(":motivo", htmlspecialchars($motivo));
        $stmt->bindParam(":signos", htmlspecialchars($signos));
        $stmt->bindParam(":exploracion", htmlspecialchars($exploracion));
        $stmt->bindParam(":cie10", strtoupper(htmlspecialchars($cie10))); // Código estandarizado
        $stmt->bindParam(":diagnostico", htmlspecialchars($diagnostico));
        $stmt->bindParam(":observaciones", htmlspecialchars($observaciones));

        if ($stmt->execute()) {
            return $this->conn->lastInsertId(); // Retorna el ID de la consulta creada para recetas posteriores
        }
        return false;
    }

    /**
     * Recupera el expediente histórico completo de un paciente uniendo médicos y diagnósticos (RF-09, TN-18)
     */
    public function obtenerHistorialPorPaciente($id_paciente) {
        $query = "SELECT c.id_consulta, c.motivo_consulta, c.signos_vitales, c.exploracion_fisica, 
                         c.codigo_cie10, c.diagnostico_descripcion, c.observaciones, c.created_at,
                         u.nombre_completo AS medico_tratante, u.especialidad
                  FROM " . $this->table_name . " c
                  INNER JOIN usuarios u ON c.id_medico = u.id_usuario
                  WHERE c.id_paciente = :id_paciente
                  ORDER BY c.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_paciente", $id_paciente);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}