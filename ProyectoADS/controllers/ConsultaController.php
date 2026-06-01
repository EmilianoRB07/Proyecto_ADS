<?php
// controllers/ConsultaController.php
require_once __DIR__ . '/AuthController.php';

class ConsultaController {
    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    /**
     * Registra una nueva consulta vinculada al médico autenticado (RF-08)
     */
    public function registrar() {
        header('Content-Type: application/json; charset=utf-8');

        if (!AuthController::checkSession()) {
            echo json_encode(["status" => "error", "code" => "ERR-03", "message" => "Sesión expirada."]);
            return;
        }

        // Control estricto de roles: Solo médicos atienden consultas (RN-02 / RN-01)
        if ($_SESSION['user_role'] !== 'Médico General' && $_SESSION['user_role'] !== 'Médico Especialista') {
            echo json_encode(["status" => "error", "code" => "ERR-02", "message" => "No tiene permisos para realizar esta acción."]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"));

        // Validar campos clínicos mandatorios (ERR-04)
        if (empty($data->id_paciente) || empty($data->motivo_consulta) || empty($data->signos_vitales) || empty($data->exploracion_fisica) || empty($data->codigo_cie10) || empty($data->diagnostico_descripcion)) {
            echo json_encode(["status" => "error", "code" => "ERR-04", "message" => "Campos obligatorios clínicos vacíos."]);
            return;
        }

        // Validación ficticia de catálogo para simular la regla ERR-10
        if (strlen($data->codigo_cie10) < 3) {
            echo json_encode(["status" => "error", "code" => "ERR-10", "message" => "El código CIE-10 ingresado no es válido. Consulte el catálogo."]);
            return;
        }

        $query = "INSERT INTO consultas (id_paciente, id_medico, motivo_consulta, signos_vitales, exploracion_fisica, codigo_cie10, diagnostico_descripcion, observaciones, activa) 
                  VALUES (:id_paciente, :id_medico, :motivo, :signos, :exploracion, :cie10, :diagnostico, :observaciones, 1)";

        $stmt = $this->db->prepare($query);

        // CORRECCIÓN: Usamos bindValue para evitar los Notices de paso por referencia
        $stmt->bindValue(":id_paciente", $data->id_paciente);
        $stmt->bindValue(":id_medico", $_SESSION['user_id']); 
        $stmt->bindValue(":motivo", htmlspecialchars($data->motivo_consulta));
        $stmt->bindValue(":signos", htmlspecialchars($data->signos_vitales));
        $stmt->bindValue(":exploracion", htmlspecialchars($data->exploracion_fisica));
        $stmt->bindValue(":cie10", strtoupper(htmlspecialchars($data->codigo_cie10)));
        $stmt->bindValue(":diagnostico", htmlspecialchars($data->diagnostico_descripcion));
        $stmt->bindValue(":observaciones", htmlspecialchars($data->observaciones ?? ''));

        try {
            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "La consulta ha sido registrada correctamente."]); // MSG-02
            } else {
                echo json_encode(["status" => "error", "message" => "No se pudo registrar la consulta médica."]);
            }
        } catch (PDOException $e) {
            // Manejo controlado del error de llave foránea por si mandan un ID inválido
            echo json_encode([
                "status" => "error", 
                "message" => "Error de integridad: El ID del paciente asignado no es válido en el sistema."
            ]);
        }
    }

    /**
     * Aplica la Regla de Negocio RN-03: Validación estricta de edición de consulta < 24 horas
     */
    public function editar($id_consulta, $nuevos_datos) {
        header('Content-Type: application/json; charset=utf-8');

        if (!AuthController::checkSession()) {
            return;
        }

        // Buscar fecha de creación de la consulta
        $queryTime = "SELECT created_at, id_medico FROM consultas WHERE id_consulta = :id LIMIT 1";
        $stmtTime = $this->db->prepare($queryTime);
        $stmtTime->bindParam(":id", $id_consulta);
        $stmtTime->execute();
        
        if ($stmtTime->rowCount() === 0) {
            echo json_encode(["status" => "error", "message" => "Consulta no encontrada."]);
            return;
        }

        $consulta = $stmtTime->fetch(PDO::FETCH_ASSOC);

        if ($consulta['id_medico'] != $_SESSION['user_id']) {
            echo json_encode(["status" => "error", "code" => "ERR-02", "message" => "No tiene permisos para modificar una consulta de otro colega."]);
            return;
        }

        $fecha_creacion = strtotime($consulta['created_at']);
        $tiempo_actual = time();
        $diferencia_horas = ($tiempo_actual - $fecha_creacion) / 3600;

        if ($diferencia_horas > 24) {
            echo json_encode([
                "status" => "error", 
                "code" => "MSG-04", 
                "message" => "Este registro ya no puede editarse. Han transcurrido más de 24 horas desde su creación." 
            ]);
            return;
        }

        $queryUpdate = "UPDATE consultas SET motivo_consulta = :motivo, diagnostico_descripcion = :diag WHERE id_consulta = :id";
        $stmtUp = $this->db->prepare($queryUpdate);
        $stmtUp->bindValue(":motivo", htmlspecialchars($nuevos_datos->motivo_consulta));
        $stmtUp->bindValue(":diag", htmlspecialchars($nuevos_datos->diagnostico_descripcion));
        $stmtUp->bindValue(":id", $id_consulta);
        
        if ($stmtUp->execute()) {
            echo json_encode(["status" => "success", "message" => "Los datos han sido actualizados correctamente."]); 
        }
    }
}
?>