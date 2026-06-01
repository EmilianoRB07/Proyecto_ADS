<?php
// models/Usuario.php

class Usuario {
    private $conn;
    private $table_name = "usuarios";

    // Propiedades del objeto
    public $id_usuario;
    public $id_rol;
    public $nombre_completo;
    public $cedula_profesional;
    public $especialidad;
    public $email;
    public $password_hash;
    public $activo;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    /**
     * Registra un nuevo médico directamente en la tabla unificada de usuarios (RF-03 / RF-04)
     */
    public function registrarMedico($nombre, $email, $id_rol, $cedula, $especialidad, $password) {
        try {
            // Consulta preparada adaptada a la estructura unificada de tu tabla
            $query = "INSERT INTO usuarios (id_rol, nombre_completo, cedula_profesional, especialidad, email, password_hash, activo) 
                      VALUES (:id_rol, :nombre, :cedula, :especialidad, :email, :password_hash, 1)";
            
            // Encriptamos la contraseña con Bcrypt por seguridad (RNF-01)
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            // Si es Médico General (id_rol == 2), guardamos la especialidad como NULL
            $esp = (intval($id_rol) === 2) ? null : $especialidad;

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id_rol', $id_rol);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':cedula', $cedula);
            $stmt->bindParam(':especialidad', $esp);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password_hash', $passwordHash);
            
            return $stmt->execute();

        } catch (PDOException $e) {
            // Captura si el correo ya existe por ser llave UNIQUE
            error_log("Error en registrarMedico: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca un usuario en la base de datos a través de su correo electrónico (Para AuthController)
     */
    public function obtenerPorEmail($email) {
        // Query limpia y optimizada uniendo roles para el control de accesos (TN-10)
        $query = "SELECT u.id_usuario, u.id_rol, u.nombre_completo, u.cedula_profesional, 
                         u.especialidad, u.email, u.password_hash, u.activo, r.nombre_rol 
                  FROM " . $this->table_name . " u
                  INNER JOIN roles r ON u.id_rol = r.id_rol 
                  WHERE u.email = :email LIMIT 1";

        $stmt = $this->conn->prepare($query);
        
        // Sanitizar el parámetro de entrada (RT-08)
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        $stmt->bindParam(":email", $email);
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Registra un nuevo usuario/médico en el sistema (Acción alternativa del Administrador RF-03)
     */
    public function crear($datos) {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET id_rol = :id_rol, nombre_completo = :nombre, cedula_profesional = :cedula, 
                      especialidad = :especialidad, email = :email, password_hash = :password, activo = 1";

        $stmt = $this->conn->prepare($query);

        // Hashear la contraseña usando BCRYPT de manera nativa (RNF-01)
        $password_segura = password_hash($datos->password, PASSWORD_BCRYPT, ['cost' => 10]);

        // Sanitización estricta de strings contra ataques XSS persistentes (RT-08)
        $nombre = htmlspecialchars(strip_tags($datos->nombre_completo));
        $cedula = !empty($datos->cedula_profesional) ? htmlspecialchars(strip_tags($datos->cedula_profesional)) : null;
        $especialidad = !empty($datos->especialidad) ? htmlspecialchars(strip_tags($datos->especialidad)) : null;
        $email = filter_var(trim($datos->email), FILTER_SANITIZE_EMAIL);

        // Vinculación segura de parámetros
        $stmt->bindParam(":id_rol", $datos->id_rol);
        $stmt->bindParam(":nombre", $nombre);
        $stmt->bindParam(":cedula", $cedula);
        $stmt->bindParam(":especialidad", $especialidad);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":password", $password_segura);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Obtiene todos los médicos activos del sistema para los listados de derivación
     */
    public function listarMedicos() {
        // Filtramos para traer solo Médicos (id_rol 2 y 3) que estén activos
        $query = "SELECT u.id_usuario, u.nombre_completo, u.especialidad, r.nombre_rol 
                  FROM usuarios u
                  INNER JOIN roles r ON u.id_rol = r.id_rol
                  WHERE u.id_rol IN (2, 3) AND u.activo = 1
                  ORDER BY r.id_rol DESC, u.nombre_completo ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las citas con estado 'Programada' asignadas al médico en sesión (NUEVO)
     */
    public function obtenerCitasMedicas($id_medico) {
        // ALINEADO AL ENUM: Buscamos el estado 'Programada' de tu base de datos
        $query = "SELECT c.id_cita, c.fecha_hora, p.id_paciente, p.nombre_completo, p.curp 
                  FROM citas c
                  INNER JOIN pacientes p ON c.id_paciente = p.id_paciente
                  WHERE c.id_medico = :id_medico AND c.estado = 'Programada'
                  ORDER BY c.fecha_hora ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_medico", $id_medico);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>