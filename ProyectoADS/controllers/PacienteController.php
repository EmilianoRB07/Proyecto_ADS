<?php
// controllers/PacienteController.php
require_once __DIR__ . '/AuthController.php';

class PacienteController {
    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    /**
     * Registra un paciente aplicando reglas de negocio de CURP (RF-05, RN-02)
     */
    public function registrar() {
        header('Content-Type: application/json; charset=utf-8');

        // 1. Validar sesión e inactividad (RNF-03)
        if (!AuthController::checkSession()) {
            echo json_encode(["status" => "error", "code" => "ERR-03", "message" => "Su sesión ha expirado. Por favor inicie sesión nuevamente."]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"));

        // 2. Validar campos obligatorios (ERR-04)
        if (empty($data->nombre_completo) || empty($data->fecha_nacimiento) || empty($data->sexo) || empty($data->curp) || empty($data->telefono) || empty($data->email)) {
            echo json_encode(["status" => "error", "code" => "ERR-04", "message" => "Todos los campos de registro son obligatorios."]);
            return;
        }

        // 3. Validar sintaxis/longitud de CURP mexicana (ERR-06)
        $curp = strtoupper(trim($data->curp));
        if (strlen($curp) !== 18) {
            echo json_encode(["status" => "error", "code" => "ERR-06", "message" => "El formato de la CURP no es válido. Verifique e intente de nuevo."]);
            return;
        }

        // 4. Regla de Negocio RN-02: Comprobar duplicidad de CURP
        $checkQuery = "SELECT id_paciente FROM pacientes WHERE curp = :curp LIMIT 1";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindParam(":curp", $curp);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            echo json_encode(["status" => "error", "code" => "ERR-05", "message" => "Ya existe un paciente registrado con esa CURP. Verifique los datos."]); // ERR-05
            return;
        }

        // 5. Proceder con la inserción limpia (RT-08)
        $query = "INSERT INTO pacientes (nombre_completo, fecha_nacimiento, sexo, curp, telefono, email) 
                  VALUES (:nombre, :fecha_nacimiento, :sexo, :curp, :telefono, :email)";
        
        $stmt = $this->db->prepare($query);
        
        // Sanitizaciones finales (RT-08)
        $nombre = htmlspecialchars(strip_tags($data->nombre_completo));
        $fecha_nacimiento = htmlspecialchars(strip_tags($data->fecha_nacimiento));
        $sexo = htmlspecialchars(strip_tags($data->sexo));
        $telefono = htmlspecialchars(strip_tags($data->telefono));
        $email = filter_var(trim($data->email), FILTER_SANITIZE_EMAIL);

        $stmt->bindParam(":nombre", $nombre);
        $stmt->bindParam(":fecha_nacimiento", $fecha_nacimiento);
        $stmt->bindParam(":sexo", $sexo);
        $stmt->bindParam(":curp", $curp);
        $stmt->bindParam(":telefono", $telefono);
        $stmt->bindParam(":email", $email);

        if ($stmt->execute()) {
            $nuevo_expediente = $this->db->lastInsertId();
            echo json_encode([
                "status" => "success",
                "message" => "El paciente ha sido registrado exitosamente con número de expediente " . $nuevo_expediente . "." // MSG-03
            ]);
        }
    }

    /**
     * Busca pacientes por coincidencia en Nombre, CURP o Expediente (RF-06)
     */
    public function buscar($criterio) {
        header('Content-Type: application/json; charset=utf-8');

        if (!AuthController::checkSession()) {
            echo json_encode(["status" => "error", "code" => "ERR-03", "message" => "Su sesión ha expirado."]);
            return;
        }

        $criterioClean = htmlspecialchars(strip_tags(trim($criterio)));
        $paramLike = "%" . $criterioClean . "%";

        // Query dinámico parametrizado trayendo el id_paciente
        $query = "SELECT id_paciente, nombre_completo, curp, fecha_nacimiento 
                  FROM pacientes 
                  WHERE nombre_completo LIKE :param 
                     OR curp LIKE :param 
                     OR id_paciente = :id_exp 
                  LIMIT 10";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":param", $paramLike);
        
        $id_exp = is_numeric($criterioClean) ? intval($criterioClean) : 0;
        $stmt->bindParam(":id_exp", $id_exp);
        
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mapeo adaptativo para blindar las propiedades que leerá el main.js
        $txtData = [];
        foreach ($resultados as $row) {
            $txtData[] = [
                "id_paciente" => $row['id_paciente'],
                "expediente" => $row['id_paciente'], // Se duplica hacia 'expediente' para complacer al JS
                "nombre_completo" => $row['nombre_completo'],
                "curp" => $row['curp']
            ];
        }

        echo json_encode(["status" => "success", "data" => $txtData]);
    }
} // Cierre correcto de la clase PacienteController sin comas huérfanas
?>