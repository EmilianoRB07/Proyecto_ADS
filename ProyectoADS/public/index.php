<?php
// public/index.php

// Carga de archivos base del backend
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/PacienteController.php';
require_once __DIR__ . '/../controllers/ConsultaController.php';
require_once __DIR__ . '/../controllers/CitaController.php';


// Iniciar base de datos de manera centralizada
$database = new Database();
$dbConnection = $database->getConnection();

// Analizar la ruta limpia recibida por parámetro GET
$url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : '';

if (empty($url)) {
    // Si entran a la raíz de public sin ruta, redirige al Login
    header("Location: /ProyectoADS/views/auth/login.html");
    exit;
}

// Descomponer segmentos (controlador/acción)
$urlSegments = explode('/', $url);
$routeController = $urlSegments[0]; 
$routeAction = isset($urlSegments[1]) ? $urlSegments[1] : '';

// Enrutador estructural de la arquitectura MVC (RT-02)
switch ($routeController) {
    case 'auth':
        $controller = new AuthController($dbConnection);
        if ($routeAction === 'login') {
            $controller->login();
        } elseif ($routeAction === 'logout') {
            $controller->logout();
        } elseif ($routeAction === 'registrarMedico') {
            $controller->registrarMedico();
        } elseif ($routeAction === 'verificarCorreo') { 
            $controller->verificarCorreo();
        } elseif ($routeAction === 'actualizarPassword') { 
            $controller->actualizarPassword();
        } else {
            retornar404();
        }
        break;
        
    case 'paciente':
        $controller = new PacienteController($dbConnection);
        if ($routeAction === 'registrar') {
            $controller->registrar();
        } elseif ($routeAction === 'buscar') {
            $criterio = isset($_GET['criterio']) ? $_GET['criterio'] : '';
            $controller->buscar($criterio);
        } else {
            retornar404();
        }
        break;

    case 'consulta':
        $controller = new ConsultaController($dbConnection);
        if ($routeAction === 'registrar') {
            $controller->registrar();
        } else {
            retornar404();
        }
        break;

    case 'cita':
        $controller = new CitaController($dbConnection);
        if ($routeAction === 'listarDoctores') {
            $controller->listarDoctores();
        } elseif ($routeAction === 'agendar') {
            $controller->agendar();
        } elseif ($routeAction === 'obtenerMisCitas') { // <-- CORRECCIÓN: Captura el puente de la API
            $controller->obtenerMisCitas();
        } else {
            retornar404();
        }
        break;

    default:
        retornar404();
        break;
}

// Declaración de función limpia sin comas huérfanas
function retornar404() {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Endpoint API no encontrado dentro de la arquitectura de HospitalNet."
    ]);
    exit;
}
?>