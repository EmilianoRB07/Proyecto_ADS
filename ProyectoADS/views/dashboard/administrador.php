<?php
// views/dashboard/administrador.php

// 1. Incluir el encabezado común y verificar la sesión segura
include_once __DIR__ . '/../layouts/header.php';

// 2. Validar que el rol corresponda ESTRICTAMENTE al Administrador (RN-01)
if ($_SESSION['user_role'] !== 'Administrador') {
    echo "<div class='container mt-5 alert alert-danger'>Acceso Denegado. Este módulo es exclusivo del Administrador del Sistema.</div>";
    include_once __DIR__ . '/../layouts/footer.php';
    exit;
}
?>

<div class="container px-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="p-4 bg-white border rounded shadow-sm d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">Panel de Control Administrative</h4>
                    <p class="text-muted small mb-0">Gestión global de usuarios, roles y catálogos institucionales.</p>
                </div>
                <span class="badge bg-danger fs-6 py-2 px-3">Modo Root</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card card-clinical">
                <div class="card-header">🔑 Registrar Nuevo Personal Médico</div>
                <div class="card-body">
                    <div id="admin-alert-container"></div>
                    
                    <form id="form-registro-medico" novalidate>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nombre Completo</label>
                            <input type="text" id="med_nombre" class="form-control form-control-sm" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Correo Electrónico Institucional</label>
                            <input type="email" id="med_email" class="form-control form-control-sm" placeholder="ejemplo@hospitalnet.com" required>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Asignar Rol (RF-04)</label>
                                <select id="med_rol" class="form-select form-select-sm" required>
                                    <option value="2">Médico General</option>
                                    <option value="3">Médico Especialista</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Cédula Profesional</label>
                                <input type="text" id="med_cedula" class="form-control form-control-sm" pattern="[0-9]{7,15}" maxlength="20" placeholder="Ej. 12345678" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Especialidad Clínica</label>
                            <input type="text" id="med_especialidad" class="form-control form-control-sm" placeholder="Dejar vacío si es Médico General">
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold">Contraseña Temporal de Acceso</label>
                            <input type="password" class="form-control form-control-sm" id="med_password" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-semibold btn-sm">
                            Guardar y Activar Cuenta
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card card-clinical">
                <div class="card-header">📊 Estado Operativo de HospitalNet</div>
                <div class="card-body">
                    <p class="small text-muted">Como usuario administrador, tienes control de auditoría sobre los registros clínicos del hospital.</p>
                    
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="p-3 border rounded bg-light text-center">
                                <h3 class="fw-bold text-primary mb-0">Activo</h3>
                                <small class="text-muted extra-small">Módulo de Citas (RF-14)</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 border rounded bg-light text-center">
                                <h3 class="fw-bold text-success mb-0">Cumplido</h3>
                                <small class="text-muted extra-small">Norma de Privacidad LFPDPPP (RNF-10)</small>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4 small mb-0">
                        <strong>Nota de Diseño de Sistemas:</strong> Los formularios de esta vista procesan las altas enviando peticiones asíncronas estructuradas al controlador mediante formato JSON para asegurar la consistencia transaccional.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const formRegistroMedico = document.getElementById("form-registro-medico");
    const adminAlertContainer = document.getElementById("admin-alert-container");

    if (formRegistroMedico) {
        formRegistroMedico.addEventListener("submit", async (e) => {
            e.preventDefault();

            // Validación nativa de Bootstrap/HTML5 antes del envío
            if (!formRegistroMedico.checkValidity()) {
                formRegistroMedico.classList.add('was-validated');
                return;
            }

            const payload = {
                nombre_completo: document.getElementById("med_nombre").value.trim(),
                email: document.getElementById("med_email").value.trim(),
                id_rol: document.getElementById("med_rol").value,
                cedula: document.getElementById("med_cedula").value.trim(),
                especialidad: document.getElementById("med_especialidad").value.trim(),
                password: document.getElementById("med_password").value
            };

            try {
                // Petición asíncrona apuntando de manera exacta a tu carpeta ProyectoADS
                const response = await fetch("/ProyectoADS/public/index.php?url=auth/registrarMedico", {
                    method: "POST",
                    headers: { "Content-Type": "application/json; charset=utf-8" },
                    body: JSON.stringify(payload)
                });

                const res = await response.json();

                if (res.status === "success") {
                    adminAlertContainer.innerHTML = `<div class="alert alert-success small py-2">${res.message}</div>`;
                    formRegistroMedico.reset();
                    formRegistroMedico.classList.remove('was-validated');
                } else {
                    adminAlertContainer.innerHTML = `<div class="alert alert-danger small py-2">${res.message}</div>`;
                }
            } catch (err) {
                console.error("Error al registrar médico:", err);
                adminAlertContainer.innerHTML = `<div class="alert alert-danger small py-2">Error de comunicación con el controlador.</div>`;
            }
        });
    }
});
</script>

<?php
// 3. Incluir el pie de página común con los scripts cargados de forma ordenada
include_once __DIR__ . '/../layouts/footer.php';
?>