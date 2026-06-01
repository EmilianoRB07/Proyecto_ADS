document.addEventListener("DOMContentLoaded", () => {

    const formLogin = document.getElementById("form-login");
    const alertContainer = document.getElementById("alert-container");

    if(formLogin){

        formLogin.addEventListener("submit", (e) => {

            e.preventDefault();

            const email = document.getElementById("email").value.trim();
            const password = document.getElementById("password").value.trim();

            if(!email || !password){
                mostrarAlerta("Todos los campos son obligatorios.", "danger");
                return;
            }

            // Simulación de login
            if(email === "admin@hospitalnet.com" && password === "1234"){

                mostrarAlerta("Inicio de sesión exitoso.", "success");

                setTimeout(() => {
                    window.location.href = "../../dashboard.html";
                }, 1000);

            } else {

                mostrarAlerta("Correo o contraseña incorrectos.", "danger");

            }

        });

    }

    function mostrarAlerta(mensaje, tipo){

        alertContainer.innerHTML = `
            <div class="alert alert-${tipo} alert-dismissible fade show small py-2" role="alert">
                ${mensaje}
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
            </div>
        `;
    }

});