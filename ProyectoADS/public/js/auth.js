// public/js/auth.js

document.addEventListener("DOMContentLoaded", () => {
    const formLogin = document.getElementById("form-login");
    const alertContainer = document.getElementById("alert-container");

    if (formLogin) {
        formLogin.addEventListener("submit", async (e) => {
            e.preventDefault();

            const email = document.getElementById("email").value.trim();
            const password = document.getElementById("password").value.trim();

            // Validación de campo obligatorio (ERR-04)
            if (!email || !password) {
                mostrarAlerta("El campo 'Email/Contraseña' es obligatorio.", "danger");
                return;
            }

            try {
                // Petición asíncrona hacia nuestro Front Controller (RT-06)
                const response = await fetch("/ProyectoADS/public/index.php?url=auth/login", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json; charset=utf-8"
                    },
                    body: JSON.stringify({ email, password })
                });

                const data = await response.json();

                if (data.status === "success") {
                    mostrarAlerta(data.message, "success");
                    // Redirección al panel correspondiente según el rol indicado por el controlador
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    // Muestra error controlado de autenticación (ERR-01)
                    mostrarAlerta(data.message, "danger");
                }
            } catch (error) {
                console.error("Error:", error);
                mostrarAlerta("Error interno de conexión con el servidor.", "danger");
            }
        });
    }

    function mostrarAlerta(mensaje, tipo) {
        alertContainer.innerHTML = `
            <div class="alert alert-${tipo} alert-dismissible fade show small py-2" role="alert">
                ${mensaje}
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
    }
});