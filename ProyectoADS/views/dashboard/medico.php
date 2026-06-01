<?php
// views/dashboard/medico.php

// 1. Incluir el encabezado común y verificar la sesión segura
include_once __DIR__ . '/../layouts/header.php';

// 2. Validar que el rol corresponda a personal médico habilitado (RF-01, RN-01)
if ($_SESSION['user_role'] !== 'Médico General' && $_SESSION['user_role'] !== 'Médico Especialista') {
    echo "<div class='container mt-5 alert alert-danger'>Acceso Denegado. Su rol no tiene privilegios para este módulo.</div>";
    include_once __DIR__ . '/../layouts/footer.php';
    exit;
}
?>

<div class="container-fluid px-4">
    <div class="row">
        
        <div class="col-md-4">
            <div class="card card-clinical mb-3">
                <div class="card-header">Búsqueda de Pacientes (Expediente Clínico)</div>
                <div class="card-body">
                    <div class="input-group mb-3">
                        <input type="text" id="search-input" class="form-control" placeholder="Nombre, CURP o No. Expediente...">
                        <button class="btn btn-primary" id="btn-search-patient" type="button">Buscar</button>
                    </div>
                    
                    <div class="list-group" id="search-results-container">
                        <p class="text-muted small text-center my-3">Realice una búsqueda para desplegar registros.</p>
                    </div>
                </div>
            </div>

            <div class="d-grid mb-4">
                <button class="btn btn-success fw-semibold py-2" data-bs-toggle="modal" data-bs-target="#modalRegistroPaciente">
                    + Registrar Nuevo Paciente
                </button>
            </div>

            <div class="card card-clinical">
                <div class="card-header bg-info text-dark fw-bold d-flex justify-content-between align-items-center">
                    <span>📥 Pacientes Trasladados</span>
                    <span class="badge bg-dark text-white" id="badge-contador-citas">0</span>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="notificaciones-citas-container">
                        <div class="p-3 text-center text-muted small">Cargando transferencias pendientes...</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card card-clinical d-none" id="panel-consulta-activa">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Atención Médica Activa</span>
                    <span class="badge bg-primary fs-6" id="patient-active-name">Paciente</span>
                </div>
                <div class="card-body">
                    <div id="consulta-alert-container"></div>
                    
                    <form id="form-consulta-clinica">
                        <input type="hidden" id="active-patient-id" value="">

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Signos Vitales (Presión, Temp, FC, FR)</label>
                                <input type="text" id="signos_vitales" class="form-control form-control-sm" placeholder="120/80 mmHg, 36.5°C" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Código Diagnóstico CIE-10 (TN-14)</label>
                                <input type="text" id="codigo_cie10" class="form-control form-control-sm" placeholder="E11.9, K21.9" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Motivo de la Consulta</label>
                            <textarea id="motivo_consulta" class="form-control" rows="2" placeholder="Describa los síntomas presentados..." required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Exploración Física</label>
                            <textarea id="exploracion_fisica" class="form-control" rows="2" placeholder="Hallazgos clínicos del examen físico..." required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Descripción del Diagnóstico Final</label>
                            <textarea id="diagnostico_descripcion" class="form-control" rows="2" placeholder="Diagnóstico detallado del médico..." required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Observaciones / Indicaciones Adicionales</label>
                            <textarea id="observaciones" class="form-control" rows="2" placeholder="Notas secundarias..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary fw-semibold" id="btn-save-consulta">
                            Guardar Consulta en Expediente
                        </button>
                    </form>
                </div>
            </div>
            
            <div id="panel-placeholder" class="alert alert-secondary text-center p-5">
                <h5>No hay ningún expediente en edición</h5>
                <p class="text-muted small">Seleccione un paciente de la lista izquierda o realice una nueva búsqueda para abrir el módulo clínico.</p>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="modalRegistroPaciente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Registrar Paciente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modal-alert-container"></div>
                <form id="form-registro-paciente">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nombre Completo</label>
                        <input type="text" id="reg_nombre" class="form-control" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Fecha de Nacimiento</label>
                            <input type="date" id="reg_fecha" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Sexo</label>
                            <select id="reg_sexo" class="form-select" required>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">CURP (18 caracteres)</label>
                        <input type="text" id="reg_curp" class="form-control" maxlength="18" placeholder="Formatos oficiales de México" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Teléfono de Contacto</label>
                        <input type="tel" id="reg_telefono" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Correo Electrónico</label>
                        <input type="email" id="reg_email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100 fw-semibold">Dar de Alta Paciente</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAgendarCita" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">📅 Programar Seguimiento / Derivación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="cita-alert-container"></div>
                
                <form id="form-agendar-cita">
                    <input type="hidden" id="cita-patient-id" value="">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Paciente Seleccionado</label>
                        <input type="text" id="cita-patient-name" class="form-control bg-light" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Asignar Médico Tratante (Derivación)</label>
                        <select id="cita-medico-destino" class="form-select" required></select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Fecha y Hora de la Cita</label>
                        <input type="datetime-local" id="cita-fecha-hora" class="form-control" required>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-secondary w-50" data-bs-close="modal" data-bs-dismiss="modal">Dar de Alta Solo Consulta</button>
                        <button type="submit" class="btn btn-primary w-50 fw-semibold">Confirmar y Agendar Cita</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// 3. Incluir el pie de página común con los scripts cargados de forma ordenada
include_once __DIR__ . '/../layouts/footer.php';
?>