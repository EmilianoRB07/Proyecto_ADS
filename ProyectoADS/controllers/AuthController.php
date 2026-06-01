<?php
// controllers/AuthController.php

class AuthController {
    private $db;
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    /**
     * Maneja el inicio de sesión (RF-01)
     */
    public function login() {
        header('Content-Type: application/json; charset=utf-8');
        
        // Obtener datos crudos de la petición asíncrona Fetch (RT-06)
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->email) || empty($data->password)) {
            echo json_encode(["status" => "error", "code" => "ERR-04", "message" => "El campo 'Email/Contraseña' es obligatorio."]); // ERR-04
            return;
        }

        // Sanitización básica (RT-08)
        $email = filter_var(trim($data->email), FILTER_SANITIZE_EMAIL);
        $password = trim($data->password);

        // Consulta preparada para evitar inyección SQL (RT-08)
        $query = "SELECT u.id_usuario, u.nombre_completo, u.password_hash, u.activo, r.nombre_rol 
                  FROM usuarios u 
                  INNER JOIN roles r ON u.id_rol = r.id_rol 
                  WHERE u.email = :email LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificar si la cuenta está activa en el sistema (RN-05)
            if ($row['activo'] == 0) {
                echo json_encode(["status" => "error", "code" => "ERR-02", "message" => "No tiene permisos para realizar esta acción. Cuenta inactiva."]);
                return;
            }

            // CORRECCIÓN DEFINITIVA: Verificación matemática segura con Bcrypt (RNF-01)
            if (password_verify($password, $row['password_hash'])) {
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                
                session_regenerate_id(true);

                // Inyectar variables seguras a la sesión
                $_SESSION['user_id'] = $row['id_usuario'];
                $_SESSION['user_name'] = $row['nombre_completo'];
                $_SESSION['user_role'] = $row['nombre_rol'];
                $_SESSION['last_activity'] = time();

                // DIRECCIÓN INTELIGENTE SEGÚN EL ROL (RN-01)
                $redirectUrl = "views/dashboard/medico.php"; // Ruta por defecto para médicos
                
                if ($row['nombre_rol'] === 'Administrador') {
                    $redirectUrl = "views/dashboard/administrador.php"; // Ruta exclusiva de admin
                }

                echo json_encode([
                    "status" => "success",
                    "message" => "Inicio de sesión exitoso. Bienvenido, " . $row['nombre_completo'] . ".", // MSG-01
                    "redirect" => "/ProyectoADS/" . $redirectUrl
                ]);
            } else {
                echo json_encode(["status" => "error", "code" => "ERR-01", "message" => "Usuario o contraseña incorrectos. Intente de nuevo."]);
            }
        } else {
            echo json_encode(["status" => "error", "code" => "ERR-01", "message" => "Usuario o contraseña incorrectos. Intente de nuevo."]);
        }
    }

    /**
     * Cierra la sesión activa de forma manual (RF-02)
     */
    public function logout() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Destruir todas las variables de sesión
        $_SESSION = array();

        // Destruir la cookie de sesión si existe
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            "status" => "success",
            "message" => "La sesión ha sido cerrada correctamente. Hasta pronto." // MSG-10
        ]);
    }

    /**
     * Middleware de validación de sesión activa y control de inactividad (RNF-03)
     */
    public static function checkSession() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Validar límite de inactividad de 15 minutos = 900 segundos (RNF-03 / ERR-03)
        if (time() - $_SESSION['last_activity'] > 900) {
            self::destruirSesionInactiva();
            return false;
        }

        $_SESSION['last_activity'] = time(); // Renovar la marca de tiempo si está activo
        return true;
    }

    private static function destruirSesionInactiva() {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
    }

    /**
     * Procesa el registro de un nuevo médico enviado por el Administrador (RF-03)
     */
    public function registrarMedico() {
        header('Content-Type: application/json; charset=utf-8');
        
        // Verificar que quien hace la petición sea un Administrador real en sesión (RN-01)
        if (session_status() == PHP_SESSION_NONE) { session_start(); }
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Administrador') {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Acceso denegado. Privilegios insuficientes."]);
            return;
        }

        // Obtener los datos crudos del fetch
        $data = json_decode(file_get_contents("php://input"));

        // Validar campos obligatorios
        if (empty($data->nombre_completo) || empty($data->email) || empty($data->id_rol) || empty($data->cedula) || empty($data->password)) {
            echo json_encode(["status" => "error", "message" => "Todos los campos obligatorios deben ser llenados."]);
            return;
        }

        // Requerir el modelo de Usuario e instanciarlo
        require_once __DIR__ . '/../models/Usuario.php';
        $usuarioModel = new Usuario($this->db);

        // Intentar registrar en la base de datos
        $exito = $usuarioModel->registrarMedico(
            trim($data->nombre_completo),
            filter_var(trim($data->email), FILTER_SANITIZE_EMAIL),
            $data->id_rol,
            trim($data->cedula),
            trim($data->especialidad),
            $data->password
        );

        if ($exito) {
            echo json_encode([
                "status" => "success",
                "message" => "¡Médico registrado con éxito! Su cuenta ya se encuentra activa."
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Error al registrar. Es posible que el correo o la cédula ya estén dados de alta."
            ]);
        }
    }

    /**
     * Paso 1 de Recuperación: Verifica si el correo existe en la base de datos
     */
    public function verificarCorreo() {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->email)) {
            echo json_encode(["status" => "error", "message" => "El correo electrónico es requerido."]);
            return;
        }

        require_once __DIR__ . '/../models/Usuario.php';
        $usuarioModel = new Usuario($this->db);
        $usuario = $usuarioModel->obtenerPorEmail($data->email);

        if ($usuario) {
            echo json_encode([
                "status" => "success",
                "message" => "Cuenta localizada con éxito. Proceda al cambio."
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "El correo ingresado no pertenece a ningún usuario activo."]);
        }
    }

    /**
     * Paso 2 de Recuperación: Actualiza la contraseña con encriptación Bcrypt
     */
    public function actualizarPassword() {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->email) || empty($data->password)) {
            echo json_encode(["status" => "error", "message" => "Datos incompletos para procesar la solicitud."]);
            return;
        }

        // Generar el nuevo hash seguro con Bcrypt (RNF-01)
        $newHash = password_hash($data->password, PASSWORD_BCRYPT);

        try {
            // Hacemos el Update directo a la tabla unificada usuarios
            $query = "UPDATE usuarios SET password_hash = :password WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':password', $newHash);
            $stmt->bindParam(':email', $data->email);
            
            if ($stmt->execute()) {
                echo json_encode([
                    "status" => "success",
                    "message" => "¡Contraseña actualizada correctamente! Redirigiendo al login..."
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "No se pudo actualizar la contraseña en el servidor."]);
            }
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Error interno: " . $e->getMessage()]);
        }
    }
}
?>