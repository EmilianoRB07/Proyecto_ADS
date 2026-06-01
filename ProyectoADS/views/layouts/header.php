<?php
// views/layouts/header.php
require_once __DIR__ . '/../../controllers/AuthController.php';

// Control de Seguridad Estricto en Servidor (Evita acceso por URL directa sin sesión)
if (!AuthController::checkSession()) {
    header("Location: /ProyectoADS/views/auth/login.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HospitalNet - Panel Médico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/ProyectoADS/public/css/styles.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="#">HospitalNet 🏥</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <div class="navbar-nav align-items-center">
                <span class="nav-item text-white me-3 small">
                    Usuario: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong> 
                    <span class="badge bg-dark ms-1"><?php echo htmlspecialchars($_SESSION['user_role']); ?></span>
                </span>
                <button class="btn btn-outline-light btn-sm fw-semibold" id="btn-logout">Cerrar Sesión</button>
            </div>
        </div>
    </div>
</nav>