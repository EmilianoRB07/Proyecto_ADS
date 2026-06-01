<?php
// controllers/CitaController.php
require_once __DIR__ . '/AuthController.php';

class CitaController {
    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    /**
     * Devuelve un JSON con los médicos registrados para armar el select dinámico
     */
    public function listarDoctores() {
        header('Content-Type: application/json; charset=utf-8');
        
        if (!AuthController::checkSession()) {
            echo json_encode(["status" => "error", "message" => "Sesión expirada."]);
            return;
        }

        require_once __DIR__ . '/../models/Usuario.php';
        $usuarioModel = new Usuario($this->db);
        $medicos = $usuarioModel->listarMedicos();

        echo json_encode(["status" => "success", "data" => $medicos]);
    }

    /**
     * Procesa la inserción asíncrona de una nueva cita de seguimiento o derivación
     */
    public function agendar() {
        header('Content-Type: application/json; charset=utf-8');

        if (!AuthController::checkSession()) {
            echo json_encode(["status" => "error", "message" => "Sesión expirada."]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->id_paciente) || empty($data->id_medico) || empty($data->fecha_hora)) {
            echo json_encode(["status" => "error", "message" => "Faltan parámetros obligatorios para agendar la cita."]);
            return;
        }

        try {
            // CORRECCIÓN ENUM: Forzamos el estado 'Programada' para que MySQL no lo rechace
            $query = "INSERT INTO citas (id_paciente, id_medico, fecha_hora, estado) 
                      VALUES (:id_paciente, :id_medico, :fecha_hora, 'Programada')";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":id_paciente", $data->id_paciente);
            $stmt->bindValue(":id_medico", $data->id_medico); 
            $stmt->bindValue(":fecha_hora", $data->fecha_hora); 

            if ($stmt->execute()) {
                echo json_encode([
                    "status" => "success", 
                    "message" => "¡Cita de derivación/seguimiento agendada con éxito!"
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "No se pudo registrar la cita en la base de datos."]);
            }
        } catch (PDOException $e) {
            error_log("Error en agendar cita: " . $e->getMessage());
            echo json_encode(["status" => "error", "message" => "Error de consistencia en el servidor al agendar."]);
        }
    }

    /**
     * Devuelve las citas en estado 'Programada' asignadas al médico logueado (NUEVO)
     */
    public function obtenerMisCitas() {
        header('Content-Type: application/json; charset=utf-8');

        if (!AuthController::checkSession()) {
            echo json_encode(["status" => "error", "message" => "Sesión expirada."]);
            return;
        }

        // Extraemos el ID real (el 5 para Chrystian) guardado de forma centralizada en la sesión
        $id_medico = $_SESSION['user_id'];

        require_once __DIR__ . '/../models/Usuario.php';
        $usuarioModel = new Usuario($this->db);
        $citas = $usuarioModel->obtenerCitasMedicas($id_medico);

        echo json_encode(["status" => "success", "data" => $citas]);
    }
}
?>