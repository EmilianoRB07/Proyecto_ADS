// public/js/main.js

document.addEventListener("DOMContentLoaded", () => {
    // Componentes de búsqueda y listado
    const btnSearch = document.getElementById("btn-search-patient");
    const searchInput = document.getElementById("search-input");
    const resultsContainer = document.getElementById("search-results-container");

    // Componentes de paneles clínicos
    const panelPlaceholder = document.getElementById("panel-placeholder");
    const panelConsultaActiva = document.getElementById("panel-consulta-activa");
    const patientActiveName = document.getElementById("patient-active-name");
    const activePatientId = document.getElementById("active-patient-id");

    // Formularios y botones
    const formConsulta = document.getElementById("form-consulta-clinica");
    const formRegistroPaciente = document.getElementById("form-registro-paciente");
    const btnLogout = document.getElementById("btn-logout");

    // Contenedores de notificaciones de citas trasladadas
    const citasContainer = document.getElementById("notificaciones-citas-container");
    const badgeContador = document.getElementById("badge-contador-citas");

    // 1. BÚSQUEDA ASÍNCRONA DE PACIENTES (RF-06)
    if (btnSearch) {
        btnSearch.addEventListener("click", async () => {
            const criterio = searchInput.value.trim();
            if (!criterio) return;

            try {
                const response = await fetch(`/ProyectoADS/public/index.php?url=paciente/buscar&criterio=${encodeURIComponent(criterio)}`);
                const res = await response.json();

                if (res.status === "success" && res.data.length > 0) {
                    resultsContainer.innerHTML = "";
                    res.data.forEach(paciente => {
                        const item = document.createElement("a");
                        item.href = "#";
                        item.className = "list-group-item list-group-item-action small";
                        item.innerHTML = `
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 fw-bold">${paciente.nombre_completo}</h6>
                                <small class="text-primary">Exp: #${paciente.expediente}</small>
                            </div>
                            <p class="mb-0 text-muted extra-small">CURP: ${paciente.curp}</p>
                        `;
                        item.addEventListener("click", (e) => {
                            e.preventDefault();
                            panelPlaceholder.classList.add("d-none");
                            panelConsultaActiva.classList.remove("d-none");
                            patientActiveName.textContent = paciente.nombre_completo;
                            activePatientId.value = paciente.id_paciente;
                        });
                        resultsContainer.appendChild(item);
                    });
                } else {
                    resultsContainer.innerHTML = `<p class="text-danger small text-center my-3">No se encontraron registros.</p>`;
                }
            } catch (err) {
                console.error("Error:", err);
            }
        });
    }

    // 2. REGISTRO DE CONSULTA MÉDICA CON FLUJO DE DERIVACIÓN INTEGRADO (RF-08)
    if (formConsulta) {
        formConsulta.addEventListener("submit", async (e) => {
            e.preventDefault();

            const currentPatientId = activePatientId.value;
            const currentPatientName = patientActiveName.textContent;

            if (!currentPatientId || currentPatientId === "undefined") {
                alert("Error: El ID del paciente no se ha cargado correctamente.");
                return;
            }

            const payload = {
                id_paciente: currentPatientId,
                signos_vitales: document.getElementById("signos_vitales").value.trim(),
                codigo_cie10: document.getElementById("codigo_cie10").value.trim(),
                motivo_consulta: document.getElementById("motivo_consulta").value.trim(),
                exploracion_fisica: document.getElementById("exploracion_fisica").value.trim(),
                diagnostico_descripcion: document.getElementById("diagnostico_descripcion").value.trim(),
                observaciones: document.getElementById("observaciones").value.trim()
            };

            try {
                const response = await fetch("/ProyectoADS/public/index.php?url=consulta/registrar", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(payload)
                });
                const res = await response.json();

                const alertBox = document.getElementById("consulta-alert-container");
                if (res.status === "success") {
                    alertBox.innerHTML = `<div class="alert alert-success small py-2">${res.message} Transfiriendo al módulo de citas...</div>`;
                    
                    setTimeout(async () => {
                        formConsulta.reset();
                        panelConsultaActiva.classList.add("d-none");
                        panelPlaceholder.classList.remove("d-none");
                        alertBox.innerHTML = "";

                        document.getElementById("cita-patient-id").value = currentPatientId;
                        document.getElementById("cita-patient-name").value = currentPatientName;

                        try {
                            const resMed = await fetch("/ProyectoADS/public/index.php?url=cita/listarDoctores");
                            const jsonMed = await resMed.json();
                            
                            if (jsonMed.status === "success") {
                                const selectMed = document.getElementById("cita-medico-destino");
                                selectMed.innerHTML = "";
                                
                                jsonMed.data.forEach(medico => {
                                    const opt = document.createElement("option");
                                    opt.value = medico.id_usuario;
                                    opt.textContent = `${medico.nombre_completo} (${medico.nombre_rol}${medico.especialidad ? ' - ' + medico.especialidad : ''})`;
                                    selectMed.appendChild(opt);
                                });

                                const modalCitaElement = document.getElementById("modalAgendarCita");
                                if (modalCitaElement) {
                                    try {
                                        const modalCita = new bootstrap.Modal(modalCitaElement);
                                        modalCita.show();
                                    } catch(e) {
                                        console.warn("Disparando modal por método alternativo seguro.");
                                        $(modalCitaElement).modal('show'); 
                                    }
                                }
                            }
                        } catch (errMed) {
                            console.error("Error al cargar médicos para derivación:", errMed);
                        }
                    }, 1500);

                } else {
                    alertBox.innerHTML = `<div class="alert alert-danger small py-2">${res.message}</div>`;
                }
            } catch (err) {
                console.error("Error:", err);
            }
        });
    }

    // --- 3. ESCUCHA DEL FORMULARIO DEL MODAL DE CITAS DE SEGUIMIENTO ---
    const formAgendarCita = document.getElementById("form-agendar-cita");
    if (formAgendarCita) {
        formAgendarCita.addEventListener("submit", async (e) => {
            e.preventDefault();

            const rawDateTime = document.getElementById("cita-fecha-hora").value;
            if (!rawDateTime) {
                alert("Por favor seleccione una fecha y hora válidas.");
                return;
            }
            
            const mysqlDateTime = rawDateTime.replace("T", " ") + ":00";

            const payloadCita = {
                id_paciente: document.getElementById("cita-patient-id").value,
                id_medico: document.getElementById("cita-medico-destino").value,
                fecha_hora: mysqlDateTime
            };

            try {
                const response = await fetch("/ProyectoADS/public/index.php?url=cita/agendar", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(payloadCita)
                });
                const res = await response.json();

                const alertCitaBox = document.getElementById("cita-alert-container");
                if (res.status === "success") {
                    alertCitaBox.innerHTML = `<div class="alert alert-success small py-2">${res.message}</div>`;
                    formAgendarCita.reset();
                    setTimeout(() => {
                        alertCitaBox.innerHTML = "";
                        const modalEl = document.getElementById("modalAgendarCita");
                        if (modalEl) {
                            try {
                                const modal = bootstrap.Modal.getInstance(modalEl);
                                modal.hide();
                            } catch(e) {
                                $(modalEl).modal('hide');
                            }
                        }
                        // Recargar la lista de forma reactiva tras agendar una cita exitosamente
                        cargarPacientesTrasladados();
                    }, 2000);
                } else {
                    alertCitaBox.innerHTML = `<div class="alert alert-danger small py-2">${res.message}</div>`;
                }
            } catch (err) {
                console.error("Error al guardar la cita:", err);
            }
        });
    }

    // --- 4. REGISTRO DE PACIENTE NUEVO (RF-05) ---
    if (formRegistroPaciente) {
        formRegistroPaciente.addEventListener("submit", async (e) => {
            e.preventDefault();

            const payload = {
                nombre_completo: document.getElementById("reg_nombre").value.trim(),
                fecha_nacimiento: document.getElementById("reg_fecha").value,
                sexo: document.getElementById("reg_sexo").value,
                curp: document.getElementById("reg_curp").value.trim(),
                telefono: document.getElementById("reg_telefono").value.trim(),
                email: document.getElementById("reg_email").value.trim()
            };

            try {
                const response = await fetch("/ProyectoADS/public/index.php?url=paciente/registrar", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(payload)
                });
                const res = await response.json();

                const alertModal = document.getElementById("modal-alert-container");
                if (res.status === "success") {
                    alertModal.innerHTML = `<div class="alert alert-success small py-2">${res.message}</div>`;
                    formRegistroPaciente.reset();
                    setTimeout(() => {
                        alertModal.innerHTML = "";
                        const modalEl = document.getElementById("modalRegistroPaciente");
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        modal.hide();
                    }, 2000);
                } else {
                    alertModal.innerHTML = `<div class="alert alert-danger small py-2">${res.message}</div>`;
                }
            } catch (err) {
                console.error("Error:", err);
            }
        });
    }

    // --- 5. CIERRE DE SESIÓN ASÍNCRONO (RF-02) ---
    if (btnLogout) {
        btnLogout.addEventListener("click", async (e) => {
            e.preventDefault();
            try {
                const response = await fetch("/ProyectoADS/public/index.php?url=auth/logout", { method: "POST" });
                const res = await response.json();
                if (res.status === "success") {
                    window.location.href = "/ProyectoADS/views/auth/login.html";
                }
            } catch (err) {
                console.error("Error al cerrar sesión:", err);
            }
        });
    }

    // --- 6. CARGA AUTOMÁTICA DE PACIENTES TRASLADADOS (NUEVO CIRCUITO) ---
    async function cargarPacientesTrasladados() {
        if (!citasContainer) return; // Salir si no estamos en la interfaz del médico

        try {
            const response = await fetch("/ProyectoADS/public/index.php?url=cita/obtenerMisCitas");
            const res = await response.json();

            if (res.status === "success" && res.data.length > 0) {
                citasContainer.innerHTML = ""; 
                badgeContador.textContent = res.data.length; 

                res.data.forEach(cita => {
                    const tarjeta = document.createElement("a");
                    tarjeta.href = "#";
                    tarjeta.className = "list-group-item list-group-item-action p-3 border-bottom";
                    
                    const fechaBonita = new Date(cita.fecha_hora).toLocaleString('es-MX', {
                        month: 'short', day: 'numeric', hour: '2-digit', minute:'2-digit'
                    });

                    tarjeta.innerHTML = `
                        <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                            <strong class="text-dark small">${cita.nombre_completo}</strong>
                            <span class="badge bg-warning text-dark extra-small">${fechaBonita}</span>
                        </div>
                        <p class="mb-0 text-muted extra-small">CURP: ${cita.curp}</p>
                        <span class="text-primary fw-semibold extra-small d-block mt-1">➡️ Dar atención clínica</span>
                    `;

                    tarjeta.addEventListener("click", (e) => {
                        e.preventDefault();
                        panelPlaceholder.classList.add("d-none");
                        panelConsultaActiva.classList.remove("d-none");
                        patientActiveName.textContent = cita.nombre_completo;
                        activePatientId.value = cita.id_paciente; 
                    });

                    citasContainer.appendChild(tarjeta);
                });
            } else {
                badgeContador.textContent = "0";
                citasContainer.innerHTML = `<div class="p-3 text-center text-muted small">Sin transferencias pendientes por hoy.</div>`;
            }
        } catch (err) {
            console.error("Error al cargar la agenda de traslados:", err);
        }
    }

    // Ejecución inicial automática al entrar al Dashboard médico
    cargarPacientesTrasladados();
});